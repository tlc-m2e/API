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
CREATE TABLE IF NOT EXISTS `ducks` (
  `id` VARCHAR(24) PRIMARY KEY,
  `owner_id` VARCHAR(24) NOT NULL,
  `tokenId` VARCHAR(255) NULL,
  `name` VARCHAR(100) NOT NULL,
  `level` INT DEFAULT 1,
  `pockets` INT DEFAULT 0,
  `status` VARCHAR(50) DEFAULT 'idle',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`owner_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);


-- Eggs
CREATE TABLE IF NOT EXISTS `eggs` (
  `id` VARCHAR(24) PRIMARY KEY,
  `owner_id` VARCHAR(24) NOT NULL,
  `tokenId` VARCHAR(255) NULL,
  `type` VARCHAR(50) DEFAULT 'common',
  `status` VARCHAR(50) DEFAULT 'incubating',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
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
  `eggId` VARCHAR(255) NULL,
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
INSERT INTO `game_constants` (`id`, `key`, `value`, `description`) VALUES ('65e012a9b3c4d5e6f7a8b9c1', 'GAME_CONSTANT_MINT_ENABLED', 'true', 'Is Minting Enabled');
INSERT INTO `game_constants` (`id`, `key`, `value`, `description`) VALUES ('65e012a9b3c4d5e6f7a8b9c2', 'GAME_CONSTANT_MINT_PRICE', '50', 'Base price for minting an egg in TOKEN');
INSERT INTO `spending_wallets` (`id`, `user_id`, `amountOfSOL`, `amountOfCOIN`, `amountOfTOKEN`, `amountOfSeed`, `energy`) VALUES ('65e012a9b3c4d5e6f7a8b9c3', '65e012a9b3c4d5e6f7a8b9c0', 1.5, 500, 100, 20, 10);
INSERT INTO `ducks` (`id`, `owner_id`, `name`, `level`) VALUES ('65e012a9b3c4d5e6f7a8b9c4', '65e012a9b3c4d5e6f7a8b9c0', 'Runner Duck #1', 5);
