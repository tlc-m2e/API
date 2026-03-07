<?php

declare(strict_types=1);

namespace TLC\Core\Middleware;

/**
 * Class BrandingMiddleware
 * Developed by THE LIFE COINCOIN
 *
 * Adds commercial branding headers to the API response.
 */
class BrandingMiddleware
{
    public static function handle(): void
    {
        header('Universal-API: Universal API');
        header('X-Powered-By: THE LIFE COINCOIN');
    }
}
