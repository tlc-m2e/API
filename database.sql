-- Sample Database for THE LIFE COINCOIN (MariaDB / MySQL compatible)
-- Note: The Universal API uses MongoDB by default.
-- This schema is provided as a starting point if you wish to implement a SQL adapter.

CREATE DATABASE IF NOT EXISTS `the_life_coincoin` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `the_life_coincoin`;

-- Users
CREATE TABLE IF NOT EXISTS `users` (
  `id` VARCHAR(24) PRIMARY KEY,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` VARCHAR(50) DEFAULT 'user',
  `first_name` VARCHAR(100) DEFAULT NULL,
  `last_name` VARCHAR(100) DEFAULT NULL,
  `age` INT DEFAULT NULL,
  `gender` VARCHAR(20) DEFAULT NULL,
  `pseudo` VARCHAR(100) UNIQUE DEFAULT NULL,
  `profile_picture` VARCHAR(255) DEFAULT NULL,
  `is_public` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Spending Wallets
CREATE TABLE IF NOT EXISTS `spending_wallets` (
  `id` VARCHAR(24) PRIMARY KEY,
  `user_id` VARCHAR(24) NOT NULL,
  `amountOfSOL` DECIMAL(18,8) DEFAULT 0,
  `amountOfCOIN` DECIMAL(18,8) DEFAULT 0,
  `amountOfTOKEN` DECIMAL(18,8) DEFAULT 0,
  `amountOfSeed` DECIMAL(18,8) DEFAULT 0,
  `energy` INT DEFAULT 10,
  `maxEnergy` INT DEFAULT 10,
  `maxEndurance` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

-- Friends
CREATE TABLE IF NOT EXISTS `friends` (
  `id` VARCHAR(24) PRIMARY KEY,
  `user_id_1` VARCHAR(24) NOT NULL,
  `user_id_2` VARCHAR(24) NOT NULL,
  `status` VARCHAR(50) DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id_1`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id_2`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

-- Ducks (Main NFTs/Characters)
-- Game Entities (Main NFTs/Characters/Items)
CREATE TABLE IF NOT EXISTS `game_entities` (
  `id` VARCHAR(24) PRIMARY KEY,
  `owner_id` VARCHAR(24) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `level` INT DEFAULT 1,
  `type` VARCHAR(50) DEFAULT 'entity',
  `metadata` JSON DEFAULT NULL,
  FOREIGN KEY (`owner_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

-- Workouts / Runs
CREATE TABLE IF NOT EXISTS `workouts` (
  `id` VARCHAR(24) PRIMARY KEY,
  `user_id` VARCHAR(24) NOT NULL,
  `distance_km` DECIMAL(8,2) DEFAULT 0,
  `duration_seconds` INT DEFAULT 0,
  `status` VARCHAR(50) DEFAULT 'in_progress',
  `earned_coin` DECIMAL(18,8) DEFAULT 0,
  `earned_token` DECIMAL(18,8) DEFAULT 0,
  `started_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `ended_at` TIMESTAMP NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

-- Listings (Marketplace)
CREATE TABLE IF NOT EXISTS `listings` (
  `id` VARCHAR(24) PRIMARY KEY,
  `item_id` VARCHAR(255) NOT NULL,
  `seller_id` VARCHAR(24) NOT NULL,
  `buyer_id` VARCHAR(24) NULL,
  `price` DECIMAL(18,8) NOT NULL,
  `currency` VARCHAR(10) DEFAULT 'SOL',
  `status` VARCHAR(50) DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `sold_at` TIMESTAMP NULL,
  FOREIGN KEY (`seller_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`buyer_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
);

-- Sales (Marketplace History)
CREATE TABLE IF NOT EXISTS `sales` (
  `id` VARCHAR(24) PRIMARY KEY,
  `listing_id` VARCHAR(24) NOT NULL,
  `seller_id` VARCHAR(24) NOT NULL,
  `buyer_id` VARCHAR(24) NOT NULL,
  `price` DECIMAL(18,8) NOT NULL,
  `currency` VARCHAR(10) DEFAULT 'SOL',
  `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`listing_id`) REFERENCES `listings`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`seller_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`buyer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

-- Swaps
CREATE TABLE IF NOT EXISTS `swaps` (
  `id` VARCHAR(24) PRIMARY KEY,
  `user_id` VARCHAR(24) NOT NULL,
  `fromToken` VARCHAR(50) NOT NULL,
  `toToken` VARCHAR(50) NOT NULL,
  `amountIn` DECIMAL(18,8) NOT NULL,
  `amountOut` DECIMAL(18,8) NOT NULL,
  `status` VARCHAR(50) DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

-- Transfer Attempts
CREATE TABLE IF NOT EXISTS `transfer_attempts` (
  `id` VARCHAR(24) PRIMARY KEY,
  `user_id` VARCHAR(24) NOT NULL,
  `type` VARCHAR(50) NOT NULL,
  `tokenType` VARCHAR(50) NOT NULL,
  `tokenId` VARCHAR(255) NULL,
  `amount` DECIMAL(18,8) DEFAULT 0,
  `status` VARCHAR(50) DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

-- Mints
CREATE TABLE IF NOT EXISTS `mints` (
  `id` VARCHAR(24) PRIMARY KEY,
  `user_id` VARCHAR(24) NOT NULL,
  `parentOneId` VARCHAR(255) NULL,
  `parentTwoId` VARCHAR(255) NULL,
  `entityId` VARCHAR(255) NULL,
  `price` DECIMAL(18,8) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

-- Game Constants
CREATE TABLE IF NOT EXISTS `game_constants` (
  `id` VARCHAR(24) PRIMARY KEY,
  `key` VARCHAR(255) NOT NULL UNIQUE,
  `value` TEXT NOT NULL,
  `description` VARCHAR(255) NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Initial Mock Data
INSERT INTO `users` (`id`, `email`, `password`, `role`) VALUES ('65e012a9b3c4d5e6f7a8b9c0', 'demo@thelifecoincoin.com', 'hashed_password_here', 'admin');

-- White Label Game Constants
INSERT INTO `game_constants` (`id`, `key`, `value`, `description`) VALUES
('65e012a9b3c4d5e6f7a8b9c1', 'APP_NAME', 'TLC M2E', 'The name of the application'),
('65e012a9b3c4d5e6f7a8b9c2', 'ENTITY_NAME_SINGULAR', 'Runner', 'Singular name of the main game entity'),
('65e012a9b3c4d5e6f7a8b9c3', 'ENTITY_NAME_PLURAL', 'Runners', 'Plural name of the main game entity'),
('65e012a9b3c4d5e6f7a8b9c4', 'CURRENCY_1_NAME', 'SOL', 'Name of the first currency (Crypto)'),
('65e012a9b3c4d5e6f7a8b9c5', 'CURRENCY_2_NAME', 'COIN', 'Name of the second currency (In-game earning)'),
('65e012a9b3c4d5e6f7a8b9c6', 'CURRENCY_3_NAME', 'REWARD', 'Name of the third currency (Premium/Governance)'),
('65e012a9b3c4d5e6f7a8b9c7', 'LEVEL_UP_BASE_COST', '10', 'Base cost multiplier for leveling up'),
('65e012a9b3c4d5e6f7a8b9c8', 'ENERGY_REGEN_PER_HOUR', '1', 'Amount of energy regenerated per hour'),
('65e012a9b3c4d5e6f7a8b9c9', 'REWARD_COEFFICIENT_KM', '5.5', 'Coins earned per kilometer ran'),
('65e012a9b3c4d5e6f7a8b9ca', 'GAME_CONSTANT_MINT_ENABLED', 'true', 'Is Minting Enabled'),
('65e012a9b3c4d5e6f7a8b9cb', 'GAME_CONSTANT_MINT_PRICE', '50', 'Base price for minting an entity');

INSERT INTO `spending_wallets` (`id`, `user_id`, `amountOfSOL`, `amountOfCOIN`, `amountOfTOKEN`, `amountOfSeed`, `energy`) VALUES ('65e012a9b3c4d5e6f7a8b9cc', '65e012a9b3c4d5e6f7a8b9c0', 1.5, 500, 100, 20, 10);
INSERT INTO `game_entities` (`id`, `owner_id`, `name`, `level`, `type`, `metadata`) VALUES ('65e012a9b3c4d5e6f7a8b9cd', '65e012a9b3c4d5e6f7a8b9c0', 'Runner #1', 5, 'main', '{"pockets": 0, "status": "idle"}');

-- Audit Trail
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` VARCHAR(24) PRIMARY KEY,
  `user_id` VARCHAR(24) NULL,
  `action` VARCHAR(50) NOT NULL,
  `resource_type` VARCHAR(100) NOT NULL,
  `resource_id` VARCHAR(24) NOT NULL,
  `old_values` JSON DEFAULT NULL,
  `new_values` JSON DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- RBAC Permissions
CREATE TABLE IF NOT EXISTS `permissions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `description` VARCHAR(255) NULL
);

CREATE TABLE IF NOT EXISTS `role_permissions` (
  `role` VARCHAR(50) NOT NULL,
  `permission_id` INT NOT NULL,
  PRIMARY KEY (`role`, `permission_id`),
  FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
);

INSERT INTO `permissions` (`id`, `name`, `description`) VALUES
(1, 'burnWallet', 'Allows burning tokens from a wallet'),
(2, 'updateConfig', 'Allows updating game constants'),
(3, 'viewLogs', 'Allows viewing system and audit logs');

INSERT INTO `role_permissions` (`role`, `permission_id`) VALUES
('admin', 1),
('admin', 2),
('admin', 3);
