<?php

namespace App\Support;

class SafeApiUrl
{
    /**
     * Validate an outbound API URL. Returns an error message or null if safe.
     */
    public static function validate(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return 'API URL is required.';
        }

        $parts = parse_url($url);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return 'API URL must be a valid URL with a host.';
        }

        $scheme = strtolower($parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            return 'API URL must use http or https.';
        }

        $host = strtolower($parts['host']);
        if ($host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            return 'API URL must not point to localhost or internal hostnames.';
        }

        if (self::isBlockedIp($host)) {
            return 'API URL must not point to a private or reserved IP address.';
        }

        $resolvedIps = self::resolveHostIps($host);
        foreach ($resolvedIps as $ip) {
            if (self::isBlockedIp($ip)) {
                return 'API URL resolves to a private or reserved IP address.';
            }
        }

        return null;
    }

    public static function assertSafe(string $url): void
    {
        $error = self::validate($url);
        if ($error !== null) {
            throw new \InvalidArgumentException($error);
        }
    }

    /**
     * @return list<string>
     */
    private static function resolveHostIps(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $ips = [];
        $records = @dns_get_record($host, DNS_A + DNS_AAAA);
        if (is_array($records)) {
            foreach ($records as $record) {
                if (! empty($record['ip'])) {
                    $ips[] = $record['ip'];
                }
                if (! empty($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }

        return array_values(array_unique($ips));
    }

    private static function isBlockedIp(string $value): bool
    {
        if (! filter_var($value, FILTER_VALIDATE_IP)) {
            return false;
        }

        return filter_var(
            $value,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }
}
