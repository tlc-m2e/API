<?php

declare(strict_types=1);

namespace TLC\Core\Docs;

use TLC\Core\Router;
use TLC\Core\Config;
use ReflectionClass;
use ReflectionMethod;

/**
 * Class OpenApiGenerator
 * Developed by THE LIFE COINCOIN
 *
 * Generates OpenAPI 3.0 specification from registered routes.
 */
class OpenApiGenerator
{
    private Router $router;

    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    public function generate(): array
    {
        $routes = $this->router->getRoutes();
        $paths = [];

        foreach ($routes as $route) {
            $path = $this->normalizePath($route['path']);
            $method = strtolower($route['method']);

            if (!isset($paths[$path])) {
                $paths[$path] = [];
            }

            $paths[$path][$method] = $this->buildOperation($route);
        }

        return [
            'openapi' => '3.0.0',
            'info' => [
                'title' => Config::get('APP_NAME', 'Universal API'),
                'version' => Config::get('APP_VERSION', '1.0.0'),
                'description' => 'Documentation générée automatiquement.',
                'contact' => [
                    'name' => 'THE LIFE COINCOIN',
                    'email' => 'contact@thelifecoincoin.com'
                ]
            ],
            'servers' => [
                [
                    'url' => Config::get('APP_URL', 'http://localhost:8080'),
                    'description' => 'Serveur principal'
                ]
            ],
            'paths' => $paths,
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'JWT',
                    ]
                ]
            ]
        ];
    }

    private function normalizePath(string $path): string
    {
        // Convert Regex-like parameters if necessary, but assuming standard {param} usage in definitions.
        // If the router used regex syntax directly in definition (unlikely for user friendliness), we'd need to convert.
        // For now, assume users define routes as /users/{id}.
        return $path;
    }

    private function buildOperation(array $route): array
    {
        $operation = [
            'summary' => 'Endpoint ' . $route['path'],
            'responses' => [
                '200' => [
                    'description' => 'Succès'
                ]
            ]
        ];

        // Security check
        if (isset($route['options']['middleware']) && in_array('AuthMiddleware', $route['options']['middleware'])) {
            $operation['security'] = [['bearerAuth' => []]];
        }

        // Analyze Handler
        $handler = $route['handler'];
        if (is_array($handler)) {
            $controller = $handler[0];
            $action = $handler[1];

            try {
                $refClass = new ReflectionClass($controller);
                $refMethod = $refClass->getMethod($action);

                // Add parameters from method signature
                $params = [];

                // Extract path parameters from URI
                preg_match_all('/\{([^}]+)\}/', $route['path'], $matches);
                foreach ($matches[1] as $paramName) {
                    $params[] = [
                        'name' => $paramName,
                        'in' => 'path',
                        'required' => true,
                        'schema' => ['type' => 'string']
                    ];
                }

                if (!empty($params)) {
                    $operation['parameters'] = $params;
                }

                // If it's a POST/PUT/PATCH, assume JSON body might be needed
                // (Very basic assumption, hard to guess exact fields without DTOs)
                if (in_array($route['method'], ['POST', 'PUT', 'PATCH'])) {
                    $operation['requestBody'] = [
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'example_field' => ['type' => 'string']
                                    ]
                                ]
                            ]
                        ]
                    ];
                }

            } catch (\Exception $e) {
                // Ignore reflection errors
            }
        }

        return $operation;
    }
}
