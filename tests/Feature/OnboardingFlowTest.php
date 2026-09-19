<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_sign_in_indicates_unonboarded_status_for_new_user(): void
    {
        $response = $this->postJson('/api/v1/auth/google', [
            'google_id' => 'google_new_9999',
            'email' => 'newonboarding@example.com',
            'name' => 'New Member',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_onboarded', false)
            ->assertJsonPath('data.is_new_user', true);
    }

    public function test_onboarding_rejects_under_18_dob(): void
    {
        $user = User::factory()->create();

        // 17 years old
        $underageDob = now()->subYears(17)->format('Y-m-d');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/auth/onboarding', [
                'date_of_birth' => $underageDob,
                'gender' => 'Woman',
                'device_country_code' => 'KE',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.date_of_birth.0', 'Lindr is for adults 18 and over.');
    }

    public function test_onboarding_completes_successfully_for_adults(): void
    {
        $user = User::factory()->create();

        $adultDob = '1998-05-15';

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/auth/onboarding', [
                'date_of_birth' => $adultDob,
                'gender' => 'Woman',
                'device_country_code' => 'KE',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_onboarded', true);

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $user->id,
            'gender' => 'female',
            'country' => 'Kenya',
            'country_code' => 'KE',
        ]);

        $this->assertTrue($user->fresh(['profile'])->isOnboarded());
    }

    public function test_returning_onboarded_user_session_restoration(): void
    {
        $user = User::factory()->female()->create();
        UserProfile::create([
            'user_id' => $user->id,
            'display_name' => $user->name,
            'date_of_birth' => '1996-08-20',
            'gender' => 'female',
            'country' => 'Kenya',
            'country_code' => 'KE',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_onboarded', true);
    }

    public function test_onboarding_completes_when_country_signal_is_unresolved_without_defaulting_to_kenya(): void
    {
        $user = User::factory()->create();

        $adultDob = '1998-05-15';

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/auth/onboarding', [
                'date_of_birth' => $adultDob,
                'gender' => 'Woman',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_onboarded', true);

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $user->id,
            'gender' => 'female',
            'country' => null,
            'country_code' => null,
        ]);

        $this->assertTrue($user->fresh(['profile'])->isOnboarded());
    }
}
