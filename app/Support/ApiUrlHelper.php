<?php

namespace App\Support;

class ApiUrlHelper
{
    /**
     * Normalize API URL for storage (trim, ensure scheme).
     */
    public static function normalizeForStorage(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return $url;
        }

        if (! preg_match('#^https?://#i', $url)) {
            $scheme = self::defaultScheme();
            $url = $scheme.'://'.ltrim($url, '/');
        }

        return rtrim($url, '/');
    }

    /**
     * REST API base without trailing /status (health checks may store or accept .../v1/status).
     */
    public static function restApiBase(string $apiUrl): string
    {
        $base = self::normalizeForStorage($apiUrl);
        if (preg_match('#/status$#i', $base)) {
            $base = substr($base, 0, -7);
        }

        return rtrim($base, '/');
    }

    public static function defaultScheme(): string
    {
        $scheme = strtolower((string) config('services.pbn.default_api_scheme', 'http'));

        return $scheme === 'https' ? 'https' : 'http';
    }

    /**
     * URLs to try in order (e.g. https then http when fallback is enabled).
     *
     * @return list<string>
     */
    public static function candidateApiUrls(string $apiUrl): array
    {
        $apiUrl = self::restApiBase($apiUrl);
        if ($apiUrl === '') {
            return [];
        }

        $candidates = [$apiUrl];

        if (config('services.pbn.https_to_http_fallback', true)) {
            $http = self::httpsToHttp($apiUrl);
            if ($http !== null && $http !== $apiUrl) {
                $candidates[] = $http;
            }
        }

        return array_values(array_unique($candidates));
    }

    public static function httpsToHttp(string $apiUrl): ?string
    {
        if (! preg_match('#^https://#i', $apiUrl)) {
            return null;
        }

        return 'http://'.substr($apiUrl, 8);
    }

    public static function shouldTryNextUrlAfterFailure(\Throwable $e): bool
    {
        if (! config('services.pbn.https_to_http_fallback', true)) {
            return false;
        }

        return self::isLikelyTlsOrSslFailure($e->getMessage());
    }

    public static function isLikelyTlsOrSslFailure(string $message): bool
    {
        $message = strtolower($message);

        foreach ([
            'ssl',
            'tls',
            'certificate',
            'cert',
            'handshake',
            'curl error 35',
            'curl error 51',
            'curl error 60',
            'unable to verify',
            'wrong version number',
            'connection refused',
            'failed to connect',
            'could not resolve host',
        ] as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }
}
