<?php

use TLC\Hook\Controllers\AuthController;
use TLC\Hook\Controllers\EntityController;
use TLC\Hook\Controllers\EncryptionController;
use TLC\Hook\Controllers\FriendController;
use TLC\Hook\Controllers\LevelUpController;
use TLC\Hook\Controllers\ListingController;
use TLC\Hook\Controllers\CollectionController;
use TLC\Hook\Controllers\EggController;
use TLC\Hook\Controllers\MarketplaceController;
use TLC\Hook\Controllers\NotificationController;
use TLC\Hook\Controllers\UserController;
use TLC\Hook\Controllers\UserSummaryController;
use TLC\Hook\Controllers\WalletController;
use TLC\Hook\Controllers\WorkoutController;
use TLC\Hook\Middleware\AuthMiddleware;

/*
|--------------------------------------------------------------------------
| Hook Routes
|--------------------------------------------------------------------------
|
| Here is where you can register routes for your application.
| Use $router to define your routes.
|
*/

$router->get('/api/ping', function() {
    echo json_encode(['status' => 'pong', 'timestamp' => time()]);
});

$router->get('/api/custom', [\TLC\Hook\Controllers\CustomController::class, 'index']);

// Auth Routes (and Legacy /api/auth)
$router->post('/api/auth/register', [AuthController::class, 'register']);
$router->post('/api/auth/login', [AuthController::class, 'login']);
$router->post('/api/auth/loginWithSocial', [AuthController::class, 'loginWithSocial']);
$router->post('/api/auth/send-otp', [AuthController::class, 'sendOtp']);
$router->post('/api/auth/loginWithOtp', [AuthController::class, 'loginWithOtp']);

$router->get('/api/user/me', [AuthController::class, 'me'], [
    'middleware' => [AuthMiddleware::class]
]);

// User Settings
$router->get('/api/userSettings/?', [\TLC\Hook\Controllers\UserSettingController::class, 'getAllSettings'], ['middleware' => [AuthMiddleware::class]]);
$router->get('/api/userSettings/([^/]+)/?', [\TLC\Hook\Controllers\UserSettingController::class, 'getSetting'], ['middleware' => [AuthMiddleware::class]]);
$router->put('/api/userSettings/([^/]+)/?', [\TLC\Hook\Controllers\UserSettingController::class, 'updateSetting'], ['middleware' => [AuthMiddleware::class]]);

$router->post('/api/users/2faEnabled', [UserController::class, 'twoFactorEnabled']); // Public
$router->post('/api/users/loginWithOtp', [AuthController::class, 'loginWithOtp']); // Public
$router->post('/api/users/refresh', [UserController::class, 'refreshToken']); // Public
$router->post('/api/users/otp', [AuthController::class, 'sendOtp']); // Public
$router->get('/api/users/me', [AuthController::class, 'me'], ['middleware' => [AuthMiddleware::class]]);
$router->put('/api/users/me', [UserController::class, 'updateMe'], ['middleware' => [AuthMiddleware::class]]);
$router->delete('/api/users/me', [UserController::class, 'deleteMe'], ['middleware' => [AuthMiddleware::class]]);
$router->get('/api/users/profile/([^/]+)', [UserController::class, 'getPublicProfile']); // Public
$router->get('/api/users/profilePicture', [UserController::class, 'getMeProfilePicture'], ['middleware' => [AuthMiddleware::class]]);
$router->put('/api/users/profilePicture', [UserController::class, 'updateMeProfilePicture'], ['middleware' => [AuthMiddleware::class]]);
$router->get('/api/users/request2FASetupKey', [UserController::class, 'generate2fa'], ['middleware' => [AuthMiddleware::class]]);
$router->post('/api/users/verify2FAToken', [UserController::class, 'verify2fa'], ['middleware' => [AuthMiddleware::class]]);
$router->post('/api/users/updatePassword', [UserController::class, 'updatePassword'], ['middleware' => [AuthMiddleware::class]]);
$router->post('/api/users/sendOtp', [AuthController::class, 'sendOtp'], ['middleware' => [AuthMiddleware::class]]);

$router->get('/api/users/admin', [UserController::class, 'listUsers'], ['middleware' => [AuthMiddleware::class]]);
$router->post('/api/users/admin/addTicketToUsers', [UserController::class, 'addTicketToUsers'], ['middleware' => [AuthMiddleware::class]]);
$router->post('/api/users/user/([a-f0-9]{24})/notify', [UserController::class, 'sendUserNotification'], ['middleware' => [AuthMiddleware::class]]);
$router->get('/api/users/user/([a-f0-9]{24})', [UserController::class, 'getUser'], ['middleware' => [AuthMiddleware::class]]);
$router->post('/api/users/user/([a-f0-9]{24})', [UserController::class, 'updateUser'], ['middleware' => [AuthMiddleware::class]]);

// Encryption Routes (Admin)
$router->post('/api/encryption/cypher', [EncryptionController::class, 'cypher'], ['middleware' => [AuthMiddleware::class]]);

// ------------------------------------

// User Routes (Legacy)
$router->post('/api/users/refresh-token', [UserController::class, 'refreshToken'], [
    'middleware' => [AuthMiddleware::class]
]);
$router->post('/api/users/2fa/generate', [UserController::class, 'generate2fa'], [
    'middleware' => [AuthMiddleware::class]
]);
$router->post('/api/users/2fa/enable', [UserController::class, 'enable2fa'], [
    'middleware' => [AuthMiddleware::class]
]);
$router->post('/api/users/2fa/verify', [UserController::class, 'verify2fa'], [
    'middleware' => [AuthMiddleware::class]
]);
$router->post('/api/users/2fa/validate', [UserController::class, 'validate2fa'], [
    'middleware' => [AuthMiddleware::class]
]);
$router->post('/api/users/2fa/disable', [UserController::class, 'disable2fa'], [
    'middleware' => [AuthMiddleware::class]
]);

// Notification Routes (Admin)
$router->get('/api/notifications/onlineUsers', [NotificationController::class, 'onlineUsers'], [
    'middleware' => [AuthMiddleware::class]
]);
$router->post('/api/notifications/notify/users', [NotificationController::class, 'notifyUsers'], [
    'middleware' => [AuthMiddleware::class]
]);
$router->post('/api/notifications/notify/groups', [NotificationController::class, 'notifyGroups'], [
    'middleware' => [AuthMiddleware::class]
]);

// User Admin Routes (Legacy)
$router->post('/api/users/admin/ban/([a-f0-9]{24})', [UserController::class, 'banUser'], [
    'middleware' => [AuthMiddleware::class]
]);
$router->put('/api/users/admin/([a-f0-9]{24})', [UserController::class, 'updateUser'], [
    'middleware' => [AuthMiddleware::class]
]);
$router->delete('/api/users/admin/([a-f0-9]{24})', [UserController::class, 'deleteUser'], [
    'middleware' => [AuthMiddleware::class]
]);

// User Summary Routes
$router->get('/api/users/summary/get', [UserSummaryController::class, 'getAll'], ['middleware' => [AuthMiddleware::class]]);
$router->get('/api/users/summary/get/([^/]+)', [UserSummaryController::class, 'get'], ['middleware' => [AuthMiddleware::class]]);
$router->get('/api/users/summary/get/([^/]+)/([a-f0-9]{24})/([^/]+)', [UserSummaryController::class, 'get'], ['middleware' => [AuthMiddleware::class]]);

// Wallet Routes
$router->get('/api/wallet/?', [WalletController::class, 'getWallet'], ['middleware' => [AuthMiddleware::class]]);
$router->get('/api/wallet/getSolBalance', [WalletController::class, 'getSolBalance'], ['middleware' => [AuthMiddleware::class]]);
$router->get('/api/wallet/getBalance', [WalletController::class, 'getBalance'], ['middleware' => [AuthMiddleware::class]]);
$router->get('/api/wallet/ducksTokensId', [WalletController::class, 'getDucksTokensId'], ['middleware' => [AuthMiddleware::class]]);
$router->get('/api/wallet/nfts', [WalletController::class, 'getNfts'], ['middleware' => [AuthMiddleware::class]]);
$router->get('/api/wallet/eggs', [WalletController::class, 'getEggs'], ['middleware' => [AuthMiddleware::class]]);
$router->get('/api/wallet/nonce', [WalletController::class, 'getNonce'], ['middleware' => [AuthMiddleware::class]]);
$router->post('/api/wallet/link', [WalletController::class, 'linkWallet'], ['middleware' => [AuthMiddleware::class]]);
$router->post('/api/wallet/signReward', [WalletController::class, 'signRewardTransaction'], ['middleware' => [AuthMiddleware::class]]); // Admin/Internal

// Game Routes (Protected)
$router->get('/api/entities', [EntityController::class, 'list'], [
    'middleware' => [AuthMiddleware::class]
]);
$router->get('/api/entities/([a-f0-9]{24})', [EntityController::class, 'get'], [
    'middleware' => [AuthMiddleware::class]
]);
$router->post('/api/entities/([a-f0-9]{24})/levelup', [EntityController::class, 'levelUp'], [
    'middleware' => [AuthMiddleware::class]
]);

$router->get('/api/eggs', [EggController::class, 'list'], [
    'middleware' => [AuthMiddleware::class]
]);
$router->get('/api/eggs/([a-f0-9]{24})', [EggController::class, 'get'], [
    'middleware' => [AuthMiddleware::class]
]);

// Marketplace Routes
$router->get('/api/marketplace/listings', [MarketplaceController::class, 'getListings']);
$router->post('/api/marketplace/listings', [MarketplaceController::class, 'createListing'], [
    'middleware' => [AuthMiddleware::class]
]);
$router->get('/api/marketplace/pots', [MarketplaceController::class, 'getPots']);

// Marketplace Listing Routes (Specific)
$router->get('/api/marketplace/listing/?', [ListingController::class, 'index']);
$router->post('/api/marketplace/listing/?', [ListingController::class, 'create'], ['middleware' => [AuthMiddleware::class]]);
$router->post('/api/marketplace/listing/buy/([a-f0-9]{24})', [ListingController::class, 'buy'], ['middleware' => [AuthMiddleware::class]]);
$router->delete('/api/marketplace/listing/([a-f0-9]{24})', [ListingController::class, 'delete'], ['middleware' => [AuthMiddleware::class]]);

// Spending Wallet Routes (Unified)
$router->get('/api/spending/getTickets', [\TLC\Hook\Controllers\SpendingWalletController::class, 'getTickets'], ['middleware' => [AuthMiddleware::class]]);
$router->get('/api/spending/getBalance', [\TLC\Hook\Controllers\SpendingWalletController::class, 'getBalance'], ['middleware' => [AuthMiddleware::class]]);
$router->get('/api/spending/duckTeam', [\TLC\Hook\Controllers\SpendingWalletController::class, 'duckTeam'], ['middleware' => [AuthMiddleware::class]]);
$router->get('/api/spending/stats', [\TLC\Hook\Controllers\SpendingWalletController::class, 'stats'], ['middleware' => [AuthMiddleware::class]]);
$router->post('/api/spending/admin/burnWallet', [\TLC\Hook\Controllers\SpendingWalletController::class, 'burnWallet'], ['middleware' => [AuthMiddleware::class]]);
$router->get('/api/spending/admin', [\TLC\Hook\Controllers\SpendingWalletController::class, 'listWallets'], ['middleware' => [AuthMiddleware::class]]);
$router->get('/api/spending/admin/getBalance/([a-f0-9]{24})', [\TLC\Hook\Controllers\SpendingWalletController::class, 'getWalletBalance'], ['middleware' => [AuthMiddleware::class]]);
$router->post('/api/spending/admin/setMaxEndurance/([a-f0-9]{24})', [\TLC\Hook\Controllers\SpendingWalletController::class, 'setMaxEndurance'], ['middleware' => [AuthMiddleware::class]]);

// Energy Routes
// Activity Routes
$router->get('/api/activity/?', [\TLC\Hook\Controllers\ActivityController::class, 'index'], [
    'middleware' => [AuthMiddleware::class]
]);

$router->get('/api/energy', [\TLC\Hook\Controllers\EnergyController::class, 'getEnergy'], [
    'middleware' => [AuthMiddleware::class]
]);
$router->post('/api/energy/refill', [\TLC\Hook\Controllers\EnergyController::class, 'refill'], [
    'middleware' => [AuthMiddleware::class]
]);
$router->post('/api/energy/forceRecomputeMaximumEnergyForSpending', [\TLC\Hook\Controllers\EnergyController::class, 'forceRecomputeMaximumEnergyForSpending'], [
    'middleware' => [AuthMiddleware::class] 
]);
$router->get('/api/stats/entity/([a-zA-Z0-9_]+)', [\TLC\Hook\Controllers\StatsController::class, 'getEntityStats']);

// Workout Routes (Unified)
$router->post('/api/workout/calculateUsersSummary', [WorkoutController::class, 'calculateUsersSummary'], ['middleware' => [AuthMiddleware::class]]);
$router->get('/api/workout/?', [WorkoutController::class, 'list'], ['middleware' => [AuthMiddleware::class]]);
$router->get('/api/workout/restore', [WorkoutController::class, 'restore'], ['middleware' => [AuthMiddleware::class]]);
$router->get('/api/workout/hasWorkout', [WorkoutController::class, 'hasWorkout'], ['middleware' => [AuthMiddleware::class]]);
$router->post('/api/workout/init', [WorkoutController::class, 'init'], ['middleware' => [AuthMiddleware::class]]);
$router->post('/api/workout/location', [WorkoutController::class, 'location'], ['middleware' => [AuthMiddleware::class]]);
$router->post('/api/workout/compute', [WorkoutController::class, 'compute'], ['middleware' => [AuthMiddleware::class]]);
$router->post('/api/workout/finish', [WorkoutController::class, 'finish'], ['middleware' => [AuthMiddleware::class]]);
$router->get('/api/workout/passive/estimate', [WorkoutController::class, 'estimatePassive'], ['middleware' => [AuthMiddleware::class]]);
$router->post('/api/workout/passive/execute', [WorkoutController::class, 'executePassive'], ['middleware' => [AuthMiddleware::class]]);
$router->get('/api/workout/([a-f0-9]{24})', [WorkoutController::class, 'get'], ['middleware' => [AuthMiddleware::class]]);
$router->get('/api/workout/([a-f0-9]{24})/ai-analysis', [WorkoutController::class, 'analyze'], ['middleware' => [AuthMiddleware::class]]);
$router->post('/api/workout/recomputeFinalStats/([a-f0-9]{24})', [WorkoutController::class, 'recomputeFinalStats'], ['middleware' => [AuthMiddleware::class]]);

// Transfer Attempt Routes
$router->post('/api/transfers/attempt/init', [\TLC\Hook\Controllers\TransferAttemptController::class, 'init'], ['middleware' => [AuthMiddleware::class]]);
$router->post('/api/transfers/attempt/verify/([^/]+)', [\TLC\Hook\Controllers\TransferAttemptController::class, 'verify'], ['middleware' => [AuthMiddleware::class]]);
$router->get('/api/transfers/attempt/?', [\TLC\Hook\Controllers\TransferAttemptController::class, 'list'], ['middleware' => [AuthMiddleware::class]]);
$router->get('/api/transfers/attempt/check/([^/]+)', [\TLC\Hook\Controllers\TransferAttemptController::class, 'check'], ['middleware' => [AuthMiddleware::class]]);
$router->post('/api/transfers/attempt/removeDuplicatesTokensIdNfts', [\TLC\Hook\Controllers\TransferAttemptController::class, 'removeDuplicatesTokensIdNfts'], ['middleware' => [AuthMiddleware::class]]);

// Swap Routes
$router->post('/api/transfers/swap/mintCat', [\TLC\Hook\Controllers\SwapController::class, 'mintCat'], ['middleware' => [AuthMiddleware::class]]);
$router->post('/api/transfers/swap/poolClaim/([^/]+)/([^/]+)', [\TLC\Hook\Controllers\SwapController::class, 'poolClaim'], ['middleware' => [AuthMiddleware::class]]);
$router->get('/api/transfers/swap/pool/([^/]+)/([^/]+)/?([^/]*)/?([^/]*)', [\TLC\Hook\Controllers\SwapController::class, 'getPoolDetails'], ['middleware' => [AuthMiddleware::class]]);
$router->get('/api/transfers/swap/get/([^/]+)', [\TLC\Hook\Controllers\SwapController::class, 'getSwap'], ['middleware' => [AuthMiddleware::class]]);
$router->post('/api/transfers/swap/', [\TLC\Hook\Controllers\SwapController::class, 'swap'], ['middleware' => [AuthMiddleware::class]]);

// Game Constants Routes
$router->get('/api/gameConstants/?', [\TLC\Hook\Controllers\GameConstantController::class, 'list'], [
    'middleware' => [AuthMiddleware::class]
]);
$router->get('/api/gameConstants/public/?', [\TLC\Hook\Controllers\GameConstantController::class, 'listPublic']);
$router->post('/api/gameConstants/public/([^/]+)', [\TLC\Hook\Controllers\GameConstantController::class, 'getPublicByKey']);
$router->put('/api/gameConstants/([a-f0-9]{24})', [\TLC\Hook\Controllers\GameConstantController::class, 'update'], [
    'middleware' => [AuthMiddleware::class]
]);

// --- SwarmGen Mint Routes ---
$router->get('/api/swarmGen/mint/?', [\TLC\Hook\Controllers\MintController::class, 'mintRules'], ['middleware' => [AuthMiddleware::class]]);
$router->get('/api/swarmGen/mint/([a-f0-9]{24})/([a-f0-9]{24})', [\TLC\Hook\Controllers\MintController::class, 'mint'], ['middleware' => [AuthMiddleware::class]]);
$router->post('/api/swarmGen/mint', [\TLC\Hook\Controllers\MintController::class, 'executeMint'], ['middleware' => [AuthMiddleware::class]]);

// --- SwarmGen Level Up Routes ---
$router->post('/api/swarmGen/levelUp/entity/([a-f0-9]{24})', [LevelUpController::class, 'levelUp'], ['middleware' => [AuthMiddleware::class]]);
$router->post('/api/swarmGen/levelUp/entity/([a-f0-9]{24})/accelerate', [LevelUpController::class, 'accelerate'], ['middleware' => [AuthMiddleware::class]]);
$router->post('/api/swarmGen/levelUp/entity/([a-f0-9]{24})/unlockPocket', [LevelUpController::class, 'unlockPocket'], ['middleware' => [AuthMiddleware::class]]);
$router->post('/api/swarmGen/levelUp/entity/([a-f0-9]{24})/attributes', [LevelUpController::class, 'attributes'], ['middleware' => [AuthMiddleware::class]]);
$router->get('/api/swarmGen/levelUp/entity/([a-f0-9]{24})', [LevelUpController::class, 'getInfo'], ['middleware' => [AuthMiddleware::class]]);
// --- SwarmGen Collections Routes ---
$router->get('/api/swarmGen/collections/?', [CollectionController::class, 'list']); // Public

// --- SwarmGen Egg Routes ---
$router->get('/api/swarmGen/egg/importTest', [EggController::class, 'importTest']); // Public

// Admin/Static routes first to avoid regex collision
$router->get('/api/swarmGen/egg/admin', [EggController::class, 'adminList'], ['middleware' => [AuthMiddleware::class]]);
$router->get('/api/swarmGen/egg/stats', [EggController::class, 'stats'], ['middleware' => [AuthMiddleware::class]]);
$router->put('/api/swarmGen/egg/updateOwnership/([a-f0-9]{24})', [EggController::class, 'updateOwnership'], ['middleware' => [AuthMiddleware::class]]);
$router->get('/api/swarmGen/egg/getEgg/([a-f0-9]{24})', [EggController::class, 'getEggById'], ['middleware' => [AuthMiddleware::class]]);

// Dynamic ID routes
$router->get('/api/swarmGen/egg/([a-f0-9]{24})/get', [EggController::class, 'getEggDetail'], ['middleware' => [AuthMiddleware::class]]);
$router->post('/api/swarmGen/egg/([a-f0-9]{24})/open', [EggController::class, 'open'], ['middleware' => [AuthMiddleware::class]]);

// Dynamic Collection routes
$router->get('/api/swarmGen/egg/([^/]+)/generate', [EggController::class, 'generate']); // Public
$router->get('/api/swarmGen/egg/([^/]+)/getEggs', [EggController::class, 'getEggsByCollection'], ['middleware' => [AuthMiddleware::class]]);
$router->get('/api/swarmGen/egg/([^/]+)/getEgg/([a-f0-9]{24})', [EggController::class, 'getEggByCollectionAndId'], ['middleware' => [AuthMiddleware::class]]);
$router->post('/api/swarmGen/egg/([^/]+)/create', [EggController::class, 'create'], ['middleware' => [AuthMiddleware::class]]);

// --- SwarmGen Entity Routes ---
$router->get('/api/swarmGen/entity/importTest', [EntityController::class, 'importTest']); // Public

// Specific Admin/Static routes first
$router->get('/api/swarmGen/entity/stats', [EntityController::class, 'stats'], ['middleware' => [AuthMiddleware::class]]);
$router->get('/api/swarmGen/entity/admin', [EntityController::class, 'adminList'], ['middleware' => [AuthMiddleware::class]]);
$router->put('/api/swarmGen/entity/updateOwnership/([a-f0-9]{24})', [EntityController::class, 'updateOwnership'], ['middleware' => [AuthMiddleware::class]]);
$router->get('/api/swarmGen/entity/getEntity/([a-f0-9]{24})', [EntityController::class, 'getEntityById'], ['middleware' => [AuthMiddleware::class]]);

// Dynamic ID routes
$router->get('/api/swarmGen/entity/([a-f0-9]{24})/get', [EntityController::class, 'getEntityDetail'], ['middleware' => [AuthMiddleware::class]]);
$router->post('/api/swarmGen/entity/([a-f0-9]{24})/set', [EntityController::class, 'setEntity'], ['middleware' => [AuthMiddleware::class]]);

// Dynamic Collection routes
$router->get('/api/swarmGen/entity/([^/]+)/getEntitiesForUser/([a-f0-9]{24})', [EntityController::class, 'getEntitiesForUser'], ['middleware' => [AuthMiddleware::class]]);
$router->get('/api/swarmGen/entity/([^/]+)/getEntities', [EntityController::class, 'getEntities'], ['middleware' => [AuthMiddleware::class]]);
$router->get('/api/swarmGen/entity/([^/]+)/getEntity/([a-f0-9]{24})', [EntityController::class, 'getEntityByCollectionAndId'], ['middleware' => [AuthMiddleware::class]]);
$router->post('/api/swarmGen/entity/([^/]+)/deleteAllTokensId', [EntityController::class, 'deleteAllTokensId'], ['middleware' => [AuthMiddleware::class]]);
$router->get('/api/swarmGen/entity/([^/]+)/generate', [EntityController::class, 'generate'], ['middleware' => [AuthMiddleware::class]]);
$router->post('/api/swarmGen/entity/([^/]+)/create', [EntityController::class, 'create'], ['middleware' => [AuthMiddleware::class]]);

// Friends routes
$router->post('/api/friends/request', [FriendController::class, 'sendRequest'], ['middleware' => [AuthMiddleware::class]]);
$router->post('/api/friends/respond', [FriendController::class, 'respondRequest'], ['middleware' => [AuthMiddleware::class]]);
$router->get('/api/friends/?', [FriendController::class, 'getFriends'], ['middleware' => [AuthMiddleware::class]]);
$router->get('/api/friends/pending', [FriendController::class, 'getPendingRequests'], ['middleware' => [AuthMiddleware::class]]);
$router->get('/api/friends/running', [FriendController::class, 'getRunningFriends'], ['middleware' => [AuthMiddleware::class]]);
