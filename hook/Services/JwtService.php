<?php

namespace Bastivan\UniversalApi\Hook\Services;

use Bastivan\UniversalApi\Core\Config;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtService
{
    private string $secret;
    private string $algo = 'HS256';

    public function __construct()
    {
        $secret = Config::get('JWT_SECRET');

        if (empty($secret)) {
            if (Config::get('APP_ENV') === 'production') {
                throw new \RuntimeException('JWT_SECRET environment variable is not set. Secure signing is required in production.');
            }
            $secret = 'default_secret_key_must_be_at_least_32_bytes_long_for_hs256';
        }

        $this->secret = $secret;
    }

    public function sign(array $payload, int $expiresInSeconds = 3600): string
    {
        $payload['exp'] = time() + $expiresInSeconds;
        $payload['iat'] = time();
        return JWT::encode($payload, $this->secret, $this->algo);
    }

    public function verify(string $token): ?object
    {
        try {
            return JWT::decode($token, new Key($this->secret, $this->algo));
        } catch (\Exception $e) {
            return null;
        }
    }
}
