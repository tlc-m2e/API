<?php

/**
 * Universal API Entry Point
 * Developed by THE LIFE COINCOIN
 */

use TLC\Core\Bootstrap;
use TLC\Core\Router;
use TLC\Core\Debugger;
use TLC\Core\Middleware\SecurityMiddleware;
use TLC\Core\Middleware\CorsMiddleware;
use TLC\Core\Middleware\BrandingMiddleware;
use TLC\Core\Middleware\GzipMiddleware;
use TLC\Controllers\HomeController;

require_once __DIR__ . '/../vendor/autoload.php';

// Initialize System
Bootstrap::init(__DIR__ . '/../');

// Start Error Handling / Debugging
Debugger::registerErrorHandler();

// Run Middleware
CorsMiddleware::handle();
SecurityMiddleware::handle();
BrandingMiddleware::handle();
GzipMiddleware::handle();

// Setup Routing
$router = new Router();

// Register Router in Container
TLC\Core\Container::getInstance()->instance(Router::class, $router);

$router->get('/', [HomeController::class, 'index']);
$router->get('/status', [HomeController::class, 'status']);

// Documentation Routes (Registered conditionally inside Controller but route must exist to be dispatched)
// We register them globally, logic inside controller handles the check.
$router->get('/docs', [TLC\Controllers\DocsController::class, 'index']);
$router->get('/docs/schema', [TLC\Controllers\DocsController::class, 'schema']);

// Load Custom Hooks
$hookBootstrap = __DIR__ . '/../hook/bootstrap.php';
if (file_exists($hookBootstrap)) {
    try {
        require_once $hookBootstrap;
    } catch (\Throwable $e) {
        \TLC\Core\Logger::error("Hook Bootstrap Failed: " . $e->getMessage());
        // We continue execution, allowing Core to run even if Hooks fail
    }
}

// Dispatch
$router->dispatch();
