<?php

namespace App\Jobs;

use App\Models\WpSite;
use App\Services\WpApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ToggleWpInspectJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE = 'wp_sites';

    public int $tries = 3;

    public function __construct(
        public WpSite $site,
        public bool $blockInspect
    ) {
        $this->onQueue(self::QUEUE);
    }

    public function handle(WpApiService $api): void
    {
        $site = $this->site->fresh();
        if (! $site) {
            return;
        }

        try {
            $response = $api->toggleInspect($site, $this->blockInspect);
            $blockInspect = array_key_exists('block_inspect', $response)
                ? (bool) $response['block_inspect']
                : $this->blockInspect;

            $site->update([
                'block_inspect' => $blockInspect,
                'block_inspect_synced_at' => now(),
                'block_inspect_supported' => true,
            ]);

            Log::info('WP inspect blocking toggled', [
                'wp_site_id' => $site->id,
                'domain' => $site->domain,
                'block_inspect' => $blockInspect,
            ]);
        } catch (\Throwable $e) {
            if ($this->isNotFoundError($e)) {
                $site->update(['block_inspect_supported' => false]);

                Log::warning('WP toggle-inspect endpoint not found (plugin may be older than v1.3.0)', [
                    'wp_site_id' => $site->id,
                    'domain' => $site->domain,
                    'api_url' => $site->api_url,
                    'error' => $e->getMessage(),
                ]);

                return;
            }

            Log::error('Failed to toggle WP inspect blocking', [
                'wp_site_id' => $site->id,
                'domain' => $site->domain,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function isNotFoundError(\Throwable $e): bool
    {
        return str_contains($e->getMessage(), '404');
    }
}
