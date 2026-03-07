<?php

declare(strict_types=1);

namespace TLC\Core\Middleware;

/**
 * Class GzipMiddleware
 * Developed by THE LIFE COINCOIN
 *
 * Handles response compression.
 */
class GzipMiddleware
{
    public static function handle(): void
    {
        // Check if client supports GZIP
        $acceptEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';

        if (str_contains($acceptEncoding, 'gzip')) {
            // Start output buffering with gzip handler
            // ob_gzhandler sends the correct Content-Encoding header
            ob_start('ob_gzhandler');
        } else {
            ob_start();
        }
    }
}
