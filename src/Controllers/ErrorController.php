<?php

declare(strict_types=1);

namespace TLC\Controllers;

use TLC\Core\Config;

/**
 * Class ErrorController
 * Developed by THE LIFE COINCOIN
 *
 * Handles rendering of error pages.
 */
class ErrorController
{
    private function render(int $code, string $title, string $message, ?array $debugData = null): void
    {
        http_response_code($code);

        $isDebug = filter_var(Config::get('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
        $debug = null;

        if ($isDebug && $debugData) {
            $debug = [
                'message' => $debugData['message'] ?? 'Unknown Error',
                'file' => $debugData['file'] ?? 'Unknown File',
                'directory' => isset($debugData['file']) ? dirname($debugData['file']) : 'Unknown Directory',
            ];
        }

        // Check for Accept: application/json or if we are in API mode
        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') || str_contains($_SERVER['REQUEST_URI'] ?? '', '/')) {
            header('Content-Type: application/json');
            $response = [
                'error' => true,
                'message' => $message,
            ];

            // Never show technical debug data if not in debug mode
            if ($isDebug && $debug) {
                $response['debug'] = $debug;
            }

            echo json_encode($response);
            exit;
        }

        // Variable for the view
        $title = $title;
        $message = $message;
        $code = (string)$code;

        // Load the view
        require __DIR__ . '/../Views/error.php';
        exit;
    }

    public function notFound(array $debugInfo = []): void
    {
        $this->render(404, 'Page Non Trouvée', 'La page que vous recherchez n\'existe pas ou a été déplacée.', $debugInfo);
    }

    public function forbidden(array $debugInfo = []): void
    {
        $this->render(403, 'Accès Interdit', 'Vous n\'avez pas la permission d\'accéder à cette ressource.', $debugInfo);
    }

    public function internalServerError(array $debugInfo = []): void
    {
        $message = 'Une erreur inattendue est survenue. Nos équipes ont été notifiées.';
        $this->render(500, 'Erreur Serveur', $message, $debugInfo);
    }

    public function serviceUnavailable(array $debugInfo = []): void
    {
        $this->render(503, 'Service Indisponible', 'Le service est temporairement indisponible pour maintenance.', $debugInfo);
    }
}
