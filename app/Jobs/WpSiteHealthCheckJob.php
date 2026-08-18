<?php

namespace App\Jobs;

use App\Models\WpSite;
use App\Services\WpApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class WpSiteHealthCheckJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE = 'wp_sites';

    public int $tries = 5;

    public function __construct(public WpSite $site)
    {
        $this->onQueue(self::QUEUE);
    }

    /**
     * Backoff (delay) between retry attempts, in seconds.
     */
    public function backoff(): array
    {
        return [10, 60, 300, 600, 900]; // 10s, 1m, 5m, 10m, 15m
    }

    public function handle(WpApiService $api): void
    {
        $site = $this->site->fresh();
        if (!$site) {
            return;
        }

        // Only one health check per site at a time (no concurrent runs). New Re-checks can still be queued.
        $lock = Cache::lock('wp-site-health-' . $site->id, 120);
        if (!$lock->get()) {
            $this->release(30);
            return;
        }

        try {
            $this->runHealthCheck($api, $site);
        } finally {
            $lock->release();
        }
    }

    private function runHealthCheck(WpApiService $api, WpSite $site): void
    {
        $result = $api->fetchStatus($site);
        if (! ($result['ok'] ?? false)) {
            $errorMessage = $result['error'] ?? 'Health check failed';
            $site->update([
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

        $updates = [
            'status' => 'active',
            'last_checked_at' => now(),
            'last_health_error' => null,
        ];

        if (array_key_exists('block_inspect', $result) && $result['block_inspect'] !== null) {
            $updates['block_inspect'] = (bool) $result['block_inspect'];
            $updates['block_inspect_synced_at'] = now();
        }

        if (! empty($result['block_inspect_field'])) {
            $updates['block_inspect_supported'] = true;
        }

        $site->update($updates);
    }

    /**
     * Called after all retries have been exhausted. Mark site as error and always store a reason.
     */
    public function failed(\Throwable $e): void
    {
        $site = $this->site->fresh();
        if (!$site) {
            return;
        }

        $message = trim((string) $e->getMessage());
        if ($message === '') {
            $message = 'Health check failed after ' . $this->tries . ' attempts.';
        }

        $site->update([
            'status' => 'error',
            'last_checked_at' => now(),
            'last_health_error' => $message,
        ]);
    }
}
