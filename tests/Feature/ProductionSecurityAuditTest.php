<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserPhoto;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionSecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_negative_or_zero_wallet_credit_is_rejected(): void
    {
        $user = User::factory()->create();
        /** @var WalletService $walletService */
        $walletService = app(WalletService::class);

        $this->expectException(\InvalidArgumentException::class);
        $walletService->creditCoins($user, -50);
    }

    public function test_negative_or_zero_wallet_debit_is_rejected(): void
    {
        $user = User::factory()->create();
        /** @var WalletService $walletService */
        $walletService = app(WalletService::class);

        $this->expectException(\InvalidArgumentException::class);
        $walletService->debitCoins($user, 0);
    }

    public function test_male_user_cannot_access_female_creator_earnings(): void
    {
        $male = User::factory()->male()->create();

        $response = $this->actingAs($male, 'sanctum')
            ->getJson('/api/v1/earnings');

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_user_cannot_delete_another_users_photo(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $photo = UserPhoto::factory()->create(['user_id' => $userB->id]);

        $response = $this->actingAs($userA, 'sanctum')
            ->deleteJson('/api/v1/photos/'.$photo->id);

        $response->assertStatus(400)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('user_photos', ['id' => $photo->id]);
    }
}
