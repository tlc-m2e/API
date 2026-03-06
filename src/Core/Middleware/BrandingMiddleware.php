<?php

declare(strict_types=1);

namespace Bastivan\UniversalApi\Core\Middleware;

/**
 * Class BrandingMiddleware
 * Developed by Bastivan Consulting
 *
 * Adds commercial branding headers to the API response.
 */
class BrandingMiddleware
{
    public static function handle(): void
    {
        header('Universal-API: Universal API');
        header('X-Powered-By: Bastivan Consulting');
    }
}
