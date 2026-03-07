<?php

declare(strict_types=1);

namespace TLC\Hook\Middleware;

class GzipMiddleware
{
    public function handle(): void
    {
        // Prevent double compression if already handled by Core or Server
        $handlers = ob_list_handlers();
        if (in_array('ob_gzhandler', $handlers)) {
            return;
        }

        $acceptEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
        if (str_contains($acceptEncoding, 'gzip')) {
            ob_start('ob_gzhandler');
        }
    }
}
