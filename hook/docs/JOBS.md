# Système de Jobs & Files d'Attente (Queue)

L'Universal API intègre un système de file d'attente (Queue) pour gérer les tâches asynchrones, permettant de garder l'API rapide et réactive.

## 1. Créer un Job

Un Job est une classe située dans `hook/Jobs/` qui étend `TLC\Core\Queue\Job`.

**Exemple Réel : `hook/Jobs/SendWelcomeEmail.php`**

Ce job est utilisé pour envoyer un email de bienvenue sans bloquer la requête d'inscription.

```php
<?php

declare(strict_types=1);

namespace TLC\Hook\Jobs;

use TLC\Core\Queue\Job;
use TLC\Core\Logger;

class SendWelcomeEmail extends Job
{
    private array $user;

    // Le constructeur reçoit les données nécessaires (sérialisées ensuite)
    public function __construct(array $user)
    {
        $this->user = $user;
    }

    /**
     * Logique exécutée par le Worker
     */
    public function handle(): void
    {
        Logger::info("Sending welcome email to: " . $this->user['email']);

        // Simulation de travail long (appel API externe, SMTP, etc.)
        // sleep(2);

        // Envoi réel via MailService...
        // ...

        Logger::info("Email sent successfully to: " . $this->user['email']);
    }
}
```

---

## 2. Dispatcher un Job

Pour mettre un job en file d'attente depuis un contrôleur (ex: `AuthController`), utilisez `Queue::push()`.

```php
use TLC\Core\Queue\Queue;
use TLC\Hook\Jobs\SendWelcomeEmail;

public function register()
{
    // ... Création utilisateur ...
    $newUser = ['email' => 'jean@example.com', 'name' => 'Jean'];

    // L'utilisateur reçoit sa réponse 201 Created immédiatement
    // Le mail partira quelques millisecondes plus tard via le worker
    Queue::push(new SendWelcomeEmail($newUser));

    echo json_encode(['message' => 'User created']);
}
```

---

## 3. Le Worker

Le worker est un processus PHP longue durée qui surveille Redis et dépile les jobs.

```bash
# Lancement manuel (ou via Supervisor en prod)
php bin/worker.php
```

Il désérialise la classe `SendWelcomeEmail` et appelle sa méthode `handle()`.

---

## 4. Cas d'Usage dans le Projet

*   **Emails Transactionnels** : OTP, Bienvenue, Reset Password.
*   **Blockchain** : Minting de NFTs, Vérification de transactions Solana (pour ne pas faire attendre l'utilisateur pendant la confirmation de block).
*   **Calculs Lourds** : Recalcul des statistiques de "SwarmGen" ou leaderboard.
