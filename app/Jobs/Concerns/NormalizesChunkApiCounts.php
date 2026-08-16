<?php

namespace App\Jobs\Concerns;

trait NormalizesChunkApiCounts
{
    /**
     * @param  array<int, array<string, mixed>>  $responsePayload
     * @return array{0: int, 1: int}
     */
    protected function resolveChunkCounts(array $responsePayload, int $linkCount, array $response): array
    {
        $successCount = (int) ($response['success'] ?? 0);
        $failedCount = (int) ($response['failed'] ?? 0);

        if ($successCount + $failedCount > 0) {
            return [$successCount, $failedCount];
        }

        if ($responsePayload !== []) {
            return $this->countResultsFromPayload($responsePayload);
        }

        if ($linkCount > 0) {
            return [0, $linkCount];
        }

        return [0, 0];
    }

    /**
     * @param  array<int, array<string, mixed>>  $responsePayload
     * @return array{0: int, 1: int}
     */
    protected function countResultsFromPayload(array $responsePayload): array
    {
        $successCount = 0;
        $failedCount = 0;

        foreach ($responsePayload as $item) {
            $status = $item['status'] ?? '';
            if (in_array($status, ['success', 'completed', 'posted'], true) || ! empty($item['link_id'])) {
                $successCount++;
            } elseif ($status === 'failed' || ! empty($item['error'])) {
                $failedCount++;
            }
        }

        return [$successCount, $failedCount];
    }
}
