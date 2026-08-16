<?php

namespace App\Jobs;

use App\Models\Domain;
use App\Services\PbnApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class DomainHealthCheckJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE = 'domains';

    public int $tries = 5;

    public function __construct(public Domain $domain)
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

    public function handle(PbnApiService $api): void
    {
        $domain = $this->domain->fresh();
        if (!$domain) {
            return;
        }

        // Only one health check per domain at a time (no concurrent runs). New Re-checks can still be queued.
        $lock = Cache::lock('domain-health-' . $domain->id, 120);
        if (!$lock->get()) {
            $this->release(30);
            return;
        }

        try {
            $this->runHealthCheck($api, $domain);
        } finally {
            $lock->release();
        }
    }

    private function runHealthCheck(PbnApiService $api, Domain $domain): void
    {
        $result = $api->ping($domain);
        if (! ($result['ok'] ?? false)) {
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

        // No error from API → mark as active and clear last error.
        $domain->update([
            'status' => 'active',
            'last_checked_at' => now(),
            'last_health_error' => null,
        ]);
    }

    /**
     * Called after all retries have been exhausted. Mark domain as error and always store a reason.
     */
    public function failed(\Throwable $e): void
    {
        $domain = $this->domain->fresh();
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

