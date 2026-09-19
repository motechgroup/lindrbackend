<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_user_can_access_filament_admin_panel(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
        ]);

        $response = $this->actingAs($admin)->get('/admin');

        $response->assertStatus(200);
    }

    public function test_male_app_user_is_denied_access_to_admin_panel(): void
    {
        $maleUser = User::factory()->create([
            'role' => UserRole::Male,
            'status' => UserStatus::Active,
        ]);

        $response = $this->actingAs($maleUser)->get('/admin');

        $response->assertStatus(403);
    }

    public function test_female_app_user_is_denied_access_to_admin_panel(): void
    {
        $femaleUser = User::factory()->create([
            'role' => UserRole::Female,
            'status' => UserStatus::Active,
        ]);

        $response = $this->actingAs($femaleUser)->get('/admin');

        $response->assertStatus(403);
    }
}
