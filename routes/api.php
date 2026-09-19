<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BlockController;
use App\Http\Controllers\Api\V1\CallController;
use App\Http\Controllers\Api\V1\CoinPackageController;
use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\CreatorController;
use App\Http\Controllers\Api\V1\CreditController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\DiscoveryController;
use App\Http\Controllers\Api\V1\EarningsController;
use App\Http\Controllers\Api\V1\GiftController;
use App\Http\Controllers\Api\V1\GoogleAuthController;
use App\Http\Controllers\Api\V1\GuidelinesController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\LevelController;
use App\Http\Controllers\Api\V1\LivenessController;
use App\Http\Controllers\Api\V1\MatchController;
use App\Http\Controllers\Api\V1\MessageController;
use App\Http\Controllers\Api\V1\MpesaOtpController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OnboardingController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PhotoController;
use App\Http\Controllers\Api\V1\PresenceController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SpotlightController;
use App\Http\Controllers\Api\V1\SwipeController;
use App\Http\Controllers\Api\V1\WalletController;
use App\Http\Controllers\Api\V1\WebhookController;
use App\Http\Controllers\Api\V1\WithdrawalController;
use App\Http\Middleware\EnsureAccountActive;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

// Public Webhook & Callback Routes
Route::get('/coin-packages', [CoinPackageController::class, 'index']);
Route::get('/token-packages', [CoinPackageController::class, 'index']);
Route::post('/payments/mpesa/callback', [WebhookController::class, 'handleMpesa']);

Route::prefix('webhooks')->group(function () {
    Route::post('/kora', [WebhookController::class, 'handleKora'])->name('webhooks.kora');
    Route::post('/flutterwave', [WebhookController::class, 'handleFlutterwave'])->name('webhooks.flutterwave');
    Route::post('/mpesa', [WebhookController::class, 'handleMpesa'])->name('webhooks.mpesa');
    Route::post('/mpesa/b2c/result', [WebhookController::class, 'handleMpesaB2CResult'])->name('webhooks.mpesa.b2c.result');
    Route::post('/mpesa/b2c/timeout', [WebhookController::class, 'handleMpesaB2CTimeout'])->name('webhooks.mpesa.b2c.timeout');
    Route::post('/googlepay', [WebhookController::class, 'handleGooglePay'])->name('webhooks.googlepay');
});

// Authentication Routes with Rate Limiting
Route::prefix('auth')->group(function () {
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/google', [GoogleAuthController::class, 'google']);
    });

    Route::middleware(['auth:sanctum', EnsureAccountActive::class])->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/onboarding', [OnboardingController::class, 'onboard']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

use App\Http\Controllers\Api\V1\InterestController;

// Protected API Routes with Throttle Middleware
Route::middleware(['auth:sanctum', EnsureAccountActive::class, 'throttle:60,1'])->group(function () {
    // Interests Catalog
    Route::get('/interests', [InterestController::class, 'index']);

    // Creator Status & Application
    Route::post('/creator/apply', [CreatorController::class, 'apply']);
    Route::get('/creator/status', [CreatorController::class, 'status']);

    // Selfie Liveness Verification
    Route::get('/liveness/challenge', [LivenessController::class, 'challenge']);
    Route::post('/liveness/verify', [LivenessController::class, 'verify']);

    // Creator M-Pesa Phone Verification OTP
    Route::post('/payouts/mpesa/send-otp', [MpesaOtpController::class, 'sendOtp']);
    Route::post('/payouts/mpesa/verify-otp', [MpesaOtpController::class, 'verifyOtp']);
    // Profile
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::put('/profile/interests', [ProfileController::class, 'update']);
    Route::get('/profile/{user}', [ProfileController::class, 'showUser']);

    // Photos
    Route::get('/photos', [PhotoController::class, 'index']);
    Route::post('/photos', [PhotoController::class, 'store']);
    Route::delete('/photos/{photo}', [PhotoController::class, 'destroy']);
    Route::post('/photos/{photo}/primary', [PhotoController::class, 'setPrimary']);

    // Discovery & Matching
    Route::get('/discovery', [DiscoveryController::class, 'index']);
    Route::get('/discover', [DiscoveryController::class, 'index']);
    Route::post('/likes', [SwipeController::class, 'swipe']);
    Route::get('/matches', [MatchController::class, 'index']);
    Route::post('/matches/search', [MatchController::class, 'search']);

    // Conversations & Messages
    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::post('/conversations', [ConversationController::class, 'store']);
    Route::get('/conversations/{conversation}/messages', [MessageController::class, 'index']);
    Route::post('/conversations/{conversation}/messages', [MessageController::class, 'store']);

    // Wallet
    Route::get('/wallet', [WalletController::class, 'show']);
    Route::get('/wallet/transactions', [WalletController::class, 'transactions']);

    // Payments API
    Route::get('/payments/methods', [PaymentController::class, 'methods']);
    Route::get('/payments', [PaymentController::class, 'index']);
    Route::get('/payments/{reference}', [PaymentController::class, 'status']);

    // Financial Operations (Tighter Throttle Rate Limit)
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('/payments/initiate', [PaymentController::class, 'initiate']);
        Route::post('/payments/purchase', [PaymentController::class, 'initiate']);
        Route::post('/wallet/topup', [WalletController::class, 'topup']);
        Route::post('/gifts/send', [GiftController::class, 'send']);
        Route::post('/withdrawals/request', [WithdrawalController::class, 'requestPayout']);
    });

    // Call Endpoints
    Route::post('/calls/request', [CallController::class, 'requestCall']);
    Route::post('/calls/{call}/accept', [CallController::class, 'acceptCall']);
    Route::post('/calls/{call}/decline', [CallController::class, 'declineCall']);
    Route::post('/calls/{call}/cancel', [CallController::class, 'cancelCall']);
    Route::get('/calls/{call}/status', [CallController::class, 'status']);
    Route::get('/calls/pending', [CallController::class, 'pending']);
    Route::post('/calls/{call}/ping', [CallController::class, 'pingCall']);
    Route::post('/calls/{call}/end', [CallController::class, 'endCall']);

    Route::get('/calls/history', [CallController::class, 'history']);
    Route::get('/calls/earnings', [CallController::class, 'earnings']);

    // Credits & Earnings
    Route::get('/credits', [CreditController::class, 'summary']);
    Route::get('/credits/transactions', [CreditController::class, 'transactions']);

    // Presence & Call Availability
    Route::post('/presence/heartbeat', [PresenceController::class, 'heartbeat']);
    Route::get('/users/{user}/presence', [PresenceController::class, 'show']);

    // Virtual Gifts Catalog & History
    Route::get('/gifts', [GiftController::class, 'index']);
    Route::get('/gifts/history', [GiftController::class, 'history']);
    Route::get('/gifts/sent', [GiftController::class, 'sent']);
    Route::get('/gifts/received', [GiftController::class, 'received']);

    // Creator Earnings & Withdrawals History
    Route::get('/earnings', [EarningsController::class, 'index']);
    Route::get('/withdrawals/history', [WithdrawalController::class, 'history']);
    Route::get('/withdrawal-methods', [WithdrawalController::class, 'methods']);
    Route::post('/withdrawal-methods', [WithdrawalController::class, 'updateMethod']);

    // Blocking & Reporting
    Route::get('/blocks', [BlockController::class, 'index']);
    Route::post('/blocks', [BlockController::class, 'store']);
    Route::delete('/blocks/{user}', [BlockController::class, 'destroy']);
    Route::post('/reports', [ReportController::class, 'store']);

    // Lindr Levels
    Route::get('/levels', [LevelController::class, 'rules']);
    Route::get('/levels/rules', [LevelController::class, 'rules']);
    Route::get('/levels/me', [LevelController::class, 'me']);
    Route::get('/me/level', [LevelController::class, 'me']);
    Route::get('/me/level/progress', [LevelController::class, 'progress']);
    Route::get('/levels/me/progress', [LevelController::class, 'progress']);

    // Spotlight Boost
    Route::get('/spotlight/packages', [SpotlightController::class, 'packages']);
    Route::get('/spotlight/me', [SpotlightController::class, 'me']);
    Route::post('/spotlight/purchase', [SpotlightController::class, 'purchase']);

    // Community Guidelines
    Route::get('/guidelines/latest', [GuidelinesController::class, 'latest']);

    // Notifications & Push Devices
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::get('/notifications/preferences', [NotificationController::class, 'getPreferences']);
    Route::post('/notifications/preferences', [NotificationController::class, 'updatePreferences']);
    Route::get('/notifications/settings', [NotificationController::class, 'getPreferences']);
    Route::post('/notifications/settings', [NotificationController::class, 'updatePreferences']);
    Route::post('/devices/push-token', [DeviceController::class, 'store']);
    Route::delete('/devices/push-token', [DeviceController::class, 'destroy']);
});
