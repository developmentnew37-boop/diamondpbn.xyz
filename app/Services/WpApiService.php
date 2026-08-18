<?php

namespace App\Services;

use App\Models\WpBatchSiteChunk;
use App\Models\WpSite;
use App\Support\ApiUrlHelper;
use App\Support\PbnSettings;
use App\Support\SafeApiUrl;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class WpApiService
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

    private function apiKeyFor(WpSite $site): string
    {
        return $site->api_key ?: (string) env('SITE_API_KEY', '');
    }

    /**
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

    private function authHeaders(WpSite $site): array
    {
        $key = $this->apiKeyFor($site);
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
        if ($key !== '') {
            $headers['Authorization'] = 'Bearer '.$key;
            $headers['X-API-Key'] = $key;
        }

        return $headers;
    }

    public function postChunk(WpSite $site, WpBatchSiteChunk $chunk): array
    {
        $headers = $this->authHeaders($site);
        $body = [
            'payload' => $chunk->links_payload ?? [],
            'batch_id' => $chunk->wp_batch_id,
            'chunk_id' => $chunk->chunk_index,
            'domain_id' => $chunk->wp_site_id,
        ];

        return $this->callWithApiUrlFallback($site->api_url, function (string $apiBase) use ($headers, $body) {
            $url = rtrim($apiBase, '/').'/hidden-links';
            $this->assertSafeApiUrl($apiBase);
            $response = $this->http()->withHeaders($headers)->post($url, $body);

            if ($response->failed()) {
                throw new \RuntimeException('API Error '.$response->status().': '.$response->body());
            }

            return $response->json();
        });
    }

    public function deleteLinkByUrl(WpSite $site, string $url): array
    {
        return $this->callWithApiUrlFallback($site->api_url, function (string $apiBase) use ($site, $url) {
            $endpoint = rtrim($apiBase, '/').'/hidden-links/by-url';
            $this->assertSafeApiUrl($apiBase);
            $response = $this->http()
                ->withHeaders($this->authHeaders($site))
                ->acceptJson()
                ->asJson()
                ->withOptions([
                    'allow_redirects' => ['max' => 5, 'strict' => true, 'protocols' => ['https', 'http']],
                ])
                ->delete($endpoint, ['url' => $url]);

            if ($response->failed()) {
                throw new \RuntimeException('API Error '.$response->status().': '.$response->body());
            }

            return $response->json() ?? [];
        });
    }

    public function deleteLinksByBatchId(WpSite $site, int $batchId): array
    {
        return $this->callWithApiUrlFallback($site->api_url, function (string $apiBase) use ($site, $batchId) {
            $url = rtrim($apiBase, '/').'/hidden-links/by-batch-id';

            return $this->deleteJsonWithRetries($this->apiKeyFor($site), $url, ['batch_id' => $batchId]);
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
            $headers['Authorization'] = 'Bearer '.$apiKey;
            $headers['X-API-Key'] = $apiKey;
        }

        $timeout = PbnSettings::getDeleteTimeoutSeconds();
        $maxAttempts = 5;
        $retryableStatuses = [408, 429, 500, 502, 503, 504, 508, 522, 524];
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
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

        return strlen($body) <= $maxLength ? $body : substr($body, 0, $maxLength).'…';
    }

    /**
     * @return array{ok: bool, error: string|null, block_inspect: bool|null, block_inspect_field: bool, show_hidden_links: bool|null, http_status: int|null}
     */
    public function fetchStatus(WpSite $site): array
    {
        return $this->fetchStatusFromUrl($site->api_url, $this->apiKeyFor($site));
    }

    /**
     * @return array{ok: bool, error: string|null}
     */
    public function ping(WpSite $site): array
    {
        $result = $this->fetchStatus($site);

        return [
            'ok' => $result['ok'],
            'error' => $result['error'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toggleInspect(WpSite $site, bool $blockInspect): array
    {
        return $this->callWithApiUrlFallback($site->api_url, function (string $apiBase) use ($site, $blockInspect) {
            $url = rtrim($apiBase, '/').'/hidden-links/toggle-inspect';
            $this->assertSafeApiUrl($apiBase);
            $response = $this->http()
                ->withHeaders($this->authHeaders($site))
                ->post($url, ['block_inspect' => $blockInspect]);

            if ($response->failed()) {
                throw new \RuntimeException('API Error '.$response->status().': '.$this->truncateResponseBody($response->body()));
            }

            return $response->json() ?? [];
        });
    }

    /**
     * @return array{ok: bool, error: string|null, block_inspect: bool|null, block_inspect_field: bool, show_hidden_links: bool|null, http_status: int|null}
     */
    private function fetchStatusFromUrl(string $apiUrl, string $apiKey): array
    {
        $headers = ['Accept' => 'application/json'];
        if ($apiKey !== '') {
            $headers['Authorization'] = 'Bearer '.$apiKey;
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
                    $decoded = $response->json();
                    $blockInspect = null;
                    $blockInspectField = false;
                    $showHiddenLinks = null;
                    if (is_array($decoded)) {
                        if (array_key_exists('block_inspect', $decoded)) {
                            $blockInspectField = true;
                            $blockInspect = (bool) $decoded['block_inspect'];
                        }
                        if (array_key_exists('show_hidden_links', $decoded)) {
                            $showHiddenLinks = (bool) $decoded['show_hidden_links'];
                        }
                    }

                    return [
                        'ok' => true,
                        'error' => null,
                        'block_inspect' => $blockInspect,
                        'block_inspect_field' => $blockInspectField,
                        'show_hidden_links' => $showHiddenLinks,
                        'http_status' => $response->status(),
                    ];
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
                    $error = 'Invalid or missing API key. Set the site API key in Edit.';
                }

                return [
                    'ok' => false,
                    'error' => $error,
                    'block_inspect' => null,
                    'block_inspect_field' => false,
                    'show_hidden_links' => null,
                    'http_status' => $status,
                ];
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                $error = 'Connection failed: '.$e->getMessage();
                if ($index < count($candidates) - 1 && ApiUrlHelper::shouldTryNextUrlAfterFailure($e)) {
                    continue;
                }

                return [
                    'ok' => false,
                    'error' => $error,
                    'block_inspect' => null,
                    'block_inspect_field' => false,
                    'show_hidden_links' => null,
                    'http_status' => null,
                ];
            } catch (\Exception $e) {
                $error = $e->getMessage();
                if ($index < count($candidates) - 1 && ApiUrlHelper::shouldTryNextUrlAfterFailure($e)) {
                    continue;
                }

                return [
                    'ok' => false,
                    'error' => $error,
                    'block_inspect' => null,
                    'block_inspect_field' => false,
                    'show_hidden_links' => null,
                    'http_status' => null,
                ];
            }
        }

        return [
            'ok' => false,
            'error' => 'Health check failed for all API URL variants.',
            'block_inspect' => null,
            'block_inspect_field' => false,
            'show_hidden_links' => null,
            'http_status' => null,
        ];
    }
}
