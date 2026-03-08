<?php

namespace TLC\Hook\Helpers;

use TLC\Hook\Models\GameConstant;
use TLC\Hook\Helpers\RedisHelper;

class SettingsHelper
{
    private static array $constants = [];
    private static bool $loaded = false;

    public static function loadConstants(): void
    {
        if (self::$loaded) {
            return;
        }

        $client = RedisHelper::getClient();
        $cacheKey = 'game_constants_all_internal';
        $cached = $client->get($cacheKey);

        if ($cached) {
            self::$constants = json_decode($cached, true);
            self::$loaded = true;
            return;
        }

        $model = new GameConstant();
        // Custom query to bypass BaseModel limit if needed, but find() works.
        // Game constants table usually holds fewer than 100 rows.
        $rows = $model->find([]);

        foreach ($rows as $row) {
            if (isset($row['key']) && isset($row['value'])) {
                self::$constants[$row['key']] = $row['value'];
            }
        }

        $client->setex($cacheKey, 3600, json_encode(self::$constants)); // Cache for 1 hour
        self::$loaded = true;
    }

    public static function getConstant(string $key, $default = null)
    {
        self::loadConstants();
        return self::$constants[$key] ?? $default;
    }

    /**
     * Translates a string by replacing {tokens} with actual game constants.
     * e.g., t("Your {entity} leveled up!") -> "Your Runner leveled up!"
     */
    public static function t(string $text, array $replacements = []): string
    {
        self::loadConstants();

        // Build default replacements from game constants
        $defaultReplacements = [
            '{entity}' => self::getConstant('ENTITY_NAME_SINGULAR', 'Entity'),
            '{entities}' => self::getConstant('ENTITY_NAME_PLURAL', 'Entities'),
            '{currency_1}' => self::getConstant('CURRENCY_1_NAME', 'SOL'),
            '{currency_2}' => self::getConstant('CURRENCY_2_NAME', 'COIN'),
            '{currency_3}' => self::getConstant('CURRENCY_3_NAME', 'REWARD'),
            '{app_name}' => self::getConstant('APP_NAME', 'TLC M2E'),
        ];

        $mergedReplacements = array_merge($defaultReplacements, $replacements);

        return strtr($text, $mergedReplacements);
    }
}
