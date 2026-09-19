<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'status',
        'provider',
        'provider_user_id',
        'avatar',
        'is_creator',
        'creator_status',
        'liveness_verified_at',
        'mpesa_phone',
        'mpesa_phone_verified',
        'mpesa_verified_at',
        'payout_hold_until',
        'email_verified_at',
        'phone_verified_at',
        'notification_settings',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Appended dynamic attributes.
     */
    protected $appends = [
        'is_onboarded',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'liveness_verified_at' => 'datetime',
            'mpesa_verified_at' => 'datetime',
            'payout_hold_until' => 'datetime',
            'is_creator' => 'boolean',
            'mpesa_phone_verified' => 'boolean',
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => UserStatus::class,
            'notification_settings' => 'array',
        ];
    }

    public function isMale(): bool
    {
        return $this->role === UserRole::Male;
    }

    public function isFemale(): bool
    {
        return $this->role === UserRole::Female;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function isOnboarded(): bool
    {
        if (! $this->relationLoaded('profile') && ! $this->profile) {
            return false;
        }

        $profile = $this->profile;
        if (! $profile) {
            return false;
        }

        return ! is_null($profile->date_of_birth)
            && ! empty($profile->gender);
    }

    public function getIsOnboardedAttribute(): bool
    {
        return $this->isOnboarded();
    }

    public function isCreator(): bool
    {
        return (bool) $this->is_creator;
    }

    public function isCreatorVerified(): bool
    {
        return $this->is_creator && in_array($this->creator_status, ['approved', 'verified']);
    }

    public function hasActivePayoutHold(): bool
    {
        return $this->payout_hold_until && $this->payout_hold_until->isFuture();
    }

    public function getCompletenessScore(): int
    {
        $score = 0;
        $profile = $this->profile;
        if (! $profile) {
            return 0;
        }

        // 1. Primary Photo or Avatar (+25)
        if (! empty($this->avatar) || $this->photos()->where('is_primary', true)->exists() || $this->photos()->exists()) {
            $score += 25;
        }

        // 2. Bio filled (+25)
        if (! empty($profile->bio) && strlen(trim($profile->bio)) >= 5) {
            $score += 25;
        }

        // 3. Interests selected (+25)
        $interestCount = $this->interests()->count();
        if ($interestCount >= 3) {
            $score += 25;
        } elseif ($interestCount > 0) {
            $score += 15;
        }

        // 4. Additional Photos (+15)
        $photoCount = $this->photos()->count();
        if ($photoCount >= 2) {
            $score += 15;
        } elseif ($photoCount >= 1) {
            $score += 10;
        }

        // 5. City or Display Name (+10)
        if (! empty($profile->display_name) || ! empty($profile->city)) {
            $score += 10;
        }

        return min(100, $score);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isAdmin() && $this->isActive();
    }

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function interests(): BelongsToMany
    {
        return $this->belongsToMany(Interest::class, 'user_interests')->withTimestamps();
    }

    public function photos(): HasMany
    {
        return $this->hasMany(UserPhoto::class)->orderBy('display_order', 'asc');
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function livenessVerifications(): HasMany
    {
        return $this->hasMany(LivenessVerification::class)->latest();
    }

    public function creatorCreditLedgers(): HasMany
    {
        return $this->hasMany(CreatorCreditLedger::class)->latest();
    }

    public function mpesaVerificationOtps(): HasMany
    {
        return $this->hasMany(MpesaVerificationOtp::class)->latest();
    }

    public function spotlightPurchases(): HasMany
    {
        return $this->hasMany(SpotlightPurchase::class)->latest();
    }

    public function levelPenalties(): HasMany
    {
        return $this->hasMany(LevelPenalty::class)->latest();
    }

    public function levelHistories(): HasMany
    {
        return $this->hasMany(UserLevelHistory::class)->latest();
    }

    public function moderationActions(): HasMany
    {
        return $this->hasMany(ModerationAction::class, 'target_user_id')->latest();
    }

    public function reportsSubmitted(): HasMany
    {
        return $this->hasMany(UserReport::class, 'reporter_id')->latest();
    }

    public function reportsReceived(): HasMany
    {
        return $this->hasMany(UserReport::class, 'reported_id')->latest();
    }

    public function devices(): HasMany
    {
        return $this->hasMany(UserDevice::class)->where('active', true);
    }

    public function hasNotificationEnabled(string $category): bool
    {
        // Security and moderation notifications cannot be disabled
        if (in_array($category, ['security', 'moderation', 'system', 'account'])) {
            return true;
        }

        $settings = $this->notification_settings ?? [];

        return (bool) ($settings[$category] ?? true);
    }
}
