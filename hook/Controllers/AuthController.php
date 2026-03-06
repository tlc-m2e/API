<?php

namespace Bastivan\UniversalApi\Hook\Controllers;

use Bastivan\UniversalApi\Hook\Models\User;
use Bastivan\UniversalApi\Hook\Services\JwtService;
use Bastivan\UniversalApi\Hook\Services\MailService;

class AuthController
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

        // Check if user exists
        if ($this->userModel->findOne(['email' => $data['email']])) {
            http_response_code(409);
            echo json_encode(['error' => 'User already exists']);
            return;
        }

        $hash = password_hash($data['password'], PASSWORD_DEFAULT);

        $userId = $this->userModel->create([
            'email' => $data['email'],
            'password' => $hash,
            'name' => $data['name'] ?? '',
            'role' => 'user'
        ]);

        http_response_code(201);
        echo json_encode(['message' => 'User created', 'id' => (string)$userId]);
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
        $userId = $_REQUEST['user_id'] ?? null;
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

        echo json_encode($user);
    }
}
