<?php

declare(strict_types=1);

namespace Bastivan\UniversalApi\Core;

/**
 * Class TrustedProxyResolver
 * Developed by Bastivan Consulting
 *
 * Handles fetching and caching of trusted proxy IPs (Cloudflare, Gcore).
 */
class TrustedProxyResolver
{
    private const CACHE_KEY = 'security:trusted_proxies';
    private const CACHE_TTL = 86400; // 24 hours

    private const CLOUDFLARE_V4 = 'https://www.cloudflare.com/ips-v4';
    private const CLOUDFLARE_V6 = 'https://www.cloudflare.com/ips-v6';
    private const GCORE_IPS = 'https://api.gcore.com/cdn/public-ip-list';
    private const GCORE_NETS = 'https://api.gcore.com/cdn/public-net-list';

    /**
     * Checks if the given IP matches any of the trusted proxies.
     */
    public static function isTrusted(string $ip): bool
    {
        // Always trust localhost for local connection verification
        if ($ip === '127.0.0.1' || $ip === '::1') {
             return true;
        }

        $trustedProxies = self::getTrustedProxies();

        foreach ($trustedProxies as $cidr) {
            if (self::ipInRange($ip, $cidr)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Retrieves the list of trusted proxies from Cache or fetches them.
     */
    public static function getTrustedProxies(): array
    {
        // Check Cache first
        $cached = Cache::get(self::CACHE_KEY);
        if ($cached) {
            $decoded = json_decode($cached, true);
            if (is_array($decoded)) {
                return self::mergeWithConfig($decoded);
            }
        }

        // Fetch if not cached
        return self::fetchAndCacheProxies();
    }

    private static function mergeWithConfig(array $fetchedProxies): array
    {
        $configProxies = Config::get('TRUSTED_PROXIES', '');
        if ($configProxies === '*' || empty($configProxies)) {
             return $fetchedProxies;
        }

        $manual = array_filter(array_map('trim', explode(',', $configProxies)));
        return array_unique(array_merge($fetchedProxies, $manual));
    }

    /**
     * Fetches IPs from providers and caches them.
     */
    public static function fetchAndCacheProxies(): array
    {
        $proxies = [];

        // Cloudflare (Text lists)
        $proxies = array_merge($proxies, self::fetchList(self::CLOUDFLARE_V4));
        $proxies = array_merge($proxies, self::fetchList(self::CLOUDFLARE_V6));

        // Gcore (JSON)
        $proxies = array_merge($proxies, self::fetchGcoreList(self::GCORE_IPS));
        $proxies = array_merge($proxies, self::fetchGcoreList(self::GCORE_NETS));

        // Filter and Unique
        $proxies = array_unique(array_filter($proxies, fn($ip) => !empty($ip)));

        // Fallback if empty to prevent DoS loop
        if (empty($proxies)) {
             $proxies = ['0.0.0.0'];
        }

        // Cache
        if (!empty($proxies)) {
             Cache::set(self::CACHE_KEY, json_encode(array_values($proxies)), self::CACHE_TTL);
        }

        return self::mergeWithConfig($proxies);
    }

    private static function fetchWithTimeout(string $url): ?string
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 3, // 3 seconds timeout
                'ignore_errors' => false,
            ],
            'ssl' => [
                 'verify_peer' => true,
                 'verify_peer_name' => true,
            ]
        ]);

        $content = @file_get_contents($url, false, $context);

        return $content !== false ? $content : null;
    }

    private static function fetchList(string $url): array
    {
        $content = self::fetchWithTimeout($url);
        if ($content === null) {
            return [];
        }
        // Split by newlines
        return array_filter(array_map('trim', explode("\n", $content)));
    }

    private static function fetchGcoreList(string $url): array
    {
        $content = self::fetchWithTimeout($url);
        if ($content === null) {
            return [];
        }

        $json = json_decode($content, true);
        if (is_array($json)) {
             $ips = [];

             // Gcore standard structure
             if (isset($json['addresses']) && is_array($json['addresses'])) {
                 $ips = array_merge($ips, $json['addresses']);
             }
             if (isset($json['addresses_v6']) && is_array($json['addresses_v6'])) {
                 $ips = array_merge($ips, $json['addresses_v6']);
             }

             // Other possible structures (networks, ranges)
             if (isset($json['networks']) && is_array($json['networks'])) {
                 $ips = array_merge($ips, $json['networks']);
             }
             if (isset($json['ranges']) && is_array($json['ranges'])) {
                 $ips = array_merge($ips, $json['ranges']);
             }

             // If it was just a flat list (though less likely for Gcore API)
             if (empty($ips) && array_is_list($json)) {
                 foreach ($json as $item) {
                     if (is_string($item)) {
                         $ips[] = $item;
                     }
                 }
             }

             return $ips;
        }

        // Fallback to text lines if not JSON
        return array_filter(array_map('trim', explode("\n", $content)));
    }

    /**
     * Check if an IP is in a CIDR range.
     */
    private static function ipInRange(string $ip, string $range): bool
    {
        if (strpos($range, '/') === false) {
            return $ip === $range;
        }

        [$subnet, $bits] = explode('/', $range, 2);

        // IPv4
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ipInt = ip2long($ip);
            $subnetInt = ip2long($subnet);
            $mask = -1 << (32 - (int)$bits);
            return ($ipInt & $mask) === ($subnetInt & $mask);
        }

        // IPv6
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) && filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
             $ipBin = inet_pton($ip);
             $subnetBin = inet_pton($subnet);
             $bits = (int)$bits;

             // Calculate how many bytes to compare fully
             $bytes = $bits >> 3; // $bits / 8
             $remainder = $bits & 7; // $bits % 8

             // Compare full bytes
             if (substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) {
                 return false;
             }

             // Compare remaining bits
             if ($remainder > 0) {
                 $mask = 0xff << (8 - $remainder);
                 $ipByte = ord($ipBin[$bytes]);
                 $subnetByte = ord($subnetBin[$bytes]);

                 return ($ipByte & $mask) === ($subnetByte & $mask);
             }

             return true;
        }

        return false;
    }
}
