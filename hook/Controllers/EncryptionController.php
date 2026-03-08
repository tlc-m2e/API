<?php

namespace TLC\Hook\Controllers;

use TLC\Hook\Models\User;
use TLC\Core\Config;

class EncryptionController extends BaseController
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

        $kmsKeyId = Config::get('OVH_KMS_KEY_ID');
        if (!$kmsKeyId) {
            http_response_code(500);
            echo json_encode(['error' => 'OVH_KMS_KEY_ID is not set']);
            return;
        }

        try {
            // OVH KMS Encryption using REST API
            $endpoint = Config::get('OVH_KMS_ENDPOINT');
            $url = rtrim($endpoint, '/') . "/api/v2/kms/keys/{$kmsKeyId}/operations/encrypt";

            $payload = json_encode([
                'plaintext' => base64_encode($clearData)
            ]);

            $certPath = Config::get('OVH_KMS_CERT_PATH');
            $keyPath = Config::get('OVH_KMS_KEY_PATH');

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json'
            ]);

            if ($certPath && file_exists($certPath)) {
                curl_setopt($ch, CURLOPT_SSLCERT, $certPath);
            }
            if ($keyPath && file_exists($keyPath)) {
                curl_setopt($ch, CURLOPT_SSLKEY, $keyPath);
            }

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                throw new \Exception("cURL Error: " . $error);
            }

            if ($httpCode >= 400) {
                throw new \Exception("HTTP Error " . $httpCode . ": " . $response);
            }

            $result = json_decode($response, true);

            if (!isset($result['ciphertext'])) {
                throw new \Exception('Failed to encrypt private key.');
            }

            $cypheredData = $result['ciphertext'];

            echo json_encode(['data' => $cypheredData]);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Encryption failed: ' . $e->getMessage()]);
        }
    }
}
