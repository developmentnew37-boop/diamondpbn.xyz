<?php

namespace App\Services;

use App\Models\BatchDomainChunk;
use App\Models\CampaignDomain;
use App\Models\CampaignDomainChunk;
use App\Models\Domain;
use App\Models\Link;
use App\Support\ApiUrlHelper;
use App\Support\PbnSettings;
use App\Support\SafeApiUrl;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class PbnApiService
{
    private function timeout(): int
    {
        return PbnSettings::getApiTimeoutSeconds();
    }

    private function http(int $timeoutSeconds = 0): PendingRequest
    {
        return Http::withoutVerifying()->timeout($timeoutSeconds > 0 ? $timeoutSeconds : $this->timeout());
    }

    private function assertSafeApiUrl(string $apiUrl): void
    {
        SafeApiUrl::assertSafe($apiUrl);
    }

    /**
     * Try the stored API URL, then http:// if https fails (sites without SSL).
     *
     * @template T
     *
     * @param  callable(string $apiBase): T  $callback
     * @return T
     */
    private function callWithApiUrlFallback(string $storedApiUrl, callable $callback): mixed
    {
        $candidates = ApiUrlHelper::candidateApiUrls($storedApiUrl);
        if ($candidates === []) {
            throw new \InvalidArgumentException('API URL is required.');
        }

        $last = null;
        foreach ($candidates as $index => $apiBase) {
            try {
                return $callback($apiBase);
            } catch (\Throwable $e) {
                $last = $e;
                $hasAlternate = $index < count($candidates) - 1;
                if ($hasAlternate && ApiUrlHelper::shouldTryNextUrlAfterFailure($e)) {
                    continue;
                }
                throw $e;
            }
        }

        throw $last ?? new \RuntimeException('API request failed.');
    }
    private function apiKeyFor(Domain $domain): string
    {
        return $domain->api_key ?: (string) env('SITE_API_KEY', '');
    }

    private function apiKeyForCampaignDomain(CampaignDomain $domain): string
    {
        return $domain->api_key ?: (string) env('SITE_API_KEY', '');
    }

    /**
     * Publish a campaign chunk to a remote domain via POST /hidden-links (api.md).
     * Uses the campaign domain's API key. Returns the API response array (payload, success, failed, etc.).
     */
    public function postCampaignChunk(CampaignDomain $domain, CampaignDomainChunk $chunk): array
    {
        $key = $this->apiKeyForCampaignDomain($domain);
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
        if ($key !== '') {
            $headers['Authorization'] = 'Bearer ' . $key;
            $headers['X-API-Key'] = $key;
        }

        $payload = $chunk->links_payload ?? [];
        $body = [
            'payload' => $payload,
            'batch_id' => $chunk->campaign_id, // Use campaign_id as batch_id for API compatibility
            'chunk_id' => $chunk->chunk_index,
            'domain_id' => $chunk->campaign_domain_id,
        ];

        return $this->callWithApiUrlFallback($domain->api_url, function (string $apiBase) use ($headers, $body) {
            $url = rtrim($apiBase, '/') . '/hidden-links';
            $this->assertSafeApiUrl($apiBase);
            $response = $this->http()
                ->withHeaders($headers)
                ->post($url, $body);

            if ($response->failed()) {
                throw new \RuntimeException(
                    'API Error ' . $response->status() . ': ' . $response->body()
                );
            }

            return $response->json();
        });
    }

    /**
     * Publish a batch chunk to a remote domain via POST /hidden-links (api.md).
     * Uses the domain's API key. Returns the API response array (payload, success, failed, etc.).
     */
    public function postChunk(Domain $domain, BatchDomainChunk $chunk): array
    {
        $key = $this->apiKeyFor($domain);
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
        if ($key !== '') {
            $headers['Authorization'] = 'Bearer ' . $key;
            $headers['X-API-Key'] = $key;
        }

        $payload = $chunk->links_payload ?? [];
        $body = [
            'payload' => $payload,
            'batch_id' => $chunk->batch_id,
            'chunk_id' => $chunk->chunk_index,
            'domain_id' => $chunk->domain_id,
        ];

        return $this->callWithApiUrlFallback($domain->api_url, function (string $apiBase) use ($headers, $body) {
            $url = rtrim($apiBase, '/') . '/hidden-links';
            $this->assertSafeApiUrl($apiBase);
            $response = $this->http()
                ->withHeaders($headers)
                ->post($url, $body);

            if ($response->failed()) {
                throw new \RuntimeException(
                    'API Error ' . $response->status() . ': ' . $response->body()
                );
            }

            return $response->json();
        });
    }

    public function postLink(Domain $domain, Link $link): array
    {
        $this->assertSafeApiUrl($domain->api_url);
        $response = $this->http()->withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKeyFor($domain),
            'Accept' => 'application/json',
        ])
            ->post(rtrim($domain->api_url, '/') . '/links', [
                'url' => $link->url,
                'keyword' => $link->keyword,
                'nofollow' => $link->no_follow,
                'batch_id' => $link->batch_id,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException(
                'API Error ' . $response->status() . ': ' . $response->body()
            );
        }

        return $response->json();
    }

    /**
     * Delete hidden links by URL on a remote domain (api.md: DELETE /hidden-links/by-url).
     * Use this to remove a single link from a remote site by its exact URL.
     */
    public function deleteLinkByUrl(Domain $domain, string $url): array
    {
        $key = $this->apiKeyFor($domain);
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
        if ($key !== '') {
            $headers['Authorization'] = 'Bearer ' . $key;
            $headers['X-API-Key'] = $key;
        }

        $endpoint = rtrim($domain->api_url, '/') . '/hidden-links/by-url';
        $this->assertSafeApiUrl($domain->api_url);
        $response = $this->http()
            ->withHeaders($headers)
            ->withBody(json_encode(['url' => $url]), 'application/json')
            ->delete($endpoint);

        if ($response->failed()) {
            throw new \RuntimeException(
                'API Error ' . $response->status() . ': ' . $response->body()
            );
        }

        return $response->json();
    }

    /**
     * Delete hidden links by URL on a campaign domain (api.md: DELETE /hidden-links/by-url).
     * Use this to remove a single link from a remote site by its exact URL.
     */
    public function deleteLinkByUrlFromCampaignDomain(CampaignDomain $domain, string $url): array
    {
        $key = $this->apiKeyForCampaignDomain($domain);
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
        if ($key !== '') {
            $headers['Authorization'] = 'Bearer ' . $key;
            $headers['X-API-Key'] = $key;
        }

        $endpoint = rtrim($domain->api_url, '/') . '/hidden-links/by-url';
        $this->assertSafeApiUrl($domain->api_url);
        $response = $this->http()
            ->withHeaders($headers)
            ->withBody(json_encode(['url' => $url]), 'application/json')
            ->delete($endpoint);

        if ($response->failed()) {
            throw new \RuntimeException(
                'API Error ' . $response->status() . ': ' . $response->body()
            );
        }

        return $response->json();
    }

    /**
     * Delete all hidden links for a batch_id on a remote domain (api.md: DELETE /hidden-links/by-batch-id).
     * Uses a long timeout and retries transient upstream errors (508, 522, etc.).
     */
    public function deleteLinksByBatchId(Domain $domain, int $batchId): array
    {
        return $this->callWithApiUrlFallback($domain->api_url, function (string $apiBase) use ($domain, $batchId) {
            $url = rtrim($apiBase, '/') . '/hidden-links/by-batch-id';

            return $this->deleteJsonWithRetries($this->apiKeyFor($domain), $url, ['batch_id' => $batchId]);
        });
    }

    public function deleteCampaignLinksByBatchId(CampaignDomain $domain, int $campaignId): array
    {
        return $this->callWithApiUrlFallback($domain->api_url, function (string $apiBase) use ($domain, $campaignId) {
            $url = rtrim($apiBase, '/') . '/hidden-links/by-batch-id';

            return $this->deleteJsonWithRetries($this->apiKeyForCampaignDomain($domain), $url, ['batch_id' => $campaignId]);
        });
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function deleteJsonWithRetries(string $apiKey, string $url, array $body): array
    {
        $this->assertSafeApiUrl($url);

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
        if ($apiKey !== '') {
            $headers['Authorization'] = 'Bearer ' . $apiKey;
            $headers['X-API-Key'] = $apiKey;
        }

        $timeout = PbnSettings::getDeleteTimeoutSeconds();
        $maxAttempts = 5;
        $retryableStatuses = [408, 429, 500, 502, 503, 504, 508, 522, 524];
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                // Strict redirects: a plain 301/302 otherwise turns DELETE into GET and the PBN API returns 405.
                $response = $this->http($timeout)
                    ->withHeaders($headers)
                    ->acceptJson()
                    ->asJson()
                    ->withOptions([
                        'allow_redirects' => [
                            'max' => 5,
                            'strict' => true,
                            'protocols' => ['https', 'http'],
                        ],
                    ])
                    ->connectTimeout(60)
                    ->delete($url, $body);

                if ($response->successful()) {
                    return $response->json() ?? [];
                }

                $message = 'API Error '.$response->status().': '.$this->truncateResponseBody($response->body());
                $lastException = new \RuntimeException($message);

                if (in_array($response->status(), $retryableStatuses, true) && $attempt < $maxAttempts) {
                    sleep(min(120, 15 * $attempt));
                    continue;
                }

                throw $lastException;
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                $lastException = $e;
                if ($attempt < $maxAttempts) {
                    sleep(min(120, 15 * $attempt));
                    continue;
                }
                throw $e;
            }
        }

        throw $lastException ?? new \RuntimeException('Delete request failed after retries.');
    }

    private function truncateResponseBody(?string $body, int $maxLength = 300): string
    {
        $body = trim((string) $body);
        if ($body === '') {
            return '';
        }

        if (strlen($body) <= $maxLength) {
            return $body;
        }

        return substr($body, 0, $maxLength).'…';
    }

    /**
     * Run health check against domain status endpoint.
     * Returns ['ok' => bool, 'error' => string|null]. Use 'error' when ok is false to show reason.
     * Sends API key as both Bearer and X-API-Key so servers that expect either will accept.
     */
    public function ping(Domain $domain): array
    {
        return $this->pingSite($domain->api_url, $this->apiKeyFor($domain));
    }

    public function pingCampaignDomain(CampaignDomain $domain): array
    {
        return $this->pingSite($domain->api_url, $this->apiKeyForCampaignDomain($domain));
    }

    /**
     * @return array{ok: bool, error: string|null}
     */
    private function pingSite(string $apiUrl, string $apiKey): array
    {
        $headers = [
            'Accept' => 'application/json',
        ];
        if ($apiKey !== '') {
            $headers['Authorization'] = 'Bearer ' . $apiKey;
            $headers['X-API-Key'] = $apiKey;
        }

        $candidates = ApiUrlHelper::candidateApiUrls($apiUrl);

        foreach ($candidates as $index => $apiBase) {
            $this->assertSafeApiUrl($apiBase);
            $base = rtrim($apiBase, '/');
            $url = str_ends_with($base, '/status') ? $base : $base.'/status';

            try {
                $response = $this->http()->withHeaders($headers)->get($url);

                if ($response->successful()) {
                    return ['ok' => true, 'error' => null];
                }

                $status = $response->status();
                $body = $response->body();
                $error = "HTTP {$status}";
                $decoded = @json_decode($body, true);
                if (is_array($decoded) && ! empty($decoded['message'])) {
                    $error = $decoded['message'];
                } elseif (trim($body) !== '') {
                    $preview = strlen($body) > 200 ? substr($body, 0, 200).'…' : $body;
                    $error .= ' — '.$preview;
                }
                if ($status === 401 && $apiKey === '') {
                    $error = 'Invalid or missing API key. Set the domain’s API key in Edit, or SITE_API_KEY in .env (same token as in Postman).';
                }

                return ['ok' => false, 'error' => $error];
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                $error = 'Connection failed: '.$e->getMessage();
                $hasAlternate = $index < count($candidates) - 1;
                if ($hasAlternate && ApiUrlHelper::shouldTryNextUrlAfterFailure($e)) {
                    continue;
                }

                return ['ok' => false, 'error' => $error];
            } catch (\Exception $e) {
                $error = $e->getMessage();
                $hasAlternate = $index < count($candidates) - 1;
                if ($hasAlternate && ApiUrlHelper::shouldTryNextUrlAfterFailure($e)) {
                    continue;
                }

                return ['ok' => false, 'error' => $error];
            }
        }

        return ['ok' => false, 'error' => 'Health check failed for all API URL variants.'];
    }
}


