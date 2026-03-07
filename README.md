# THE LIFE COINCOIN API 🦆🪙

Bienvenue sur l'API officielle de **THE LIFE COINCOIN**, une application "Move-to-Earn" gamifiée intégrée, clé en main pour que n'importe qui puisse créer son M2E.

Cette API est propulsée par le framework **Universal API** de THE LIFE COINCOIN et modifiée par **Vibe coder**, garantissant performance, sécurité et scalabilité. Le principe est simple : courir et gagner de l'argent !

---

## 🚀 Fonctionnalités Principales

*   **Move-to-Earn** : Suivi des entraînements (GPS), calcul des récompenses (courir pour gagner), et mode passif.
*   **Écosystème Web3** : Gestion de Wallet "Spending" avec multiples devises (SOL, COIN, TOKEN, Seed).
*   **Gamification** :
    *   Gestion d'équipe de Canards (Main & Support).
    *   Élevage d'Oeufs.
    *   Système d'Énergie et d'Endurance.
*   **Marketplace** : Achat et vente d'actifs in-game.
*   **Sécurité Avancée** : Authentification JWT, 2FA (TOTP), OTP par Email, et Social Logins (Google, Facebook, Discord, X).
*   **Social & Amis** : Système de demande d'amis, suivi en temps réel de la course des amis, profils publics/privés.
*   **Intégration IA** : Utilisation d'IA pour analyser les entraînements et bien plus.
*   **Administration** : Outils complets pour la gestion des utilisateurs, des wallets et des constantes de jeu.
*   **Base de données Agnostique** : Compatible MongoDB et MariaDB.

---

## 🛠 Installation & Démarrage

### Prérequis
*   Docker & Docker Compose

### Lancement Rapide

```bash
# Cloner le projet (Open Source)
git clone https://github.com/tlc-m2e/API
cd API

# Lancer les services (API, MongoDB/MariaDB, Redis)
docker-compose up -d --build
```

L'API sera accessible sur `http://localhost:8080`.
Une interface démo web est disponible à la racine (public/index.html).

---

## 📚 Documentation API

### 🔐 Authentification & Sécurité

La sécurité est au cœur de l'application. Toutes les routes protégées nécessitent un Bearer Token JWT.

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| `POST` | `/api/auth/register` | Création d'un nouveau compte joueur. |
| `POST` | `/api/auth/login` | Connexion (email/password). Retourne le token JWT. |
| `POST` | `/api/auth/loginWithSocial` | Connexion via token OAuth (Google, Facebook, Discord, X). |
| `POST` | `/api/auth/send-otp` | Envoi d'un code OTP par email. |
| `POST` | `/api/auth/loginWithOtp` | Connexion via code OTP. |
| `GET` | `/api/user/me` | Récupérer les informations du joueur connecté. |
| `POST` | `/api/users/refresh-token` | Rafraîchir le token d'accès. |

**Double Authentification (2FA)** :
| Méthode | Endpoint | Description |
|---------|----------|-------------|
| `POST` | `/api/users/2fa/generate` | Générer un secret TOTP (QR Code). |
| `POST` | `/api/users/2fa/enable` | Activer le 2FA. |
| `POST` | `/api/users/2fa/verify` | Vérifier un code 2FA. |
| `POST` | `/api/users/2fa/validate` | Valider une session 2FA. |
| `POST` | `/api/users/2fa/disable` | Désactiver le 2FA. |

### 🤝 Social & Amis

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| `GET` | `/api/users/profile/{pseudo}` | Voir le profil public d'un joueur. |
| `POST` | `/api/friends/request` | Envoyer une demande d'ami. |
| `POST` | `/api/friends/respond` | Accepter ou refuser une demande. |
| `GET` | `/api/friends/` | Liste des amis. |
| `GET` | `/api/friends/running` | Voir les amis actuellement en train de courir. |

### 🏃 Fitness & Workouts (Move-to-Earn)

Gestion des sessions de sport et des gains associés. Courez pour gagner !

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| `POST` | `/api/workout/init` | Démarrer une nouvelle session d'entraînement. |
| `POST` | `/api/workout/location` | Envoyer des points GPS (ping régulier). |
| `POST` | `/api/workout/finish` | Terminer l'entraînement et calculer les gains. |
| `GET` | `/api/workout/` | Historique des entraînements. |
| `GET` | `/api/workout/hasWorkout` | Vérifier si un entraînement est en cours. |
| `GET` | `/api/workout/restore` | Restaurer un entraînement interrompu. |
| `GET` | `/api/workout/passive/estimate` | Estimer les gains passifs. |
| `POST` | `/api/workout/passive/execute` | Réclamer les gains passifs. |

### 💰 Spending Wallet & Économie

Le "Spending Wallet" est le portefeuille interne du jeu.

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| `GET` | `/api/spending/getBalance` | Soldes (SOL, COIN, TOKEN, Seed) et Énergie. |
| `GET` | `/api/spending/getTickets` | Récupérer les tickets disponibles. |
| `GET` | `/api/spending/duckTeam` | Voir l'équipe de canards active (Main/Support). |
| `GET` | `/api/spending/stats` | Statistiques globales du joueur. |

### 🦆 Mécaniques de Jeu (Canards & Oeufs)

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| `GET` | `/api/ducks` | Liste de tous les canards du joueur. |
| `GET` | `/api/ducks/{id}` | Détails d'un canard spécifique. |
| `POST` | `/api/ducks/{id}/levelup` | Monter le niveau d'un canard. |
| `GET` | `/api/eggs` | Liste des oeufs. |
| `GET` | `/api/eggs/{id}` | Détails d'un oeuf. |

### ⚡ Énergie & Stats

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| `GET` | `/energy` | Niveau actuel d'énergie. |
| `POST` | `/energy/refill` | Recharger l'énergie (consomme des ressources). |
| `GET` | `/stats/entity/{type}` | Obtenir les stats d'une entité (ex: Duck, Item). |

### 🏪 Marketplace

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| `GET` | `/api/marketplace/listings` | Voir les objets en vente. |
| `POST` | `/api/marketplace/listings` | Créer une annonce de vente. |
| `GET` | `/api/marketplace/pots` | Consulter les pots disponibles. |

### ⚙️ Constantes de Jeu

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| `GET` | `/gameConstants/public/` | Liste des constantes publiques. |
| `POST` | `/gameConstants/public/{key}` | Récupérer une constante spécifique. |

---

## 👮 Zone Admin

Outils réservés aux administrateurs pour la modération et le réglage du jeu.

### Gestion Utilisateurs
*   `GET /api/users/admin` : Liste des utilisateurs.
*   `GET /api/users/admin/{id}` : Détails d'un utilisateur.
*   `PUT /api/users/admin/{id}` : Modifier un utilisateur.
*   `POST /api/users/admin/ban/{id}` : Bannir un utilisateur.
*   `DELETE /api/users/admin/{id}` : Supprimer (Soft delete).

### Gestion Spending & Économie
*   `GET /api/spending/admin` : Liste des wallets.
*   `POST /api/spending/admin/burnWallet` : Brûler des tokens d'un wallet.
*   `POST /api/spending/admin/setMaxEndurance/{id}` : Définir l'endurance max.

### Notifications
*   `GET /notifications/onlineUsers` : Voir les utilisateurs en ligne.
*   `POST /notifications/notify/users` : Envoyer une notif ciblée.
*   `POST /notifications/notify/groups` : Envoyer une notif de groupe.

---

## 🏗 Architecture Technique

Le projet repose sur une architecture **MVC Custom** située dans le dossier `hook/` :
*   **Controllers** : Logique métier et points d'entrée (`hook/Controllers`).
*   **Models** : Interaction avec MongoDB (`hook/Models`).
*   **Routes** : Définition des endpoints (`hook/routes.php`).

### Technologies
*   **Langage** : PHP 8.4
*   **Base de données** : MongoDB, MariaDB
*   **Cache** : Redis
*   **Conteneurisation** : Docker (Alpine Linux)

---

## 📄 Crédits

Développé pour **THE LIFE COINCOIN**. Open Source M2E.
Créé par **Vibe coder**.
Basé sur le framework *Universal API* de **THE LIFE COINCOIN**.
