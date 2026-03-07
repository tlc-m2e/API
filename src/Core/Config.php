<?php

declare(strict_types=1);

namespace TLC\Core;

use Aws\S3\S3Client;
use Dotenv\Dotenv;

/**
 * Class Config
 * Developed by THE LIFE COINCOIN
 *
 * Handles configuration loading from .env (Local) or S3 (HA).
 */
class Config
{
    private static array $settings = [];

    public static function load(string $rootPath): void
    {
        // Load basic environment variables to determine mode
        $dotenv = Dotenv::createImmutable($rootPath);
        $dotenv->safeLoad();

        // Base config from env
        self::$settings = $_ENV;

        // HA Configuration: Load config from S3 if configured
        if (!empty($_ENV['CONFIG_S3_BUCKET']) && !empty($_ENV['CONFIG_S3_KEY'])) {
            self::loadFromS3();
        }
    }

    private static function loadFromS3(): void
    {
        try {
            $s3 = new S3Client([
                'version' => 'latest',
                'region'  => $_ENV['AWS_DEFAULT_REGION'] ?? 'us-east-1',
                'credentials' => [
                    'key'    => $_ENV['AWS_ACCESS_KEY_ID'],
                    'secret' => $_ENV['AWS_SECRET_ACCESS_KEY'],
                ]
            ]);

            $result = $s3->getObject([
                'Bucket' => $_ENV['CONFIG_S3_BUCKET'],
                'Key'    => $_ENV['CONFIG_S3_KEY']
            ]);

            $jsonConfig = (string) $result['Body'];
            $remoteConfig = json_decode($jsonConfig, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($remoteConfig)) {
                // Merge S3 config over local config
                self::$settings = array_merge(self::$settings, $remoteConfig);
            }
        } catch (\Exception $e) {
            // Fallback to local config or log error
            // In a real scenario, we might want to halt or warn
            error_log("THE LIFE COINCOIN API: Failed to load config from S3: " . $e->getMessage());
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::$settings[$key] ?? $default;
    }
}
