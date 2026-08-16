<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\BatchDomainChunk;
use App\Models\Domain;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $userId = auth()->id();
        $batches = Batch::where('user_id', $userId)->orderBy('name')->get(['id', 'name']);
        $domains = Domain::where('user_id', $userId)->orderBy('domain')->get(['id', 'domain']);

        $baseQuery = $this->filteredChunksQuery($userId, $request);
        $chunkCount = (clone $baseQuery)->count('batch_domain_chunks.id');
        $requireBatchFilter = ! $request->filled('batch_id')
            && BatchDomainChunk::query()
                ->join('batches', 'batches.id', '=', 'batch_domain_chunks.batch_id')
                ->where('batches.user_id', $userId)
                ->count() > 10000;

        if ($request->get('export') === 'csv') {
            if ($requireBatchFilter) {
                return redirect()->route('reports.index', $request->query())
                    ->with('error', 'Select a batch before exporting — too many records to export all at once.');
            }

            return $this->exportCsv($baseQuery);
        }

        $perPage = 200;
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $offsetRows = max(0, ($currentPage - 1) * $perPage);

        if ($requireBatchFilter) {
            $totalRows = 0;
            $rows = collect();
        } else {
            $totalRows = $this->countReportRows($baseQuery);
            $rows = $this->buildReportRowsForPage($baseQuery, $offsetRows, $perPage);
        }

        $rows = new LengthAwarePaginator(
            $rows,
            $totalRows,
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('reports.index', compact('batches', 'domains', 'rows', 'chunkCount', 'totalRows', 'requireBatchFilter'));
    }

    private function filteredChunksQuery(int $userId, Request $request): Builder
    {
        $query = BatchDomainChunk::query()
            ->join('batches', 'batches.id', '=', 'batch_domain_chunks.batch_id')
            ->leftJoin('domains', 'domains.id', '=', 'batch_domain_chunks.domain_id')
            ->where('batches.user_id', $userId);

        if ($request->filled('batch_id')) {
            $query->where('batch_domain_chunks.batch_id', $request->batch_id);
        }
        if ($request->filled('domain_id')) {
            $query->where('batch_domain_chunks.domain_id', $request->domain_id);
        }
        if ($request->filled('from_date')) {
            $query->whereDate('batch_domain_chunks.created_at', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('batch_domain_chunks.created_at', '<=', $request->to_date);
        }

        return $query
            ->orderByDesc('batch_domain_chunks.completed_at')
            ->orderByDesc('batch_domain_chunks.created_at')
            ->orderByDesc('batch_domain_chunks.id');
    }

    private function countReportRows(Builder $query): int
    {
        return (int) (clone $query)
            ->reorder()
            ->cloneWithout(['columns'])
            ->cloneWithoutBindings(['select'])
            ->selectRaw('COALESCE(SUM('.$this->effectiveLinksCountExpression().'), 0) as aggregate')
            ->value('aggregate');
    }

    private function effectiveLinksCountExpression(): string
    {
        // Always derive from links_payload — links_count column is often stale (0) on older rows.
        return 'COALESCE(JSON_LENGTH(batch_domain_chunks.links_payload), 0)';
    }

    private function linksCountAggregateExpression(): string
    {
        return $this->effectiveLinksCountExpression();
    }

    private function chunkLinksCountSelect(): array
    {
        return [DB::raw($this->effectiveLinksCountExpression().' as links_count')];
    }

    /**
     * Flatten chunk payloads into report rows for a single page without loading every payload.
     */
    private function buildReportRowsForPage(Builder $query, int $offsetRows, int $limitRows): Collection
    {
        $rows = collect();
        $generatedRowIndex = 0;
        $chunkPageEnd = $offsetRows + $limitRows;
        $windowChunks = [];

        $metaQuery = (clone $query)->select(array_merge([
            'batch_domain_chunks.id',
            'batch_domain_chunks.status',
            'batch_domain_chunks.completed_at',
            'batch_domain_chunks.created_at',
            'batches.name as batch_name',
            DB::raw('COALESCE(domains.domain, CONCAT("domain #", batch_domain_chunks.domain_id)) as domain_name'),
        ], $this->chunkLinksCountSelect()));

        foreach ($metaQuery->cursor() as $chunk) {
            $chunkRowCount = (int) ($chunk->links_count ?? 0);
            if ($chunkRowCount === 0) {
                continue;
            }

            $chunkStartIndex = $generatedRowIndex;
            $chunkEndIndex = $generatedRowIndex + $chunkRowCount;

            if ($chunkEndIndex <= $offsetRows) {
                $generatedRowIndex = $chunkEndIndex;
                continue;
            }

            if ($chunkStartIndex >= $chunkPageEnd) {
                break;
            }

            $windowChunks[] = [
                'id' => (int) $chunk->id,
                'batch_name' => $chunk->batch_name ?? '-',
                'domain_name' => $chunk->domain_name ?? '-',
                'status' => $chunk->status,
                'completed_at' => $chunk->completed_at,
                'created_at' => $chunk->created_at,
                'start' => (int) max(0, $offsetRows - $chunkStartIndex),
                'end' => (int) min($chunkRowCount, $chunkPageEnd - $chunkStartIndex),
            ];

            $generatedRowIndex = $chunkEndIndex;

            if ($generatedRowIndex >= $chunkPageEnd) {
                break;
            }
        }

        if ($windowChunks === []) {
            return $rows;
        }

        $payloads = BatchDomainChunk::query()
            ->whereIn('id', array_column($windowChunks, 'id'))
            ->get(['id', 'links_payload', 'results_payload', 'status', 'completed_at', 'created_at'])
            ->keyBy('id');

        foreach ($windowChunks as $window) {
            $chunk = $payloads->get($window['id']);
            if (!$chunk) {
                continue;
            }

            $linksPayload = is_array($chunk->links_payload) ? $chunk->links_payload : [];
            $resultsPayload = is_array($chunk->results_payload) ? $chunk->results_payload : [];
            $date = $window['completed_at'] ?? $window['created_at'] ?? $chunk->completed_at ?? $chunk->created_at;

            for ($i = $window['start']; $i < $window['end']; $i++) {
                $result = $resultsPayload[$i] ?? null;
                $status = $result['status'] ?? ($window['status'] ?? $chunk->status ?? 'pending');

                $rows->push((object) [
                    'batch' => (object) ['name' => $window['batch_name']],
                    'domain' => (object) ['domain' => $window['domain_name']],
                    'link' => (object) [
                        'url' => $linksPayload[$i]['url'] ?? '-',
                        'keyword' => $linksPayload[$i]['keyword'] ?? '-',
                    ],
                    'status' => $status,
                    'created_at' => $date,
                    'error' => $result['error'] ?? $result['error_message'] ?? null,
                ]);
            }
        }

        return $rows->values();
    }

    private function chunksToReportRows(Collection $chunks): Collection
    {
        $rows = collect();

        foreach ($chunks as $chunk) {
            $linksPayload = is_array($chunk->links_payload) ? $chunk->links_payload : [];
            $resultsPayload = is_array($chunk->results_payload) ? $chunk->results_payload : [];
            $date = $chunk->completed_at ?? $chunk->created_at;
            $batchName = $chunk->batch_name ?? $chunk->batch?->name ?? '-';
            $domainName = $chunk->domain_name ?? $chunk->domain?->domain ?? '-';

            foreach ($linksPayload as $i => $linkData) {
                $result = $resultsPayload[$i] ?? null;
                $status = $result['status'] ?? ($chunk->status ?? 'pending');

                $rows->push((object) [
                    'batch' => (object) ['name' => $batchName],
                    'domain' => (object) ['domain' => $domainName],
                    'link' => (object) [
                        'url' => $linkData['url'] ?? '-',
                        'keyword' => $linkData['keyword'] ?? '-',
                    ],
                    'status' => $status,
                    'created_at' => $date,
                    'error' => $result['error'] ?? $result['error_message'] ?? null,
                ]);
            }
        }

        return $rows;
    }

    private function exportCsv(Builder $query): StreamedResponse
    {
        $filename = 'links-report-' . date('Y-m-d-His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $exportQuery = (clone $query)->select([
            'batch_domain_chunks.id',
            'batch_domain_chunks.links_payload',
            'batch_domain_chunks.results_payload',
            'batch_domain_chunks.status',
            'batch_domain_chunks.completed_at',
            'batch_domain_chunks.created_at',
            'batches.name as batch_name',
            DB::raw('COALESCE(domains.domain, CONCAT("domain #", batch_domain_chunks.domain_id)) as domain_name'),
        ]);

        return response()->stream(function () use ($exportQuery) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Batch', 'Domain', 'URL', 'Keyword', 'Status', 'Posted At', 'Error']);

            foreach ($exportQuery->cursor() as $chunk) {
                $rows = $this->chunksToReportRows(collect([$chunk]));
                foreach ($rows as $r) {
                    fputcsv($handle, [
                        $r->batch->name ?? '',
                        $r->domain->domain ?? '',
                        $r->link->url ?? '',
                        $r->link->keyword ?? '',
                        $r->status ?? '',
                        $r->created_at?->format('Y-m-d H:i:s') ?? '',
                        $r->error ?? '',
                    ]);
                }
            }

            fclose($handle);
        }, 200, $headers);
    }
}
