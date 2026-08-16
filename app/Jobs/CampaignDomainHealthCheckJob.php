<?php

namespace App\Jobs;

use App\Models\CampaignDomain;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class CampaignDomainHealthCheckJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE = 'campaign_domains';

    public int $tries = 3;

    public function __construct(public CampaignDomain $campaignDomain)
    {
        $this->onQueue(self::QUEUE);
    }

    public function backoff(): array
    {
        return [10, 60, 300]; // 10s, 1m, 5m
    }

    public function handle(): void
    {
        $domain = $this->campaignDomain->fresh();
        if (!$domain) {
            return;
        }

        $lock = Cache::lock('campaign-domain-health-' . $domain->id, 120);
        if (!$lock->get()) {
            $this->release(30);
            return;
        }

        try {
            $this->runHealthCheck($domain);
        } finally {
            $lock->release();
        }
    }

    private function runHealthCheck(CampaignDomain $domain): void
    {
        $result = $this->pingCampaignDomain($domain);

        if (!($result['ok'] ?? false)) {
            $errorMessage = $result['error'] ?? 'Health check failed';
            $domain->update([
                'last_checked_at' => now(),
                'last_health_error' => $errorMessage,
            ]);

            if ($this->attempts() < $this->tries) {
                $backoff = $this->backoff();
                $delaySeconds = $backoff[$this->attempts() - 1] ?? 60;
                $this->release($delaySeconds);
                return;
            }

            throw new \RuntimeException($errorMessage);
        }

        $domain->update([
            'status' => 'active',
            'last_checked_at' => now(),
            'last_health_error' => null,
        ]);
    }

    private function pingCampaignDomain(CampaignDomain $domain): array
    {
        $base = rtrim($domain->api_url, '/');
        $url = str_ends_with($base, '/status') ? $base : $base . '/status';
        $key = $domain->api_key ?: '';

        $headers = [
            'Accept' => 'application/json',
        ];
        if ($key !== '') {
            $headers['Authorization'] = 'Bearer ' . $key;
            $headers['X-API-Key'] = $key;
        }

        try {
            $response = Http::withoutVerifying()
                ->withHeaders($headers)
                ->timeout(30)
                ->get($url);

            if ($response->successful()) {
                return ['ok' => true, 'error' => null];
            }

            $status = $response->status();
            $body = $response->body();
            $error = "HTTP {$status}";
            $decoded = @json_decode($body, true);
            if (is_array($decoded) && !empty($decoded['message'])) {
                $error = $decoded['message'];
            } elseif (trim($body) !== '') {
                $preview = strlen($body) > 200 ? substr($body, 0, 200) . '…' : $body;
                $error .= ' — ' . $preview;
            }
            if ($status === 401 && $key === '') {
                $error = 'Invalid or missing API key';
            }
            return ['ok' => false, 'error' => $error];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return ['ok' => false, 'error' => 'Connection failed: ' . $e->getMessage()];
        } catch (\Exception $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public function failed(\Throwable $e): void
    {
        $domain = $this->campaignDomain->fresh();
        if (!$domain) {
            return;
        }

        $message = trim((string) $e->getMessage());
        if ($message === '') {
            $message = 'Health check failed after ' . $this->tries . ' attempts.';
        }

        $domain->update([
            'status' => 'error',
            'last_checked_at' => now(),
            'last_health_error' => $message,
        ]);
    }
}
