<?php

declare(strict_types=1);

namespace TLC\Core\Middleware;

use TLC\Core\Cache;

/**
 * Class CacheMiddleware
 * Developed by THE LIFE COINCOIN
 *
 * Handles Route Caching.
 */
class CacheMiddleware
{
    /**
     * Generates a cache key for the current request.
     */
    public static function getKey(string $uri): string
    {
        // Include query string in cache key to differentiate requests
        $fullUri = $uri;
        if (!empty($_SERVER['QUERY_STRING'])) {
            $fullUri .= '?' . $_SERVER['QUERY_STRING'];
        }

        // Fix: Include Authorization header in cache key to prevent data leak between users
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $fullUri .= '|' . $_SERVER['HTTP_AUTHORIZATION'];
        }

        // Fix: Include Cookie header to handle session-based auth
        if (isset($_SERVER['HTTP_COOKIE'])) {
            $fullUri .= '|' . $_SERVER['HTTP_COOKIE'];
        }

        return 'route_cache:' . md5($fullUri);
    }

    /**
     * Checks if a route response is cached.
     *
     * @param string $uri
     * @return array|null Returns ['content' => string, 'headers' => array, 'statusCode' => int] or null
     */
    public static function check(string $uri): ?array
    {
        $key = self::getKey($uri);
        $data = Cache::get($key);

        if ($data) {
            return json_decode($data, true);
        }

        return null;
    }

    /**
     * Stores a route response in cache.
     *
     * @param string $uri
     * @param string $content
     * @param int $ttl
     */
    public static function store(string $uri, string $content, int $ttl): void
    {
        $key = self::getKey($uri);

        // Capture headers but filter out sensitive ones like Set-Cookie
        $headers = [];
        foreach (headers_list() as $header) {
            if (stripos($header, 'Set-Cookie:') === 0) {
                continue;
            }
            $headers[] = $header;
        }

        $statusCode = http_response_code();

        $data = json_encode([
            'content' => $content,
            'headers' => $headers,
            'statusCode' => $statusCode
        ]);

        Cache::set($key, $data, $ttl);
    }
}
