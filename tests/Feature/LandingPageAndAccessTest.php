<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Faq;
use App\Models\LandingMedia;
use App\Models\LandingSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageAndAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed basic landing page content
        LandingSetting::create(['key' => 'app_name', 'value' => 'Lindr', 'label' => 'App Name', 'group' => 'general']);
        LandingSetting::create(['key' => 'app_store_url', 'value' => 'https://apps.apple.com/app/lindr', 'label' => 'App Store URL', 'group' => 'app_links']);
        LandingSetting::create(['key' => 'google_play_url', 'value' => 'https://play.google.com/store/apps/details?id=app.lindr', 'label' => 'Google Play URL', 'group' => 'app_links']);

        Faq::create([
            'question' => 'How does Lindr work?',
            'answer' => 'Lindr allows users to connect, chat, and send virtual gifts.',
            'category' => 'General',
            'display_order' => 1,
            'is_active' => true,
        ]);

        LandingMedia::create([
            'key' => 'hero_lifestyle',
            'title' => 'Hero Lifestyle',
            'file_path' => 'landing/hero_lifestyle.png',
            'alt_text' => 'Hero image',
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }

    public function test_public_landing_page_can_be_rendered(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Lindr');
        $response->assertSee('Connect Authentically');
        $response->assertSee('How does Lindr work?');
        $response->assertSee('Admin Access');
    }

    public function test_access_portal_can_be_viewed_by_guest(): void
    {
        $response = $this->get('/access');

        $response->assertStatus(200);
        $response->assertSee('Admin Access Portal');
        $response->assertSee('admin@lindr.app');
    }

    public function test_admin_user_can_login_via_access_portal(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@lindr.app',
            'password' => bcrypt('password'),
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
        ]);

        $response = $this->post('/access', [
            'email' => 'admin@lindr.app',
            'password' => 'password',
        ]);

        $response->assertRedirect('/admin');
        $this->assertAuthenticatedAs($admin);
    }

    public function test_non_admin_user_cannot_login_via_access_portal(): void
    {
        User::factory()->create([
            'email' => 'user@lindr.app',
            'password' => bcrypt('password'),
            'role' => UserRole::Male,
            'status' => UserStatus::Active,
        ]);

        $response = $this->post('/access', [
            'email' => 'user@lindr.app',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_authenticated_admin_visiting_access_portal_redirects_to_admin(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
        ]);

        $response = $this->actingAs($admin)->get('/access');

        $response->assertRedirect('/admin');
    }

    public function test_store_download_buttons_auto_hide_when_urls_are_empty(): void
    {
        foreach (LandingSetting::whereIn('key', ['app_store_url', 'google_play_url'])->get() as $setting) {
            $setting->update(['value' => '']);
        }

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertDontSee('https://apps.apple.com/app/lindr');
        $response->assertDontSee('https://play.google.com/store/apps/details?id=app.lindr');
    }

    public function test_admin_can_logout_via_access_portal(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
        ]);

        $response = $this->actingAs($admin)->post('/access/logout');

        $response->assertRedirect('/access');
        $this->assertGuest();
    }
}
