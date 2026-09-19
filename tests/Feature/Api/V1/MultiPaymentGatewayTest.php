<?php

namespace Tests\Feature\Api\V1;

use App\Models\CoinPackage;
use App\Models\PaymentProvider;
use App\Models\PaymentTransaction;
use App\Models\User;
use Database\Seeders\PaymentGatewaySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiPaymentGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected CoinPackage $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PaymentGatewaySeeder::class);

        $this->user = User::factory()->create([
            'role' => 'male',
            'status' => 'active',
            'phone' => '254711111111',
        ]);
        $this->user->wallet()->create(['coin_balance' => 0]);

        $this->package = CoinPackage::create([
            'name' => '500 Coins Pack',
            'coin_amount' => 500,
            'bonus_coins' => 50,
            'price_kes' => 500.00,
            'is_active' => true,
            'display_order' => 1,
        ]);
    }

    public function test_user_can_fetch_available_payment_methods(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/payments/methods');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'country' => 'KE',
                    'currency' => 'KES',
                ],
            ]);

        $codes = collect($response->json('data.methods'))->pluck('code')->all();
        $this->assertContains('mpesa', $codes);
        $this->assertContains('korapay', $codes);
        $this->assertContains('flutterwave', $codes);
        $this->assertContains('google_pay', $codes);
    }

    public function test_payment_method_filtering_by_country(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/payments/methods?country=NG');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'country' => 'NG',
                    'currency' => 'NGN',
                ],
            ]);

        $codes = collect($response->json('data.methods'))->pluck('code')->all();
        $this->assertNotContains('mpesa', $codes);
        $this->assertContains('korapay', $codes);
        $this->assertContains('flutterwave', $codes);
    }

    public function test_payment_initiation_security_and_validation(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/payments/initiate', [
                'coin_package_id' => $this->package->id,
                'payment_method' => 'korapay',
                'price' => 1.00, // Should be ignored; backend must read package price from DB
                'total_coins' => 10000, // Should be ignored
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'provider' => 'korapay',
                    'payment_method' => 'korapay',
                    'amount' => 500.00,
                    'expected_coins' => 550,
                    'status' => 'pending',
                ],
            ]);

        $this->assertDatabaseHas('payment_transactions', [
            'user_id' => $this->user->id,
            'package_id' => $this->package->id,
            'amount' => 500.00,
            'expected_coins' => 550,
            'status' => 'pending',
        ]);
    }

    public function test_korapay_webhook_processing_and_atomic_wallet_credit(): void
    {
        $transaction = PaymentTransaction::create([
            'public_reference' => 'PAY-KORA-1001',
            'user_id' => $this->user->id,
            'package_id' => $this->package->id,
            'provider_code' => 'korapay',
            'payment_method_code' => 'korapay',
            'country' => 'KE',
            'currency' => 'KES',
            'amount' => 500.00,
            'expected_coins' => 550,
            'status' => 'pending',
        ]);

        $webhookPayload = [
            'event' => 'charge.success',
            'data' => [
                'reference' => 'PAY-KORA-1001',
                'status' => 'success',
                'amount' => 500.00,
                'transaction_reference' => 'KORA-REC-9988',
            ],
        ];

        $response = $this->postJson('/api/v1/webhooks/kora', $webhookPayload);

        $response->assertStatus(200)
            ->assertJson(['status' => 'success']);

        $transaction->refresh();
        $this->assertEquals('successful', $transaction->status);

        $this->user->wallet->refresh();
        $this->assertEquals(550, $this->user->wallet->coin_balance);
    }

    public function test_duplicate_webhook_does_not_double_credit_wallet(): void
    {
        $transaction = PaymentTransaction::create([
            'public_reference' => 'PAY-KORA-DUP',
            'user_id' => $this->user->id,
            'package_id' => $this->package->id,
            'provider_code' => 'korapay',
            'payment_method_code' => 'korapay',
            'country' => 'KE',
            'currency' => 'KES',
            'amount' => 500.00,
            'expected_coins' => 550,
            'status' => 'pending',
        ]);

        $payload = [
            'event' => 'charge.success',
            'data' => [
                'reference' => 'PAY-KORA-DUP',
                'status' => 'success',
                'transaction_reference' => 'KORA-REC-DUP',
            ],
        ];

        // First webhook call
        $this->postJson('/api/v1/webhooks/kora', $payload)->assertStatus(200);
        $this->user->wallet->refresh();
        $this->assertEquals(550, $this->user->wallet->coin_balance);

        // Replay duplicate webhook call
        $this->postJson('/api/v1/webhooks/kora', $payload)->assertStatus(200);
        $this->user->wallet->refresh();
        $this->assertEquals(550, $this->user->wallet->coin_balance);
    }

    public function test_flutterwave_payment_and_webhook_flow(): void
    {
        $transaction = PaymentTransaction::create([
            'public_reference' => 'PAY-FLW-2002',
            'user_id' => $this->user->id,
            'package_id' => $this->package->id,
            'provider_code' => 'flutterwave',
            'payment_method_code' => 'flutterwave',
            'country' => 'KE',
            'currency' => 'KES',
            'amount' => 500.00,
            'expected_coins' => 550,
            'status' => 'pending',
        ]);

        $payload = [
            'event' => 'charge.completed',
            'data' => [
                'tx_ref' => 'PAY-FLW-2002',
                'status' => 'successful',
                'flw_ref' => 'FLW-REC-5544',
            ],
        ];

        $response = $this->postJson('/api/v1/webhooks/flutterwave', $payload);

        $response->assertStatus(200);
        $transaction->refresh();
        $this->assertEquals('successful', $transaction->status);

        $this->user->wallet->refresh();
        $this->assertEquals(550, $this->user->wallet->coin_balance);
    }

    public function test_google_pay_token_initiation_flow(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/payments/initiate', [
                'coin_package_id' => $this->package->id,
                'payment_method' => 'google_pay',
                'google_pay_token' => 'mock_encrypted_google_pay_token_string',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'provider' => 'google_pay',
                    'payment_method' => 'google_pay',
                    'amount' => 500.00,
                    'expected_coins' => 550,
                ],
            ]);
    }

    public function test_admin_can_toggle_provider_status(): void
    {
        // Disable KoraPay provider
        PaymentProvider::where('code', 'korapay')->update(['enabled' => false]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/payments/methods');

        $codes = collect($response->json('data.methods'))->pluck('code')->all();
        $this->assertNotContains('korapay', $codes);

        // Initiation with disabled provider fails cleanly
        $initiateResponse = $this->actingAs($this->user)
            ->postJson('/api/v1/payments/initiate', [
                'coin_package_id' => $this->package->id,
                'payment_method' => 'korapay',
            ]);

        $initiateResponse->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => "Payment method 'korapay' is currently unavailable.",
            ]);
    }
}
