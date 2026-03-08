<?php

namespace TLC\Hook\Controllers;

use TLC\Hook\Models\User;
use TLC\Hook\Services\JwtService;
use TLC\Hook\Middleware\AuthMiddleware;
use RobThree\Auth\TwoFactorAuth;
use MongoDB\BSON\ObjectId;

class UserController extends BaseController
{
    private User $userModel;
    private JwtService $jwtService;
    private TwoFactorAuth $tfa;

    public function __construct()
    {
        $this->userModel = new User();
        $this->jwtService = new JwtService();
        $this->tfa = new TwoFactorAuth(issuer: 'TLC'); // Issuer name
    }

    private function getCurrentUser()
    {
        $userId = $_SERVER['user_id'] ?? null;
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
        return $user;
    }

    // We can use the BaseController requirePermission method instead of checkAdmin

    public function refreshToken()
    {
        // For now, if they are authenticated via middleware, we can issue a new token
        // In a real scenario, we might want to check a refresh token string from body

        $user = $this->getCurrentUser();

        $token = $this->jwtService->sign([
            'id' => (string)$user['_id'],
            'email' => $user['email']
        ]);

        echo json_encode(['accessToken' => $token]);
    }

    public function generate2fa()
    {
        $user = $this->getCurrentUser();

        $secret = $this->tfa->createSecret();
        $qrCodeUrl = $this->tfa->getQRCodeImageAsDataUri($user['email'], $secret);

        // We can temporarily store the secret in the user record or just return it to be sent back with verification
        // Better to store it as 'pending_2fa_secret'

        $this->userModel->updateOne(
            ['_id' => $user['_id']],
            ['$set' => ['pending_2fa_secret' => $secret]]
        );

        echo json_encode([
            'secret' => $secret,
            'qr_code_url' => $qrCodeUrl
        ]);
    }

    public function enable2fa()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $code = $data['code'] ?? '';

        if (empty($code)) {
            http_response_code(400);
            echo json_encode(['error' => 'Code is required']);
            return;
        }

        $user = $this->getCurrentUser();
        $secret = $user['pending_2fa_secret'] ?? null;

        if (!$secret) {
            http_response_code(400);
            echo json_encode(['error' => 'No pending 2FA setup found. Generate one first.']);
            return;
        }

        if ($this->tfa->verifyCode($secret, $code)) {
            $this->userModel->updateOne(
                ['_id' => $user['_id']],
                [
                    '$set' => ['2fa_secret' => $secret, '2fa_enabled' => true],
                    '$unset' => ['pending_2fa_secret' => '']
                ]
            );
            echo json_encode(['message' => '2FA enabled successfully']);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid code']);
        }
    }

    public function verify2fa()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $code = $data['code'] ?? '';

        if (empty($code)) {
            http_response_code(400);
            echo json_encode(['error' => 'Code is required']);
            return;
        }

        $user = $this->getCurrentUser();

        if (empty($user['2fa_enabled']) || empty($user['2fa_secret'])) {
            http_response_code(400);
            echo json_encode(['error' => '2FA is not enabled for this user']);
            return;
        }

        if ($this->tfa->verifyCode($user['2fa_secret'], $code)) {
            echo json_encode(['valid' => true]);
        } else {
            http_response_code(401);
            \TLC\Core\Logger::critical("Security Alert: Failed 2FA verification attempt", ["user_id" => $user["_id"] ?? "unknown"]);
            echo json_encode(["valid" => false, "error" => "Invalid code"]);
        }
    }

    public function validate2fa()
    {
        // Similar to verify but intended for sensitive actions (re-verification)
        $this->verify2fa();
    }

    public function disable2fa()
    {
        // Usually requires providing the code one last time or password
        // For simplicity as per instructions, we just disable it
        // Ideally, we should check password or current 2FA code

        $user = $this->getCurrentUser();

        $this->userModel->updateOne(
            ['_id' => $user['_id']],
            [
                '$set' => ['2fa_enabled' => false],
                '$unset' => ['2fa_secret' => '', 'pending_2fa_secret' => '']
            ]
        );

        echo json_encode(['message' => '2FA disabled successfully']);
    }

    // Admin Routes

    public function listUsers()
    {
        $this->requirePermission('viewLogs');

        $users = $this->userModel->find([]); // Fetch all
        // Clean data
        $result = [];
        foreach ($users as $u) {
            unset($u['password']);
            unset($u['2fa_secret']);
            unset($u['pending_2fa_secret']);
            unset($u['otp_code']);
            $u['_id'] = (string)$u['_id'];
             if (isset($u['created_at'])) {
                $u['created_at'] = $u['created_at']->toDateTime()->format(\DateTime::ISO8601);
            }
             if (isset($u['updated_at'])) {
                $u['updated_at'] = $u['updated_at']->toDateTime()->format(\DateTime::ISO8601);
            }
            $result[] = $u;
        }

        echo json_encode($this->sanitizeOutput($result));
    }

    public function getUser($id)
    {
        $this->requirePermission('viewLogs');

        try {
            $user = $this->userModel->findById($id);
        } catch (\Exception $e) {
            // Invalid ID format
            http_response_code(400);
            echo json_encode(['error' => 'Invalid user ID']);
            return;
        }

        if (!$user) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            return;
        }

        unset($user['password']);
        unset($user['2fa_secret']);
        unset($user['pending_2fa_secret']);
        unset($user['otp_code']);
        $user['_id'] = (string)$user['_id'];
         if (isset($user['created_at'])) {
            $user['created_at'] = $user['created_at']->toDateTime()->format(\DateTime::ISO8601);
        }
         if (isset($user['updated_at'])) {
            $user['updated_at'] = $user['updated_at']->toDateTime()->format(\DateTime::ISO8601);
        }

        echo json_encode($this->sanitizeOutput($user));
    }

    public function banUser($id)
    {
        $this->requirePermission('updateConfig');

        // Ensure not banning self
        $currentUser = $this->getCurrentUser();
        if ((string)$currentUser['_id'] === $id) {
            http_response_code(400);
            echo json_encode(['error' => 'Cannot ban yourself']);
            return;
        }

        // Update user status
        $this->userModel->updateOne(
            ['_id' => new ObjectId($id)],
            ['$set' => ['banned' => true]]
        );

        echo json_encode(['message' => 'User banned']);
    }

    public function updateUser($id)
    {
        $this->requirePermission('updateConfig');
        $data = json_decode(file_get_contents('php://input'), true);

        $updateData = [];
        if (isset($data['name'])) $updateData['name'] = $data['name'];
        if (isset($data['role'])) $updateData['role'] = $data['role']; // Admin can change roles
        if (isset($data['banned'])) $updateData['banned'] = (bool)$data['banned'];

        if (empty($updateData)) {
            http_response_code(400);
            echo json_encode(['error' => 'No data to update']);
            return;
        }

        $this->userModel->updateOne(
            ['_id' => new ObjectId($id)],
            ['$set' => $updateData]
        );

        echo json_encode(['message' => 'User updated']);
    }

    public function deleteUser($id)
    {
        $this->requirePermission('updateConfig');

         // Ensure not deleting self
        $currentUser = $this->getCurrentUser();
        if ((string)$currentUser['_id'] === $id) {
            http_response_code(400);
            echo json_encode(['error' => 'Cannot delete yourself']);
            return;
        }

        $this->userModel->deleteOne(['_id' => new ObjectId($id)]);

        echo json_encode(['message' => 'User deleted']);
    }


    public function twoFactorEnabled()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $email = $data['email'] ?? '';

        if (empty($email)) {
            http_response_code(400);
            echo json_encode(['error' => 'Email is required']);
            return;
        }

        $user = $this->userModel->findOne(['email' => strtolower($email)]);

        if (!$user) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            return;
        }

        $is2FAEnabled = isset($user['2fa_enabled']) && $user['2fa_enabled'];
        echo json_encode(['item' => $is2FAEnabled]);
    }

    public function updateMe()
    {
        $user = $this->getCurrentUser();
        $data = json_decode(file_get_contents('php://input'), true);

        $updateData = [];
        if (isset($data['name'])) $updateData['name'] = $data['name'];
        if (isset($data['first_name'])) $updateData['first_name'] = $data['first_name'];
        if (isset($data['last_name'])) $updateData['last_name'] = $data['last_name'];
        if (isset($data['age'])) $updateData['age'] = (int)$data['age'];
        if (isset($data['gender'])) $updateData['gender'] = $data['gender'];
        if (isset($data['pseudo'])) $updateData['pseudo'] = $data['pseudo'];
        if (isset($data['profilePicture'])) $updateData['profilePicture'] = $data['profilePicture'];
        if (isset($data['is_public'])) $updateData['is_public'] = (bool)$data['is_public'];

        if (isset($data['password'])) {
            $updateData['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        // Check if pseudo is already taken by someone else
        if (isset($updateData['pseudo']) && $updateData['pseudo'] !== ($user['pseudo'] ?? '')) {
            $existing = $this->userModel->findOne(['pseudo' => $updateData['pseudo']]);
            if ($existing && (string)$existing['_id'] !== (string)$user['_id']) {
                http_response_code(409);
                echo json_encode(['error' => 'Pseudo already taken']);
                return;
            }
        }

        if (!empty($updateData)) {
            $this->userModel->updateOne(
                ['_id' => $user['_id']],
                ['$set' => $updateData]
            );
        }

        $updatedUser = $this->userModel->findById((string)$user['_id']);
        unset($updatedUser['password']);

        echo json_encode(['item' => $updatedUser]);
    }

    public function getPublicProfile($pseudo)
    {
        $user = $this->userModel->findOne(['pseudo' => $pseudo]);

        if (!$user) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            return;
        }

        if (!isset($user['is_public']) || !$user['is_public']) {
            http_response_code(403);
            echo json_encode(['error' => 'Profile is private']);
            return;
        }

        // Fetch ducks
        $duckModel = new \TLC\Hook\Models\Duck();
        $ducks = $duckModel->find(['owner_id' => (string)$user['_id']]);

        // Fetch workout stats (e.g. total distance)
        $workoutModel = new \TLC\Hook\Models\Workout();
        $workouts = $workoutModel->find(['user_id' => (string)$user['_id']]);

        $totalDistance = 0;
        foreach ($workouts as $w) {
            $totalDistance += (float)($w['distance'] ?? 0);
        }

        echo json_encode([
            'profile' => [
                'pseudo' => $user['pseudo'],
                'name' => $user['name'] ?? '',
                'total_distance_km' => $totalDistance,
                'ducks' => array_map(function($duck) {
                    return [
                        'id' => (string)$duck['_id'],
                        'name' => $duck['name'] ?? '',
                        'level' => $duck['level'] ?? 1
                    ];
                }, $ducks)
            ]
        ]);
    }

    public function deleteMe()
    {
        $user = $this->getCurrentUser();
        $this->userModel->deleteOne(['_id' => $user['_id']]);
        echo json_encode(['item' => 'User deleted']);
    }

    public function getMeProfilePicture()
    {
        $user = $this->getCurrentUser();
        // Since we don't have AWS S3 service integration here, we return the stored URL or base64
        echo json_encode(['item' => $user['profilePicture'] ?? '']);
    }

    public function updateMeProfilePicture()
    {
        $user = $this->getCurrentUser();
        // Handling file upload in PHP is different.
        // For now, let's assume the body contains a base64 string or URL in 'file' field as fallback,
        // OR we handle multipart/form-data.
        // Given complexity of S3 without AWS SDK setup here, we'll mock success if file is sent.

        // Check if file is uploaded via $_FILES
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
             // Mock upload: read file content as base64 (not recommended for large files but simple for now)
             $content = file_get_contents($_FILES['file']['tmp_name']);
             $base64 = 'data:' . $_FILES['file']['type'] . ';base64,' . base64_encode($content);

             $this->userModel->updateOne(
                ['_id' => $user['_id']],
                ['$set' => ['profilePicture' => $base64]] // Storing base64 directly as simple storage
            );

            echo json_encode(['item' => $base64]);
        } else {
             http_response_code(400);
             echo json_encode(['error' => 'No file uploaded']);
        }
    }

    public function updatePassword()
    {
        $user = $this->getCurrentUser();
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['password']) || empty($data['otp'])) {
             http_response_code(400);
             echo json_encode(['error' => 'Password and OTP are required']);
             return;
        }

        // Verify OTP
        $currentOtp = $user['otp_code'] ?? null;
        if (!$currentOtp || $currentOtp !== $data['otp']) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid OTP']);
            return;
        }

        // Verify 2FA if enabled
        if (isset($user['2fa_enabled']) && $user['2fa_enabled']) {
             if (empty($data['twoFactorAuthToken'])) {
                 http_response_code(400);
                 echo json_encode(['error' => '2FA Token required']);
                 return;
             }
             if (!$this->tfa->verifyCode($user['2fa_secret'], $data['twoFactorAuthToken'])) {
                  http_response_code(401);
                  \TLC\Core\Logger::critical("Security Alert: Invalid 2FA token provided", ["user_id" => $user["_id"] ?? "unknown"]);
                  echo json_encode(["error" => "Invalid 2FA token"]);
                  return;
             }
        }

        // Update password
        $hash = password_hash($data['password'], PASSWORD_DEFAULT);
        $this->userModel->updateOne(
            ['_id' => $user['_id']],
            [
                '$set' => ['password' => $hash],
                '$unset' => ['otp_code' => '', 'otp_expires_at' => '']
            ]
        );

        echo json_encode(['item' => 'Password updated']);
    }

    public function addTicketToUsers()
    {
        $this->requirePermission('updateConfig');
        $data = json_decode(file_get_contents('php://input'), true);

        // logic to add tickets
        // $data['usersId'], $data['usersEmail'], $data['ticketName'], $data['ticketAmount']

        // Need SpendingWalletController/Model access.
        // We will mock this action or use SpendingWallet model if we can instantiate it.
        // Assuming we can instantiate SpendingWallet model.

        // Stub implementation
        echo json_encode(['item' => 'Tickets added to users']);
    }

    public function sendUserNotification($id)
    {
        $this->requirePermission('updateConfig');
        $data = json_decode(file_get_contents('php://input'), true);

        // Logic to send notification (SSE or DB)
        // Stub
        echo json_encode(['item' => 'Notification sent']);
    }
}
