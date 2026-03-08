<?php

declare(strict_types=1);

/**
 * Hook System Bootstrap
 * Developed by THE LIFE COINCOIN
 */

// 1. Register Autoloader for Hooks
spl_autoload_register(function ($class) {
    $prefix = 'TLC\\Hook\\';
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
if (class_exists(\TLC\Hook\Middleware\GzipMiddleware::class)) {
    (new \TLC\Hook\Middleware\GzipMiddleware())->handle();
}

// 3. Load SettingsHelper dynamically for SQL White Label
if (class_exists(\TLC\Hook\Helpers\SettingsHelper::class)) {
    try {
        \TLC\Hook\Helpers\SettingsHelper::loadConstants();
    } catch (\Exception $e) {
        // Safe fail if DB not migrated yet
    }
}

// 4. Load Routes
$routesFile = __DIR__ . '/routes.php';
if (file_exists($routesFile)) {
    require_once $routesFile;
}
