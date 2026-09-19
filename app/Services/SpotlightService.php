<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Enums\UserStatus;
use App\Exceptions\InsufficientTokensException;
use App\Models\SpotlightPackage;
use App\Models\SpotlightPurchase;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SpotlightService
{
    public function __construct(
        public WalletService $walletService,
        public ?NotificationService $notificationService = null
    ) {}

    /**
     * Seed default Spotlight packages (Daily, Weekly, Monthly) if empty.
     */
    public function seedDefaultPackagesIfEmpty(): void
    {
        if (SpotlightPackage::count() === 0) {
            $defaults = [
                [
                    'name' => 'Daily Spotlight',
                    'duration_minutes' => 1440,
                    'token_cost' => 100,
                    'boost_multiplier' => 2.0,
                    'description' => '24 Hours Profile Boost to top of Discover grid',
                    'is_active' => true,
                    'sort_order' => 1,
                ],
                [
                    'name' => 'Weekly Spotlight',
                    'duration_minutes' => 10080,
                    'token_cost' => 500,
                    'boost_multiplier' => 2.5,
                    'description' => '7 Days Maximum Exposure across Discover feed',
                    'is_active' => true,
                    'sort_order' => 2,
                ],
                [
                    'name' => 'Monthly Spotlight',
                    'duration_minutes' => 43200,
                    'token_cost' => 1500,
                    'boost_multiplier' => 3.5,
                    'description' => '30 Days Premium Priority Placement & Max Exposure',
                    'is_active' => true,
                    'sort_order' => 3,
                ],
            ];

            foreach ($defaults as $pkg) {
                SpotlightPackage::create($pkg);
            }
        }
    }

    /**
     * Get active spotlight packages ordered by sort_order.
     */
    public function getPackages()
    {
        $this->seedDefaultPackagesIfEmpty();

        return SpotlightPackage::where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->orderBy('duration_minutes', 'asc')
            ->get();
    }

    /**
     * Purchase a Spotlight boost using wallet Tokens.
     * If user already has an active Spotlight, extend seamlessly from remaining time.
     */
    public function purchaseSpotlight(User $user, int $packageId): SpotlightPurchase
    {
        if ($user->status !== UserStatus::Active) {
            throw new \DomainException('Sanctioned accounts cannot purchase or receive Spotlight boosts.');
        }

        $package = SpotlightPackage::where('is_active', true)->findOrFail($packageId);

        $wallet = $this->walletService->getWallet($user);
        if ($wallet->coin_balance < $package->token_cost) {
            throw new InsufficientTokensException('Insufficient token balance to purchase Spotlight boost.');
        }

        return DB::transaction(function () use ($user, $package) {
            // Debit token cost (100% platform revenue, 0 creator credits)
            $walletTx = $this->walletService->debitCoins(
                $user,
                $package->token_cost,
                TransactionType::Debit,
                SpotlightPurchase::class,
                null,
                "Purchased Spotlight Boost: {$package->name}"
            );

            // Check if user already has an active Spotlight
            $existingActive = SpotlightPurchase::where('user_id', $user->id)
                ->where('status', 'active')
                ->where('expires_at', '>', now())
                ->orderBy('expires_at', 'desc')
                ->first();

            $startsAt = now();

            if ($existingActive && $existingActive->expires_at->gt(now())) {
                // Seamlessly extend from current active expiration date
                $expiresAt = $existingActive->expires_at->copy()->addMinutes($package->duration_minutes);
                // Mark existing purchase as extended
                $existingActive->update(['status' => 'extended']);
            } else {
                $expiresAt = now()->addMinutes($package->duration_minutes);
            }

            $purchase = SpotlightPurchase::create([
                'user_id' => $user->id,
                'package_id' => $package->id,
                'tokens_spent' => $package->token_cost,
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
                'status' => 'active',
            ]);

            $walletTx->update(['reference_id' => (string) $purchase->id]);

            $freshPurchase = $purchase->fresh('package');

            if ($this->notificationService) {
                $this->notificationService->sendSpotlightStartedNotification(
                    $user,
                    $package->name,
                    $package->duration_minutes
                );
            }

            return $freshPurchase;
        });
    }

    /**
     * Get current active spotlight boost for user (if active & not sanctioned).
     */
    public function getActiveSpotlight(User $user): ?SpotlightPurchase
    {
        if ($user->status !== UserStatus::Active) {
            return null;
        }

        return SpotlightPurchase::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->with('package')
            ->orderBy('expires_at', 'desc')
            ->first();
    }

    /**
     * Revoke all active spotlights for a user due to moderation/sanctions.
     */
    public function revokeUserSpotlights(User $user): int
    {
        return SpotlightPurchase::where('user_id', $user->id)
            ->whereIn('status', ['active', 'extended'])
            ->update(['status' => 'revoked']);
    }
}
