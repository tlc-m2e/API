<?php

namespace TLC\Hook\Controllers;

use TLC\Hook\Models\User;
use TLC\Core\Config;
use Aws\Kms\KmsClient;

class EncryptionController
{
    private User $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    private function checkAdmin()
    {
        $userId = $_REQUEST['user_id'] ?? null;
        if (!$userId) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        $user = $this->userModel->findById($userId);
        if (!$user) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            exit;
        }

        if (!isset($user['role']) || $user['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            exit;
        }
    }

    public function cypher()
    {
        $this->checkAdmin();

        $data = json_decode(file_get_contents('php://input'), true);
        $clearData = $data['data'] ?? null;

        if (!$clearData) {
            http_response_code(400);
            echo json_encode(['message' => 'Missing required fields']);
            return;
        }

        $kmsKeyArn = Config::get('AWS_KMS_KEY_ARN');
        if (!$kmsKeyArn) {
            http_response_code(500);
            echo json_encode(['error' => 'AWS_KMS_KEY_ARN is not set']);
            return;
        }

        try {
            $kmsClient = new KmsClient([
                'version' => 'latest',
                'region'  => Config::get('AWS_DEFAULT_REGION', 'us-east-1'),
                'credentials' => [
                    'key'    => Config::get('AWS_ACCESS_KEY_ID'),
                    'secret' => Config::get('AWS_SECRET_ACCESS_KEY'),
                ]
            ]);

            $result = $kmsClient->encrypt([
                'KeyId' => $kmsKeyArn,
                'Plaintext' => $clearData,
            ]);

            if (!isset($result['CiphertextBlob'])) {
                throw new \Exception('Failed to encrypt private key.');
            }

            $cypheredData = base64_encode($result['CiphertextBlob']);

            echo json_encode(['data' => $cypheredData]);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Encryption failed: ' . $e->getMessage()]);
        }
    }
}
