# WHITE LABEL M2E API 🏃‍♂️🪙

[![CI/CD Pipeline](https://github.com/tlc-m2e/API/actions/workflows/ci.yml/badge.svg)](https://github.com/tlc-m2e/API/actions/workflows/ci.yml)

Bienvenue sur le moteur M2E "White Label", basé sur l'architecture de **THE LIFE COINCOIN**.
Ce projet a été refactorisé pour être **100% SQL (MariaDB)** et entièrement **dynamique** (Marque Blanche).

### Infrastructure Status
![MariaDB](https://img.shields.io/badge/MariaDB-003545?style=for-the-badge&logo=mariadb&logoColor=white)
![Redis](https://img.shields.io/badge/redis-%23DD0031.svg?style=for-the-badge&logo=redis&logoColor=white)
![OVH](https://img.shields.io/badge/ovh-%23123F6D.svg?style=for-the-badge&logo=ovh&logoColor=white)

Chaque aspect du jeu (noms, monnaies, règles, entités) est défini dynamiquement par une configuration en base de données MariaDB. Fini les références statiques !

Cette API est propulsée par le framework **Universal API**, garantissant performance, sécurité et scalabilité. Le principe est simple : courir et gagner de l'argent !

---

## 🚀 Fonctionnalités Principales

*   **Move-to-Earn** : Suivi des entraînements (GPS), calcul des récompenses (courir pour gagner), et mode passif.
*   **Écosystème Web3** : Gestion de Wallet "Spending" avec multiples devises (SOL, COIN, TOKEN, Seed).
*   **Gamification (Marque Blanche)** :
    *   Gestion d'équipe d'Entités (Personnages génériques).
    *   Noms de monnaies, récompenses, et cooldowns dynamiques gérés via `game_constants`.
    *   Système d'Énergie et d'Endurance.
*   **Marketplace** : Achat et vente d'actifs in-game.
*   **Sécurité Avancée** : Authentification JWT, 2FA (TOTP), OTP par Email, et Social Logins (Google, Facebook, Discord, X).
*   **Social & Amis** : Système de demande d'amis, suivi en temps réel de la course des amis, profils publics/privés.
*   **Intégration IA** : Utilisation d'IA pour analyser les entraînements et bien plus.
*   **Administration** : Outils complets pour la gestion des utilisateurs, des wallets et des constantes de jeu.
*   **Base de données SQL** : Refactorisation complète pour utiliser MariaDB via PDO.

---

## 🛠 Installation & Démarrage

### Prérequis
*   Docker & Docker Compose

### Lancement Rapide

1. **Obligation Légale (Important)** : Avant de démarrer l'API, vous devez vous renseigner auprès de l'AMF (France) ou de l'autorité compétente de votre juridiction concernant les régulations PSAN/MiCA, particulièrement pour l'utilisation des services de Swap et Marketplace. Vous devez activer `LEGAL_CONSENT=true` dans votre fichier `.env` pour confirmer que vous assumez ces responsabilités juridiques. L'API refusera de démarrer sans cela.

```bash
# Cloner le projet (Open Source)
git clone https://github.com/tlc-m2e/API
cd API

# Configurer l'environnement et le consentement légal
cp .env.example .env
# Éditer .env pour définir LEGAL_CONSENT=true

# Lancer les services (API, MariaDB, Redis)
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

### 💰 Spending Wallet & Économie (100% Non-Custodial)

Le "Spending Wallet" est le portefeuille interne du jeu. L'architecture a été mise à jour pour être **100% non-custodiale**. Le backend ne manipule ni ne stocke aucune clé privée.

*   **Sign-In With Solana (SIWS)** : Les utilisateurs lient leur Wallet (Phantom, Solflare) via une signature cryptographique d'un *nonce* unique généré par le serveur, empêchant les attaques par rejeu. (`/api/wallet/nonce` et `/api/wallet/link`).
*   **Transferts Sécurisés** : Pour créditer le Spending Wallet, le client initie une demande (`/api/transfers/attempt/init`), signe une transaction contenant un `memo` spécifique généré par le serveur, et soumet le hash (`tx_hash`) au endpoint de vérification (`/api/transfers/attempt/verify/{id}`). Le backend vérifie *on-chain* la transaction avant d'incrémenter les soldes en base de données de manière sécurisée (utilisation de Redis Mutex anti-double spending).
*   **House Wallets & Récompenses** : Les envois automatisés (récompenses) s'appuient sur OVHcloud KMS pour signer de manière isolée les transactions sans exposer la clé privée à l'application PHP.

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| `GET` | `/api/spending/getBalance` | Soldes (SOL, COIN, TOKEN, Seed) et Énergie. |
| `GET` | `/api/spending/getTickets` | Récupérer les tickets disponibles. |
| `GET` | `/api/spending/duckTeam` | Voir l'équipe de canards active (Main/Support). |
| `GET` | `/api/spending/stats` | Statistiques globales du joueur. |

### 🏃 Mécaniques de Jeu (Entités Généralisées)

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| `GET` | `/api/entities` | Liste de toutes les entités du joueur. |
| `GET` | `/api/entities/{id}` | Détails d'une entité spécifique. |
| `POST` | `/api/entities/{id}/levelup` | Monter le niveau d'une entité. |
| `GET` | `/api/eggs` | Liste des œufs/lootboxes (legacy). |
| `GET` | `/api/eggs/{id}` | Détails d'un œuf/lootbox. |

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
*   **Base de données** : MariaDB
*   **Cache** : Redis
*   **Conteneurisation** : Docker (Debian)

---

## 📄 Crédits

Développé pour **THE LIFE COINCOIN**. Open Source M2E.
Créé par **Vibe coder**.
Basé sur le framework *Universal API* de **THE LIFE COINCOIN**.

---

## ⚖️ Conformité (PSAN/MiCA) & Sécurité

### Obligation d'Enregistrement PSAN/MiCA
L'utilisation des fonctionnalités de **Swap** et de **Marketplace** (échange d'actifs numériques) au sein de cette API peut être soumise à des obligations réglementaires strictes selon votre juridiction (ex: enregistrement PSAN auprès de l'AMF en France, ou conformité MiCA au niveau européen). 
Il est de la responsabilité de l'opérateur (vous) de s'assurer de la conformité légale de son déploiement. L'activation de l'API requiert l'acceptation explicite de ces responsabilités via la variable `LEGAL_CONSENT=true`.

### Architecture Non-Custodiale : Un Atout Conformité
L'architecture de cette API est strictement **100% non-custodiale**. Le backend ne stocke, ne manipule et n'a accès à **aucune clé privée** en clair. 
Toutes les opérations d'authentification wallet (SIWS) et de validation de transferts reposent sur des preuves cryptographiques côté client et des vérifications *on-chain* publiques. Les transactions de la plateforme (House Wallets) sont isolées via des services externes sécurisés (OVHcloud KMS).
Cette absence de conservation des fonds des utilisateurs par l'API simplifie considérablement les démarches de mise en conformité réglementaire.

---

## 🔒 Sécurité et Conformité ISO 27001

L'application intègre des mesures de sécurité avancées pour répondre aux exigences de la norme ISO 27001 :

### 1. Audit Trail (Contrôle ISO A.12.4)
Toutes les actions sensibles (création, modification, suppression) effectuées sur les modèles de données sont tracées automatiquement dans la table `audit_logs`.
*   **Contenu :** Utilisateur, Action, Type de ressource, ID, Valeurs précédentes, Nouvelles valeurs, Adresse IP, User-Agent, Date.

### 2. Chiffrement des données au repos (Contrôle ISO A.10.1)
Les données personnelles identifiables (PII), telles que les adresses e-mail, sont chiffrées en base de données à l'aide de l'algorithme `AES-256-CBC`.
*   **Configuration requise :** Définir la variable d'environnement `ENCRYPTION_KEY` avec une clé secrète de 32 caractères dans votre fichier `.env`.

### 3. Contrôle d'Accès Granulaire (RBAC - Contrôle ISO A.9)
L'accès aux fonctionnalités d'administration est régi par un système de permissions basé sur les rôles (RBAC). Les tables `permissions` et `role_permissions` permettent d'attribuer des droits spécifiques (ex: `updateConfig`, `viewLogs`).

### 4. Filtrage et Masquage JSON
Toutes les sorties API sont filtrées automatiquement pour masquer les champs sensibles (`password`, `private_key`, `secret`, `internal_log`). En production (`APP_DEBUG=false`), aucune information technique n'est divulguée dans les réponses JSON d'erreur.
