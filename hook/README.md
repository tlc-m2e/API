# Documentation des Hooks - Universal API

Le dossier `hook/` est le cœur extensible de votre API. Ici résident vos contrôleurs, vos middlewares et vos routes personnalisées.

Pour vous aider à construire une API **"Digne des plus grands"**, nous avons rédigé des guides détaillés.

## 📚 Guides de Référence

### 1. [Routing Avancé (GET/POST, Cache, IP Limit)](docs/ROUTING.md)
Apprenez à définir des routes performantes et sécurisées.
*   **Méthodes HTTP** : Maîtrisez `GET`, `POST`, `PUT`, `DELETE`.
*   **Cache Redis** : Accélérez vos réponses avec une seule ligne de code.
*   **Sécurité IP** : Restreignez l'accès à certaines routes (Whitelisting).

### 2. [Authentification & Base de Données](docs/AUTH_DB.md)
Un guide complet "Step-by-Step" pour gérer vos utilisateurs.
*   **SQL Schema** : La structure de base de données idéale.
*   **Inscription / Connexion** : Code PHP complet et sécurisé (`password_hash`).
*   **Session Token** : Authentification Stateless moderne sans cookies de session.

### 3. [Système de Jobs (Queue & Workers)](docs/JOBS.md)
Déchargez votre API des tâches lourdes (Emails, Blockchain) grâce à Redis.
*   **Création de Jobs** : Classes simples et sérialisables.
*   **Dispatching** : Envoi en arrière-plan non-bloquant.
*   **Worker** : Exécution asynchrone.

### 4. [Web3 & Boutique (E-Commerce)](docs/WEB3_SHOP.md)
Implémentez des fonctionnalités métier avancées.
*   **Web3** : Balance Crypto et Localisation Live.
*   **Shop** : Catalogue Produits JSON et gestion de Stock transactionnelle.

---

## 📂 Structure du Dossier

```text
hook/
├── Controllers/     # Vos contrôleurs (Logique métier)
├── Jobs/            # Vos tâches de fond (Queue)
├── Middleware/      # Vos middlewares (Auth, Validation...)
├── Views/           # Vos templates HTML
├── docs/            # Documentation technique détaillée
├── routes.php       # Définition des routes
└── README.md        # Ce fichier
```

## 🚀 Démarrage Rapide

Ouvrez `hook/routes.php` et commencez à coder :

```php
// Exemple simple
$router->get('/api/ping', function() {
    echo json_encode(['status' => 'pong']);
});
```

Pour des implémentations plus robustes, référez-vous aux fichiers dans `docs/`.

---

**THE LIFE COINCOIN** - L'excellence par le code.
