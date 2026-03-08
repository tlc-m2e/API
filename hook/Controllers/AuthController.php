<?php

namespace TLC\Hook\Controllers;

use TLC\Hook\Models\User;
use TLC\Hook\Services\JwtService;
use TLC\Hook\Services\MailService;

class AuthController extends BaseController
{
    private User $userModel;
    private JwtService $jwtService;
    private MailService $mailService;

    public function __construct()
    {
        $this->userModel = new User();
        $this->jwtService = new JwtService();
        $this->mailService = new MailService();
    }

    public function sendOtp()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['email'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Email is required']);
            return;
        }

        $user = $this->userModel->findOne(['email' => $data['email']]);

        if (!$user) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            return;
        }

        // Generate 6 digit OTP securely
        $otp = (string)random_int(100000, 999999);
        $expiresAt = new \MongoDB\BSON\UTCDateTime((time() + 600) * 1000); // 10 minutes

        // Save to user
        $this->userModel->updateOne(
            ['_id' => $user['_id']],
            ['$set' => ['otp_code' => $otp, 'otp_expires_at' => $expiresAt]]
        );

        // Send Email
        $sent = $this->mailService->sendOtp($data['email'], $otp);

        if ($sent) {
            echo json_encode(['message' => 'OTP sent']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to send OTP']);
        }
    }

    public function loginWithOtp()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['email']) || empty($data['otp'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Email and OTP are required']);
            return;
        }

        $user = $this->userModel->findOne(['email' => $data['email']]);

        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid credentials']);
            return;
        }

        // Check OTP
        $currentOtp = $user['otp_code'] ?? null;
        $expiresAt = $user['otp_expires_at'] ?? null;

        if (!$currentOtp || $currentOtp !== $data['otp']) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid OTP']);
            return;
        }

        // Check expiration
        $now = new \MongoDB\BSON\UTCDateTime();
        if ($expiresAt < $now) {
            http_response_code(401);
            echo json_encode(['error' => 'OTP expired']);
            return;
        }

        // Clear OTP
        $this->userModel->updateOne(
            ['_id' => $user['_id']],
            ['$unset' => ['otp_code' => '', 'otp_expires_at' => '']]
        );

        // Generate Token
        $token = $this->jwtService->sign([
            'id' => (string)$user['_id'],
            'email' => $user['email']
        ]);

        echo json_encode(['accessToken' => $token]);
    }

    public function register()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['email']) || empty($data['password'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Email and password are required']);
            return;
        }

        // Check if user exists by email
        if ($this->userModel->findOne(['email' => $data['email']])) {
            http_response_code(409);
            echo json_encode(['error' => 'User already exists with this email']);
            return;
        }

        // Check if user exists by pseudo if provided
        if (!empty($data['pseudo']) && $this->userModel->findOne(['pseudo' => $data['pseudo']])) {
            http_response_code(409);
            echo json_encode(['error' => 'User already exists with this pseudo']);
            return;
        }

        $hash = password_hash($data['password'], PASSWORD_DEFAULT);

        $userData = [
            'email' => $data['email'],
            'password' => $hash,
            'name' => $data['name'] ?? '',
            'first_name' => $data['first_name'] ?? '',
            'last_name' => $data['last_name'] ?? '',
            'age' => isset($data['age']) ? (int)$data['age'] : null,
            'gender' => $data['gender'] ?? '',
            'pseudo' => $data['pseudo'] ?? '',
            'is_public' => true,
            'role' => 'user'
        ];

        $userId = $this->userModel->create($userData);

        http_response_code(201);
        echo json_encode(['message' => 'User created', 'id' => (string)$userId]);
    }

    private function verifySocialToken($provider, $token)
    {
        $email = null;
        $name = '';
        $socialId = '';

        try {
            switch (strtolower($provider)) {
                case 'google':
                    // Verify Google token
                    $url = "https://oauth2.googleapis.com/tokeninfo?id_token=" . urlencode($token);
                    $response = @file_get_contents($url);
                    if ($response) {
                        $data = json_decode($response, true);
                        if (isset($data['email'])) {
                            $email = $data['email'];
                            $name = $data['name'] ?? '';
                            $socialId = $data['sub'] ?? '';
                        }
                    }
                    break;
                case 'facebook':
                    // Verify Facebook token
                    $url = "https://graph.facebook.com/me?fields=id,name,email&access_token=" . urlencode($token);
                    $response = @file_get_contents($url);
                    if ($response) {
                        $data = json_decode($response, true);
                        if (isset($data['email']) || isset($data['id'])) {
                            $email = $data['email'] ?? ($data['id'] . '@facebook.local'); // Fallback if email not shared
                            $name = $data['name'] ?? '';
                            $socialId = $data['id'] ?? '';
                        }
                    }
                    break;
                case 'discord':
                    // Verify Discord token (requires Bearer authorization header)
                    $opts = [
                        "http" => [
                            "method" => "GET",
                            "header" => "Authorization: Bearer " . $token . "\r\n"
                        ]
                    ];
                    $context = stream_context_create($opts);
                    $url = "https://discord.com/api/users/@me";
                    $response = @file_get_contents($url, false, $context);
                    if ($response) {
                        $data = json_decode($response, true);
                        if (isset($data['id'])) {
                            $email = $data['email'] ?? ($data['id'] . '@discord.local');
                            $name = $data['username'] ?? '';
                            $socialId = $data['id'] ?? '';
                        }
                    }
                    break;
                case 'x':
                case 'twitter':
                    // Verify X/Twitter token (OAuth 2.0 Bearer Token)
                    $opts = [
                        "http" => [
                            "method" => "GET",
                            "header" => "Authorization: Bearer " . $token . "\r\n"
                        ]
                    ];
                    $context = stream_context_create($opts);
                    $url = "https://api.twitter.com/2/users/me?user.fields=id,name,username";
                    $response = @file_get_contents($url, false, $context);
                    if ($response) {
                        $data = json_decode($response, true);
                        if (isset($data['data']['id'])) {
                            $userData = $data['data'];
                            $email = ($userData['username'] ?? $userData['id']) . '@twitter.local'; // X API v2 doesn't always return email easily without special permissions
                            $name = $userData['name'] ?? '';
                            $socialId = $userData['id'] ?? '';
                        }
                    }
                    break;
            }
        } catch (\Exception $e) {
            // Log error or ignore and return false
            return false;
        }

        if ($email && $socialId) {
            return [
                'email' => $email,
                'name' => $name,
                'social_id' => $socialId
            ];
        }

        return false;
    }

    public function loginWithSocial()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['provider']) || empty($data['token'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Provider and token are required']);
            return;
        }

        $provider = $data['provider']; // facebook, discord, x, google
        $token = $data['token'];

        $socialData = $this->verifySocialToken($provider, $token);

        if (!$socialData) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid or expired social token']);
            return;
        }

        $email = $socialData['email'];

        $user = $this->userModel->findOne(['email' => $email]);

        if (!$user) {
            // Auto-register the user if they don't exist

            // Generate a unique pseudo
            $basePseudo = strtolower($provider) . '_' . substr($socialData['social_id'], 0, 8);
            $pseudo = $basePseudo;
            $counter = 1;
            while ($this->userModel->findOne(['pseudo' => $pseudo])) {
                $pseudo = $basePseudo . $counter;
                $counter++;
            }

            $userData = [
                'email' => $email,
                'password' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), // Random password for social logins
                'name' => $socialData['name'],
                'pseudo' => $pseudo,
                'is_public' => true,
                'role' => 'user'
            ];
            $userId = $this->userModel->create($userData);
            $user = $this->userModel->findById((string)$userId);
        }

        $tokenStr = $this->jwtService->sign([
            'id' => (string)$user['_id'],
            'email' => $user['email']
        ]);

        echo json_encode(['accessToken' => $tokenStr, 'user' => [
            'id' => (string)$user['_id'],
            'email' => $user['email'],
            'pseudo' => $user['pseudo'] ?? '',
            'name' => $user['name'] ?? ''
        ]]);
    }

    public function login()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        $user = $this->userModel->findOne(['email' => $data['email']]);

        if (!$user || !password_verify($data['password'], $user['password'] ?? '')) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid credentials']);
            return;
        }

        $token = $this->jwtService->sign([
            'id' => (string)$user['_id'],
            'email' => $user['email']
        ]);

        echo json_encode(['accessToken' => $token]);
    }

    public function me()
    {
        $userId = $_SERVER['user_id'] ?? null;
        if (!$userId) {
            http_response_code(401);
            return;
        }

        $user = $this->userModel->findById($userId);
        unset($user['password']); // Don't send password

        // Convert BSON dates to strings if needed
        if (isset($user['created_at'])) {
            $user['created_at'] = $user['created_at']->toDateTime()->format(\DateTime::ISO8601);
        }
        if (isset($user['updated_at'])) {
            $user['updated_at'] = $user['updated_at']->toDateTime()->format(\DateTime::ISO8601);
        }

        echo json_encode($this->sanitizeOutput($user));
    }
}
