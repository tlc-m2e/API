<?php

namespace TLC\Hook\Middleware;

use TLC\Hook\Services\JwtService;

class AuthMiddleware
{
    public static function handle(): void
    {
        $headers = getallheaders();
        $auth = $headers['Authorization'] ?? '';

        if (!str_starts_with($auth, 'Bearer ')) {
            self::abort();
        }

        $token = substr($auth, 7);
        $jwtService = new JwtService();
        $payload = $jwtService->verify($token);

        if (!$payload) {
            self::abort();
        }

        // Store user info in global context or request if possible,
        // for now we rely on the controller re-verifying or extracting if needed
        // But a cleaner way is to inject it.
        // Since this is a static middleware in the hook system, we might set a global.
        $_REQUEST['user_id'] = $payload->id ?? null;
        $_REQUEST['user_email'] = $payload->email ?? null;
    }

    private static function abort()
    {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
}
