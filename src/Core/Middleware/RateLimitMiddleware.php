<?php

declare(strict_types=1);

namespace TLC\Core\Middleware;

use TLC\Core\Cache;
use TLC\Core\Config;

/**
 * Class RateLimitMiddleware
 * Developed by THE LIFE COINCOIN
 *
 * Simple Redis-based Rate Limiter.
 */
class RateLimitMiddleware
{
    public static function handle(string $route): void
    {
        $limit = (int)Config::get('RATE_LIMIT_MAX', 100); // Requests
        $window = (int)Config::get('RATE_LIMIT_WINDOW', 60); // Seconds

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = "rate_limit:{$ip}:{$route}";

        // Atomic Increment
        $client = Cache::getClient();
        $newVal = $client->incr($key);

        if ($newVal === 1) {
            $client->expire($key, $window);
        }

        if ($newVal > $limit) {
            http_response_code(429);
            header('Retry-After: ' . $window);
            echo json_encode([
                'error' => 'Too Many Requests',
                'message' => 'THE LIFE COINCOIN API: Rate limit exceeded.'
            ]);
            exit;
        }

        // Headers
        header('X-RateLimit-Limit: ' . $limit);
        header('X-RateLimit-Remaining: ' . ($limit - $newVal));
    }
}
