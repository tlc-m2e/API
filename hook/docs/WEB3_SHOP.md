# Guides d'Implémentation : Web3 & Marketplace

Ce document détaille l'implémentation des fonctionnalités Web3 (Wallet, NFTs) et E-commerce (Marketplace) spécifiques au projet "The Life Coincoin".

---

## 1. Web3 : Wallet & Assets (Solana)

Le `WalletController` gère l'inventaire Web3 de l'utilisateur : Solde SOL, Tokens SPL, et NFTs (Ducks, Eggs).

### A. Récupération du Wallet
**Route :** `GET /api/wallet`

```php
// WalletController.php

public function getWallet() {
    $userId = $_SERVER['user_id'];

    // Récupération des balances depuis la DB (cache ou temps réel)
    $solBalance = $this->getSolBalanceLogic($userId);
    $tokens = $this->getTokensLogic($userId);

    echo json_encode([
        'address' => 'SolanaWalletAddress...',
        'sol' => $solBalance,
        'tokens' => $tokens, // COIN, TOKEN, Seed...
    ]);
}
```

### B. Gestion des NFTs (Ducks/Eggs)
Le projet distingue les NFTs (sur la blockchain) de leur représentation en jeu.

**Route :** `GET /api/wallet/nfts`

```php
public function getNfts() {
    // Logique pour scanner la blockchain ou une API d'indexation
    // Retourne les métadonnées des NFTs détenus par le wallet lié

    $nfts = [
        ['mint' => '...', 'name' => 'King Duck #1', 'type' => 'Duck'],
        ['mint' => '...', 'name' => 'Mysterious Egg', 'type' => 'Egg']
    ];

    echo json_encode(['nfts' => $nfts]);
}
```

---

## 2. Marketplace & E-Commerce

Le système permet aux utilisateurs d'acheter et vendre des assets (Ducks, Items) via le `MarketplaceController` et `ListingController`.

### A. Créer une Annonce (Listing)
**Route :** `POST /api/marketplace/listing`

```php
// ListingController.php

public function create() {
    $userId = $_SERVER['user_id'];
    $data = json_decode(file_get_contents('php://input'), true);

    // 1. Validation : L'utilisateur possède-t-il l'objet ?
    // 2. Création de l'annonce dans MongoDB/MariaDB

    $listingId = $this->listingModel->create([
        'seller_id' => new ObjectId($userId),
        'item_id' => new ObjectId($data['item_id']),
        'price' => (float)$data['price'],
        'currency' => $data['currency'], // ex: 'SOL' ou 'TOKEN'
        'status' => 'active',
        'created_at' => new UTCDateTime()
    ]);

    echo json_encode(['listing_id' => (string)$listingId]);
}
```

### B. Achat d'un Item (Buy)
**Route :** `POST /api/marketplace/listing/buy/([a-f0-9]{24})`

Cette opération est atomique et critique. Elle gère le transfert de propriété et de fonds.

```php
// ListingController.php

public function buy($listingId) {
    $buyerId = $_SERVER['user_id'];

    // 1. Récupérer l'annonce
    $listing = $this->listingModel->findOne(['_id' => new ObjectId($listingId)]);

    if ($listing['status'] !== 'active') {
        http_response_code(400); echo json_encode(['error' => 'Not availabe']); return;
    }

    // 2. Vérifier les fonds du Buyer (Spending Wallet)
    // 3. Effectuer la transaction (Débit Buyer -> Crédit Seller)
    // 4. Mettre à jour la propriété de l'Item (Duck/Egg)
    // 5. Fermer l'annonce

    $this->listingModel->updateOne(
        ['_id' => $listing['_id']],
        ['$set' => ['status' => 'sold', 'buyer_id' => new ObjectId($buyerId)]]
    );

    echo json_encode(['status' => 'success']);
}
```

### C. Swap & Liquidity Pools
Le `SwapController` permet d'échanger des tokens internes.

**Route :** `POST /api/transfers/swap`

```php
// SwapController.php

public function swap() {
    $input = json_decode(file_get_contents('php://input'), true);

    // Logique de calcul du taux de change (AMM ou Oracle)
    // Exécution du swap dans le Spending Wallet
}
```
