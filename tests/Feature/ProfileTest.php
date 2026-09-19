<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_profile(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/profile', [
                'display_name' => 'Johnny',
                'date_of_birth' => '1998-05-15',
                'gender' => 'male',
                'bio' => 'Software Developer from Nairobi',
                'city' => 'Nairobi',
                'country' => 'Kenya',
                'interests' => ['Tech', 'Music'],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.display_name', 'Johnny')
            ->assertJsonPath('data.city', 'Nairobi');

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $user->id,
            'display_name' => 'Johnny',
            'city' => 'Nairobi',
        ]);
    }

    public function test_underage_user_profile_update_is_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/profile', [
                'display_name' => 'Kid',
                'date_of_birth' => now()->subYears(16)->format('Y-m-d'), // 16 years old
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_user_can_view_another_users_profile(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        UserProfile::factory()->create([
            'user_id' => $userB->id,
            'display_name' => 'Alice',
            'city' => 'Mombasa',
        ]);

        $response = $this->actingAs($userA, 'sanctum')
            ->getJson('/api/v1/profile/'.$userB->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.profile.display_name', 'Alice')
            ->assertJsonPath('data.profile.city', 'Mombasa');
    }
}
