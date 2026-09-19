<?php

namespace Tests\Feature;

use App\Models\CoinPackage;
use App\Models\CoinPurchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MpesaPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_initiate_coin_purchase(): void
    {
        $user = User::factory()->create();
        $user->wallet()->create(['coin_balance' => 0]);
        $package = CoinPackage::create([
            'name' => 'Starter Pack',
            'coin_amount' => 100,
            'bonus_coins' => 20,
            'price_kes' => 200.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/payments/purchase', [
                'package_id' => $package->id,
                'phone_number' => '0712345678',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.coins_credited', 120);

        $this->assertDatabaseHas('payment_transactions', [
            'user_id' => $user->id,
            'package_id' => $package->id,
            'status' => 'pending',
            'expected_coins' => 120,
        ]);
    }

    public function test_successful_mpesa_callback_credits_coins_to_user_wallet(): void
    {
        $user = User::factory()->create();
        $user->wallet()->create(['coin_balance' => 0]);
        $package = CoinPackage::create([
            'name' => 'Gold Pack',
            'coin_amount' => 500,
            'bonus_coins' => 50,
            'price_kes' => 1000.00,
            'is_active' => true,
        ]);

        $purchase = CoinPurchase::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'amount_kes' => 1000.00,
            'coins_credited' => 550,
            'payment_method' => 'mpesa',
            'status' => 'pending',
            'phone_number' => '254712345678',
            'checkout_request_id' => 'ws_CO_TEST_9999',
        ]);

        $callbackPayload = [
            'Body' => [
                'stkCallback' => [
                    'MerchantRequestID' => 'mr_12345',
                    'CheckoutRequestID' => 'ws_CO_TEST_9999',
                    'ResultCode' => 0,
                    'ResultDesc' => 'The service request is processed successfully.',
                    'CallbackMetadata' => [
                        'Item' => [
                            ['Name' => 'Amount', 'Value' => 1000],
                            ['Name' => 'MpesaReceiptNumber', 'Value' => 'RKT998877'],
                            ['Name' => 'PhoneNumber', 'Value' => 254712345678],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/payments/mpesa/callback', $callbackPayload);

        $response->assertStatus(200)
            ->assertJsonPath('ResultCode', 0);

        $this->assertEquals('successful', $purchase->fresh()->status);
        $this->assertEquals('RKT998877', $purchase->fresh()->mpesa_receipt_number);
        $this->assertEquals(550, $user->fresh()->wallet->coin_balance);
    }

    public function test_failed_mpesa_callback_does_not_credit_coins(): void
    {
        $user = User::factory()->create();
        $user->wallet()->create(['coin_balance' => 0]);
        $package = CoinPackage::create([
            'name' => 'Basic Pack',
            'coin_amount' => 50,
            'bonus_coins' => 0,
            'price_kes' => 100.00,
            'is_active' => true,
        ]);

        $purchase = CoinPurchase::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'amount_kes' => 100.00,
            'coins_credited' => 50,
            'payment_method' => 'mpesa',
            'status' => 'pending',
            'phone_number' => '254712345678',
            'checkout_request_id' => 'ws_CO_FAILED_1111',
        ]);

        $callbackPayload = [
            'Body' => [
                'stkCallback' => [
                    'CheckoutRequestID' => 'ws_CO_FAILED_1111',
                    'ResultCode' => 1032,
                    'ResultDesc' => 'Request cancelled by user.',
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/payments/mpesa/callback', $callbackPayload);

        $response->assertStatus(200);

        $this->assertEquals('failed', $purchase->fresh()->status);
        $this->assertEquals(0, $user->fresh()->wallet?->coin_balance ?? 0);
    }

    public function test_duplicate_mpesa_callback_is_idempotent(): void
    {
        $user = User::factory()->create();
        $user->wallet()->create(['coin_balance' => 0]);
        $package = CoinPackage::create([
            'name' => 'Pack',
            'coin_amount' => 100,
            'bonus_coins' => 0,
            'price_kes' => 200.00,
            'is_active' => true,
        ]);

        $purchase = CoinPurchase::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'amount_kes' => 200.00,
            'coins_credited' => 100,
            'payment_method' => 'mpesa',
            'status' => 'pending',
            'phone_number' => '254712345678',
            'checkout_request_id' => 'ws_CO_DUP_2222',
        ]);

        $callbackPayload = [
            'Body' => [
                'stkCallback' => [
                    'CheckoutRequestID' => 'ws_CO_DUP_2222',
                    'ResultCode' => 0,
                    'ResultDesc' => 'Success',
                    'CallbackMetadata' => [
                        'Item' => [
                            ['Name' => 'MpesaReceiptNumber', 'Value' => 'DUP12345'],
                        ],
                    ],
                ],
            ],
        ];

        // Send callback twice
        $this->postJson('/api/v1/payments/mpesa/callback', $callbackPayload);
        $this->postJson('/api/v1/payments/mpesa/callback', $callbackPayload);

        $this->assertEquals(100, $user->fresh()->wallet->coin_balance);
        $this->assertDatabaseCount('wallet_transactions', 1);
    }
}
