# Gestion des Utilisateurs (Auth & DB)

L'Universal API utilise **MongoDB** pour le stockage et **JWT (JSON Web Tokens)** pour l'authentification stateless.

## 1. Modèle Utilisateur (MongoDB)

Les utilisateurs sont stockés dans la collection `users`. Nous utilisons la librairie `mongodb/mongodb` et des modèles personnalisés.

**Exemple de structure User (BSON) :**
```json
{
  "_id": ObjectId("64f5a..."),
  "email": "user@example.com",
  "password": "$2y$10$...",
  "role": "user",
  "otp_code": "123456",
  "otp_expires_at": ISODate("2023-09-04T12:00:00Z"),
  "created_at": ISODate("...")
}
```

## 2. Authentification (AuthController.php)

Le contrôleur `AuthController` gère l'inscription, le login (Mot de passe ou OTP) et la génération de tokens JWT.

### Inscription (Register)

```php
public function register()
{
    $data = json_decode(file_get_contents('php://input'), true);

    // Vérification existence
    if ($this->userModel->findOne(['email' => $data['email']])) {
        http_response_code(409);
        echo json_encode(['error' => 'User already exists']);
        return;
    }

    // Hashage
    $hash = password_hash($data['password'], PASSWORD_DEFAULT);

    // Création via le Modèle
    $userId = $this->userModel->create([
        'email' => $data['email'],
        'password' => $hash,
        'name' => $data['name'] ?? '',
        'role' => 'user'
    ]);

    http_response_code(201);
    echo json_encode(['message' => 'User created', 'id' => (string)$userId]);
}
```

### Connexion avec OTP (LoginWithOtp)

Exemple de flux moderne "Passwordless" ou 2FA.

```php
public function loginWithOtp()
{
    $data = json_decode(file_get_contents('php://input'), true);
    $user = $this->userModel->findOne(['email' => $data['email']]);

    // Vérification du code et de l'expiration
    $now = new \MongoDB\BSON\UTCDateTime();
    if ($user['otp_code'] !== $data['otp'] || $user['otp_expires_at'] < $now) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid or expired OTP']);
        return;
    }

    // Génération du Token JWT
    $token = $this->jwtService->sign([
        'id' => (string)$user['_id'],
        'email' => $user['email']
    ]);

    echo json_encode(['accessToken' => $token]);
}
```

## 3. Middleware d'Authentification (AuthMiddleware)

Le middleware intercepte chaque requête, vérifie la signature du JWT et peuple `$_REQUEST['user_id']`.

**Fichier : `hook/Middleware/AuthMiddleware.php`**

```php
namespace TLC\Hook\Middleware;

use TLC\Hook\Services\JwtService;

class AuthMiddleware
{
    public static function handle(): void
    {
        $headers = getallheaders();
        $auth = $headers['Authorization'] ?? '';

        // Extraction du Bearer Token
        if (!str_starts_with($auth, 'Bearer ')) {
            self::abort();
        }
        $token = substr($auth, 7);

        // Vérification JWT
        $jwtService = new JwtService();
        $payload = $jwtService->verify($token);

        if (!$payload) {
            self::abort();
        }

        // Injection du contexte utilisateur pour les contrôleurs
        $_REQUEST['user_id'] = $payload->id ?? null;
        $_REQUEST['user_email'] = $payload->email ?? null;
    }

    private static function abort()
    {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
}
```

## 4. Utilisation dans les Contrôleurs

Une fois passé le middleware, vous pouvez accéder à l'utilisateur courant simplement.

```php
// UserController.php

public function getMeProfilePicture()
{
    // Récupéré depuis le middleware
    $userId = $_REQUEST['user_id'];

    $user = $this->userModel->findById($userId);
    // ...
}
```
