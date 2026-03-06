<?php

declare(strict_types=1);

namespace Bastivan\UniversalApi\Controllers;

use Bastivan\UniversalApi\Core\Config;
use Bastivan\UniversalApi\Core\Docs\OpenApiGenerator;
use Bastivan\UniversalApi\Core\Router;

/**
 * Class DocsController
 * Developed by Bastivan Consulting
 *
 * Handles API documentation routes.
 */
class DocsController
{
    private OpenApiGenerator $generator;

    public function __construct(Router $router)
    {
        $this->generator = new OpenApiGenerator($router);
    }

    public function index(): void
    {
        if (Config::get('APP_DOCS_ENABLED') !== 'true') {
            http_response_code(404);
            echo "Documentation désactivée.";
            return;
        }

        $html = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>API Documentation - Bastivan Consulting</title>
    <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/swagger-ui/5.11.0/swagger-ui.css" />
    <link rel="icon" type="image/png" href="https://cdnjs.cloudflare.com/ajax/libs/swagger-ui/5.11.0/favicon-32x32.png" sizes="32x32" />
    <style>
        body { margin: 0; padding: 0; }
        .swagger-ui .topbar { background-color: #000; }
    </style>
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/swagger-ui/5.11.0/swagger-ui-bundle.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/swagger-ui/5.11.0/swagger-ui-standalone-preset.js"></script>
    <script>
    window.onload = function() {
        const ui = SwaggerUIBundle({
            url: "/docs/schema",
            dom_id: '#swagger-ui',
            presets: [
                SwaggerUIBundle.presets.apis,
                SwaggerUIStandalonePreset
            ],
            layout: "StandaloneLayout",
            language: "fr"
        });
        window.ui = ui;
    };
    </script>
</body>
</html>
HTML;
        header('Content-Type: text/html');
        echo $html;
    }

    public function schema(): void
    {
        if (Config::get('APP_DOCS_ENABLED') !== 'true') {
            http_response_code(404);
            echo json_encode(['error' => 'Documentation disabled']);
            return;
        }

        header('Content-Type: application/json');
        echo json_encode($this->generator->generate());
    }
}
