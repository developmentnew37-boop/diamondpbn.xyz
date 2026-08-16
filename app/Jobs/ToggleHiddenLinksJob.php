<?php

namespace App\Jobs;

use App\Models\CampaignDomain;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ToggleHiddenLinksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE = 'toggle_hidden_links';

    public int $tries = 3;

    public function __construct(
        public CampaignDomain $domain,
        public bool $showHiddenLinks
    ) {
        $this->onQueue(self::QUEUE);
    }

    public function handle(): void
    {
        try {
            $apiUrl = rtrim($this->domain->api_url, '/') . '/hidden-links/toggle-visibility';

            $response = Http::timeout(15)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . ($this->domain->api_key ?? ''),
                    'Accept' => 'application/json',
                ])
                ->post($apiUrl, [
                    'show_hidden_links' => $this->showHiddenLinks,
                ]);

            if ($response->successful()) {
                Log::info("Hidden links toggled successfully for domain: {$this->domain->domain}", [
                    'domain_id' => $this->domain->id,
                    'show_hidden_links' => $this->showHiddenLinks,
                    'response' => $response->json(),
                ]);
            } elseif ($response->status() === 404) {
                // API endpoint not found on target domain - don't retry
                Log::warning("Hidden links API endpoint not found on domain: {$this->domain->domain}", [
                    'domain_id' => $this->domain->id,
                    'api_url' => $apiUrl,
                    'message' => 'The domain does not have the /api/hidden-links/toggle-visibility endpoint implemented',
                ]);

                // Don't throw exception for 404 - just log and skip
                return;
            } else {
                Log::error("Failed to toggle hidden links for domain: {$this->domain->domain}", [
                    'domain_id' => $this->domain->id,
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);

                throw new \Exception("API returned status {$response->status()}: {$response->body()}");
            }
        } catch (\Exception $e) {
            Log::error("Exception while toggling hidden links for domain: {$this->domain->domain}", [
                'domain_id' => $this->domain->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
