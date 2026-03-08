<?php

declare(strict_types=1);

namespace TLC\Core;

use Monolog\Logger as MonoLogger;
use Monolog\Handler\StreamHandler;
use Aws\S3\S3Client;

/**
 * Class Logger
 * Developed by THE LIFE COINCOIN
 */
class Logger
{
    private static ?MonoLogger $logger = null;

    public static function init(): void
    {
        if (self::$logger === null) {
            self::$logger = new MonoLogger('tlc_api');

            $logDriver = Config::get('LOG_DRIVER', 'local');

            if ($logDriver === 'local') {
                $path = __DIR__ . '/../../logs/app.log';
                // Ensure directory exists
                if (!is_dir(dirname($path))) {
                    mkdir(dirname($path), 0777, true);
                }
                self::$logger->pushHandler(new StreamHandler($path, MonoLogger::DEBUG));
                // Also push to stdout for Docker/CLI
                self::$logger->pushHandler(new StreamHandler('php://stdout', MonoLogger::DEBUG));
            } elseif ($logDriver === 's3') {
                $client = new S3Client([
                    'version' => 'latest',
                    'region'  => Config::get('AWS_DEFAULT_REGION'),
                    'credentials' => [
                        'key'    => Config::get('AWS_ACCESS_KEY_ID'),
                        'secret' => Config::get('AWS_SECRET_ACCESS_KEY'),
                    ]
                ]);
                $client->registerStreamWrapper();

                $bucket = Config::get('LOG_S3_BUCKET');
                $key = 'logs/app-' . date('Y-m-d') . '.log';
                $path = "s3://{$bucket}/{$key}";

                self::$logger->pushHandler(new StreamHandler($path, MonoLogger::DEBUG));
            }
        }
    }

    public static function log(string $level, string $message, array $context = []): void
    {
        if (self::$logger === null) self::init();
        self::$logger->log($level, $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::log('info', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log('error', $message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        self::log('debug', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::log('warning', $message, $context);
    }

    public static function critical(string $message, array $context = []): void
    {
        self::log('critical', $message, $context);
    }
}
