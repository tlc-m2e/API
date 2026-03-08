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

        // 4. Load Game Constants
        if (class_exists(\TLC\Hook\Helpers\SettingsHelper::class)) {
            try {
                \TLC\Hook\Helpers\SettingsHelper::loadConstants();
            } catch (\Exception $e) {
                // Ignore DB error if run before migration
            }
        }
    }
}
