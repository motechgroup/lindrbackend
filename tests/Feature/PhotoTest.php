<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserPhoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_upload_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $file = UploadedFile::fake()->image('profile.jpg');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/photos', [
                'photo' => $file,
                'is_primary' => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_primary', true);

        $this->assertDatabaseHas('user_photos', [
            'user_id' => $user->id,
            'is_primary' => true,
        ]);
    }

    public function test_user_can_set_primary_photo(): void
    {
        $user = User::factory()->create();
        $photo1 = UserPhoto::factory()->create(['user_id' => $user->id, 'is_primary' => true]);
        $photo2 = UserPhoto::factory()->create(['user_id' => $user->id, 'is_primary' => false]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/photos/'.$photo2->id.'/primary');

        $response->assertStatus(200)
            ->assertJsonPath('data.is_primary', true);

        $this->assertTrue($photo2->fresh()->is_primary);
        $this->assertFalse($photo1->fresh()->is_primary);
    }

    public function test_user_can_delete_own_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $photo = UserPhoto::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/v1/photos/'.$photo->id);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('user_photos', ['id' => $photo->id]);
    }
}
