<?php

declare(strict_types=1);

namespace TLC\Core;

/**
 * Class Bootstrap
 * Developed by THE LIFE COINCOIN
 *
 * Initializes the application environment.
 */
class Bootstrap
{
    public static function init(string $rootPath): void
    {
        // 1. Load Configuration
        Config::load($rootPath);

        // 2. Set Error Reporting
        $debugMode = filter_var(Config::get('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN);

        if ($debugMode) {
            ini_set('display_errors', '1');
            ini_set('display_startup_errors', '1');
            error_reporting(E_ALL);
        } else {
            ini_set('display_errors', '0');
            ini_set('display_startup_errors', '0');
            error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
        }

        // 3. Set Timezone
        date_default_timezone_set(Config::get('APP_TIMEZONE', 'UTC'));

        // 4. Verify FIPS compliance
        $enableFipsMode = filter_var(Config::get('ENABLE_FIPS_MODE', false), FILTER_VALIDATE_BOOLEAN);
        if ($enableFipsMode) {
            $fipsActive = false;

            // FIPS mode disables MD5, testing its availability provides a robust check for FIPS
            if (openssl_digest('test', 'md5') === false) {
                $fipsActive = true;
            } elseif (file_exists('/proc/sys/crypto/fips_enabled') && trim(file_get_contents('/proc/sys/crypto/fips_enabled')) === '1') {
                $fipsActive = true;
            }

            // FIPS provider checking OpenSSL 3.0 info (fallback)
            if (!$fipsActive && function_exists('openssl_get_md_methods')) {
                $methods = openssl_get_md_methods();
                // When FIPS provider is active and strictly enforcing, weak algorithms shouldn't be loaded or operational
                if (!in_array('md5', $methods, true)) {
                    $fipsActive = true;
                }
            }

            if (!$fipsActive) {
                \TLC\Core\Logger::error("FIPS mode is enabled in config but cryptographic provider is not FIPS compliant.");
                throw new \RuntimeException("FIPS Compliance Error: FIPS mode is required but not active.");
            } else {
                \TLC\Core\Logger::info("FIPS cryptographic provider is loaded and active.");
            }
        }

        // 5. Load Game Constants
        if (class_exists(\TLC\Hook\Helpers\SettingsHelper::class)) {
            try {
                \TLC\Hook\Helpers\SettingsHelper::loadConstants();
            } catch (\Exception $e) {
                // Ignore DB error if run before migration
            }
        }
    }
}
