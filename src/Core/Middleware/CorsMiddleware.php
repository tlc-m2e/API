<?php

declare(strict_types=1);

namespace TLC\Core\Middleware;

use TLC\Core\Config;

/**
 * Class CorsMiddleware
 * Developed by THE LIFE COINCOIN
 *
 * Handles Cross-Origin Resource Sharing (CORS) headers and Preflight requests.
 */
class CorsMiddleware
{
    /**
     * Handle the incoming request.
     */
    public static function handle(): void
    {
        // 1. Get Allowed Origins from Config
        $allowedOriginsRaw = Config::get('CORS_ALLOWED_ORIGINS', '');
        $allowedOrigins = array_filter(array_map('trim', explode(',', $allowedOriginsRaw)));

        // 2. Identify Request Origin
        $requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';

        // 3. Validate Origin
        // We only set CORS headers if the origin is explicitly allowed.
        // We do NOT use wildcard '*' for sensitive APIs.
        if (!empty($requestOrigin) && in_array($requestOrigin, $allowedOrigins, true)) {
            header("Access-Control-Allow-Origin: $requestOrigin");
            header("Access-Control-Allow-Credentials: true");
            header("Access-Control-Max-Age: 86400"); // Cache preflight for 24h
        }

        // 4. Handle Preflight (OPTIONS) Requests
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            if (!empty($requestOrigin) && in_array($requestOrigin, $allowedOrigins, true)) {
                // Allow all common methods or restrict if needed.
                // Usually for CORS, we allow the methods the API supports.
                header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS");

                // Allow common headers
                if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
                    header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
                } else {
                    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
                }

                // Return 200 OK immediately and exit
                http_response_code(200);
                exit;
            } else {
                // If origin is not allowed, we can either ignore it (let it fail) or explicitly fail.
                // Standard behavior is just not to send the A-C-A-O header, which causes browser to fail.
                // We will just exit to prevent further processing for OPTIONS on unapproved origins.
                http_response_code(200); // Or 403, but 200 without headers is usually enough to fail CORS.
                exit;
            }
        }
    }
}
