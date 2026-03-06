<?php

declare(strict_types=1);

namespace Bastivan\UniversalApi\Core;
use Bastivan\UniversalApi\Controllers\ErrorController;

/**
 * Class Router
 * Developed by Bastivan Consulting
 *
 * Simple Regex Router.
 */
class Router
{
    private array $routes = [];

    public function get(string $path, callable|array $handler, array $options = []): void
    {
        $this->add('GET', $path, $handler, $options);
    }

    public function post(string $path, callable|array $handler, array $options = []): void
    {
        $this->add('POST', $path, $handler, $options);
    }

    public function put(string $path, callable|array $handler, array $options = []): void
    {
        $this->add('PUT', $path, $handler, $options);
    }

    public function delete(string $path, callable|array $handler, array $options = []): void
    {
        $this->add('DELETE', $path, $handler, $options);
    }

    public function patch(string $path, callable|array $handler, array $options = []): void
    {
        $this->add('PATCH', $path, $handler, $options);
    }

    public function match(array $methods, string $path, callable|array $handler, array $options = []): void
    {
        foreach ($methods as $method) {
            $this->add(strtoupper($method), $path, $handler, $options);
        }
    }

    private function add(string $method, string $path, callable|array $handler, array $options = []): void
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'pattern' => '#^' . $path . '$#',
            'handler' => $handler,
            'options' => $options
        ];
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }

    public function dispatch(): void
    {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $method = $_SERVER['REQUEST_METHOD'];

        foreach ($this->routes as $route) {
            if ($route['method'] === $method && preg_match($route['pattern'], $uri, $matches)) {
                array_shift($matches); // Remove full match

                // Security Check
                if (isset($route['options']['security'])) {
                    Middleware\SecurityMiddleware::checkRouteSecurity($route['options']['security']);
                }

                // Rate Limit
                Middleware\RateLimitMiddleware::handle($uri);

                // Custom Middleware
                if (isset($route['options']['middleware']) && is_array($route['options']['middleware'])) {
                    foreach ($route['options']['middleware'] as $middleware) {
                        if (class_exists($middleware) && method_exists($middleware, 'handle')) {
                            $middleware::handle($uri);
                        }
                    }
                }

                // Check Cache
                $cacheEnabled = $route['options']['cache'] ?? false;
                $cacheTTL = (int)($route['options']['cache_ttl'] ?? 3600);
                $cacheKey = 'route_cache:' . md5($method . $uri);

                if ($cacheEnabled) {
                    try {
                        $cachedData = Cache::get($cacheKey);
                        if ($cachedData !== null) {
                            $response = json_decode($cachedData, true);
                            if (json_last_error() === JSON_ERROR_NONE && isset($response['body'])) {
                                // Replay Status Code
                                if (isset($response['code'])) {
                                    http_response_code($response['code']);
                                }
                                // Replay Headers
                                if (isset($response['headers']) && is_array($response['headers'])) {
                                    foreach ($response['headers'] as $header) {
                                        header($header);
                                    }
                                }
                                echo $response['body'];
                                return;
                            }
                        }
                    } catch (\Exception $e) {
                        // Proceed without cache on error
                    }
                }

                if ($cacheEnabled) {
                    ob_start();
                }

                try {
                    $handler = $route['handler'];
                    if (is_array($handler)) {
                        $controllerName = $handler[0];
                        $action = $handler[1];
                        $controller = Container::getInstance()->resolve($controllerName);
                        call_user_func_array([$controller, $action], $matches);
                    } else {
                        call_user_func_array($handler, $matches);
                    }
                } catch (\Throwable $e) {
                    if ($cacheEnabled) {
                        ob_end_clean(); // Discard buffer on error
                    }

                    // Log error
                    Logger::error("Route Execution Failed: " . $e->getMessage(), [
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'uri' => $uri
                    ]);

                    // Use ErrorController to render a proper 500 response
                    $errorController = Container::getInstance()->resolve(ErrorController::class);
                    $errorController->internalServerError([
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine()
                    ]);
                }

                if ($cacheEnabled) {
                    $content = ob_get_clean();
                    if ($content !== false) {
                        try {
                            $headers = headers_list();
                            $code = http_response_code();

                            $cachePayload = json_encode([
                                'code' => $code,
                                'headers' => $headers,
                                'body' => $content
                            ]);

                            if ($cachePayload !== false) {
                                Cache::set($cacheKey, $cachePayload, $cacheTTL);
                            }
                        } catch (\Exception $e) {
                            // Ignore cache write errors
                        }
                        echo $content;
                    }
                }

                return;
            }
        }

        // 404
        $controller = Container::getInstance()->resolve(ErrorController::class);
        $controller->notFound(['message' => "Route not found: $uri"]);
    }
}
