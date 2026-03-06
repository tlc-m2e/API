<?php

/**
 * Universal API Entry Point
 * Developed by Bastivan Consulting
 */

use Bastivan\UniversalApi\Core\Bootstrap;
use Bastivan\UniversalApi\Core\Router;
use Bastivan\UniversalApi\Core\Debugger;
use Bastivan\UniversalApi\Core\Middleware\SecurityMiddleware;
use Bastivan\UniversalApi\Core\Middleware\CorsMiddleware;
use Bastivan\UniversalApi\Core\Middleware\BrandingMiddleware;
use Bastivan\UniversalApi\Core\Middleware\GzipMiddleware;
use Bastivan\UniversalApi\Controllers\HomeController;

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
Bastivan\UniversalApi\Core\Container::getInstance()->instance(Router::class, $router);

$router->get('/', [HomeController::class, 'index']);
$router->get('/status', [HomeController::class, 'status']);

// Documentation Routes (Registered conditionally inside Controller but route must exist to be dispatched)
// We register them globally, logic inside controller handles the check.
$router->get('/docs', [Bastivan\UniversalApi\Controllers\DocsController::class, 'index']);
$router->get('/docs/schema', [Bastivan\UniversalApi\Controllers\DocsController::class, 'schema']);

// Load Custom Hooks
$hookBootstrap = __DIR__ . '/../hook/bootstrap.php';
if (file_exists($hookBootstrap)) {
    try {
        require_once $hookBootstrap;
    } catch (\Throwable $e) {
        \Bastivan\UniversalApi\Core\Logger::error("Hook Bootstrap Failed: " . $e->getMessage());
        // We continue execution, allowing Core to run even if Hooks fail
    }
}

// Dispatch
$router->dispatch();
