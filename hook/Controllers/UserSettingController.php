<?php

namespace Bastivan\UniversalApi\Hook\Controllers;

use Bastivan\UniversalApi\Hook\Helpers\UserSettingHelper;
use Bastivan\UniversalApi\Hook\Models\UserSetting;
use MongoDB\BSON\ObjectId;

class UserSettingController
{
    private UserSetting $userSettingModel;

    public function __construct()
    {
        $this->userSettingModel = new UserSetting();
    }

    public function getAllSettings()
    {
        $userId = new ObjectId($_REQUEST['user_id']);
        $settingsInDb = $this->userSettingModel->find(['user_id' => $userId]);
        $defaultSettings = UserSettingHelper::getDefinitions();

        $settings = [];

        foreach ($defaultSettings as $defaultSetting) {
            $existingSetting = null;
            foreach ($settingsInDb as $dbSetting) {
                if ($dbSetting->setting_key === $defaultSetting['name']) {
                    $existingSetting = $dbSetting;
                    break;
                }
            }

            if ($existingSetting) {
                unset($existingSetting->user_id);
                $settings[] = $existingSetting;
            } else {
                $settings[] = [
                    'setting_key' => $defaultSetting['name'],
                    'setting_value' => $defaultSetting['defaultValue'],
                ];
            }
        }

        echo json_encode($settings);
    }

    public function getSetting($settingKey)
    {
        $userId = new ObjectId($_REQUEST['user_id']);
        $settingInDb = $this->userSettingModel->findOne([
            'user_id' => $userId,
            'setting_key' => $settingKey
        ]);

        $defaultSettings = UserSettingHelper::getDefinitions();
        $defaultSetting = null;
        foreach ($defaultSettings as $ds) {
            if ($ds['name'] === $settingKey) {
                $defaultSetting = $ds;
                break;
            }
        }

        if (!$defaultSetting) {
            http_response_code(404);
            echo json_encode(['error' => 'Setting definition not found']);
            return;
        }

        if ($settingInDb) {
            unset($settingInDb->user_id);
            echo json_encode($settingInDb);
        } else {
            echo json_encode([
                'setting_key' => $defaultSetting['name'],
                'setting_value' => $defaultSetting['defaultValue'],
            ]);
        }
    }

    public function updateSetting($settingKey)
    {
        $userId = new ObjectId($_REQUEST['user_id']);
        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['value'])) {
             // Try 'setting_value' as well, just in case
             if (!isset($input['setting_value'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Value is required']);
                return;
             }
             $value = $input['setting_value'];
        } else {
             $value = $input['value'];
        }

        // Validate if setting key exists in definitions
        $defaultSettings = UserSettingHelper::getDefinitions();
        $isValidKey = false;
        foreach ($defaultSettings as $ds) {
            if ($ds['name'] === $settingKey) {
                $isValidKey = true;
                break;
            }
        }

        if (!$isValidKey) {
             http_response_code(400);
             echo json_encode(['error' => 'Invalid setting key']);
             return;
        }

        $existing = $this->userSettingModel->findOne([
            'user_id' => $userId,
            'setting_key' => $settingKey
        ]);

        if ($existing) {
            $this->userSettingModel->updateOne(
                ['_id' => $existing->_id],
                ['$set' => ['setting_value' => $value]]
            );
        } else {
            $this->userSettingModel->create([
                'user_id' => $userId,
                'setting_key' => $settingKey,
                'setting_value' => $value
            ]);
        }

        // Return the updated/created setting
        $updated = $this->userSettingModel->findOne([
            'user_id' => $userId,
            'setting_key' => $settingKey
        ]);

        unset($updated->user_id);
        echo json_encode($updated);
    }
}
