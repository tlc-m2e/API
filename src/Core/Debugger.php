<?php

declare(strict_types=1);

namespace Bastivan\UniversalApi\Core;
use Bastivan\UniversalApi\Controllers\ErrorController;

/**
 * Class Debugger
 * Developed by Bastivan Consulting
 *
 * Collects comprehensive debug information.
 */
class Debugger
{
    public static function collect(): array
    {
        // Only run if debug mode is enabled
        if (!filter_var(Config::get('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN)) {
            return [];
        }

        $headers = getallheaders();
        $bodySize = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
        //$bodySize = strlen((string)file_get_contents('php://input'));

        return [
            'bastivan_debug' => [
                'client_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'client_os' => self::getOS($_SERVER['HTTP_USER_AGENT'] ?? ''),
                'request_size_bytes' => $bodySize,
                'php_version' => PHP_VERSION,
                'memory_usage' => memory_get_usage(true),
                'status_code' => http_response_code(),
                'timestamp' => microtime(true),
            ]
        ];
    }

    private static function getOS(string $userAgent): string
    {
        if (preg_match('/linux/i', $userAgent)) return 'Linux';
        if (preg_match('/macintosh|mac os x/i', $userAgent)) return 'macOS';
        if (preg_match('/windows|win32/i', $userAgent)) return 'Windows';
        return 'Unknown';
    }

    public static function registerErrorHandler(): void
    {
        // 1. Error Handler (Warnings, Notices, etc.)
        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            // Check if error is suppressed with @
            if (!(error_reporting() & $errno)) {
                return false;
            }

            $errorData = [
                'type' => 'PHP Warning/Error',
                'message' => $errstr,
                'file' => $errfile,
                'line' => $errline
            ];

            // Log it
            Logger::log('warning', $errstr, $errorData);

            // If it's a critical error (which set_error_handler doesn't usually catch, but for completeness)
            // we might want to show the error page. But usually set_error_handler is for non-fatal.
            // However, we can convert errors to exceptions to handle them uniformly if desired.
            // For now, we log and store for debug output, but don't stop execution unless we want strict mode.

            $GLOBALS['BASTIVAN_DEBUG_ERRORS'][] = $errorData;

            // Don't execute PHP internal error handler
            return true;
        });

        // 2. Exception Handler (Uncaught Exceptions -> 500)
        set_exception_handler(function ($exception) {
            $errorData = [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ];

            Logger::log('error', $exception->getMessage(), $errorData);

            $controller = new ErrorController();
            $controller->internalServerError($errorData);
        });

        // 3. Shutdown Function (Fatal Errors -> 500)
        register_shutdown_function(function () {
            $error = error_get_last();
            if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
                $errorData = [
                    'message' => $error['message'],
                    'file' => $error['file'],
                    'line' => $error['line']
                ];

                Logger::log('critical', 'Fatal Error: ' . $error['message'], $errorData);

                // Clear any partial output
                if (ob_get_length()) {
                    ob_clean();
                }

                $controller = new ErrorController();
                $controller->internalServerError($errorData);
            }
        });
    }
}
