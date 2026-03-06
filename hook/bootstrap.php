<?php

declare(strict_types=1);

/**
 * Hook System Bootstrap
 * Developed by Bastivan Consulting
 */

// 1. Register Autoloader for Hooks
spl_autoload_register(function ($class) {
    $prefix = 'Bastivan\\UniversalApi\\Hook\\';
    $baseDir = __DIR__ . '/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// 2. Register Gzip Middleware
if (class_exists(\Bastivan\UniversalApi\Hook\Middleware\GzipMiddleware::class)) {
    (new \Bastivan\UniversalApi\Hook\Middleware\GzipMiddleware())->handle();
}

// 3. Load Routes
$routesFile = __DIR__ . '/routes.php';
if (file_exists($routesFile)) {
    require_once $routesFile;
}
