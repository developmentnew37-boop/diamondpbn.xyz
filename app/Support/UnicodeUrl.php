<?php

namespace App\Support;

class UnicodeUrl
{
    /**
     * Make a link URL ASCII-safe for remote `url` / FILTER_VALIDATE_URL checks.
     * IDN hosts become punycode; path, query, and fragment are percent-encoded
     * without double-encoding existing %XX sequences.
     */
    public static function normalize(?string $url): string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return $url;
        }

        $parts = parse_url($url);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return $url;
        }

        $scheme = strtolower((string) $parts['scheme']);
        $host = self::asciiHost((string) $parts['host']);
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $user = isset($parts['user']) ? self::encodeComponent((string) $parts['user']) : '';
        $pass = isset($parts['pass']) ? ':'.self::encodeComponent((string) $parts['pass']) : '';
        $auth = $user !== '' ? $user.$pass.'@' : '';
        $path = isset($parts['path']) ? self::encodePath((string) $parts['path']) : '';
        $query = isset($parts['query']) ? '?'.self::encodeQuery((string) $parts['query']) : '';
        $fragment = isset($parts['fragment']) ? '#'.self::encodeComponent((string) $parts['fragment']) : '';

        return $scheme.'://'.$auth.$host.$port.$path.$query.$fragment;
    }

    /**
     * @param  list<array<string, mixed>>|array<int, mixed>  $payload
     * @return list<array<string, mixed>>|array<int, mixed>
     */
    public static function normalizePayload(array $payload): array
    {
        foreach ($payload as $i => $item) {
            if (is_array($item) && isset($item['url'])) {
                $payload[$i]['url'] = self::normalize((string) $item['url']);
            }
        }

        return $payload;
    }

    public static function jsonBody(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    private static function asciiHost(string $host): string
    {
        $host = trim($host);
        if ($host === '') {
            return $host;
        }

        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            return $host;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return str_contains($host, ':') ? '['.$host.']' : $host;
        }

        if (function_exists('idn_to_ascii')) {
            $flags = defined('IDNA_DEFAULT') ? IDNA_DEFAULT : 0;
            $variant = defined('INTL_IDNA_VARIANT_UTS46') ? INTL_IDNA_VARIANT_UTS46 : 0;
            $ascii = $variant
                ? idn_to_ascii($host, $flags, $variant)
                : idn_to_ascii($host, $flags);
            if (is_string($ascii) && $ascii !== '') {
                return $ascii;
            }
        }

        return $host;
    }

    private static function encodePath(string $path): string
    {
        return implode('/', array_map([self::class, 'encodeComponent'], explode('/', $path)));
    }

    private static function encodeQuery(string $query): string
    {
        $pairs = explode('&', $query);
        $out = [];
        foreach ($pairs as $pair) {
            if ($pair === '') {
                continue;
            }
            $eq = strpos($pair, '=');
            if ($eq === false) {
                $out[] = self::encodeComponent($pair);
                continue;
            }
            $out[] = self::encodeComponent(substr($pair, 0, $eq)).'='.self::encodeComponent(substr($pair, $eq + 1));
        }

        return implode('&', $out);
    }

    private static function encodeComponent(string $value): string
    {
        return rawurlencode(rawurldecode($value));
    }
}
