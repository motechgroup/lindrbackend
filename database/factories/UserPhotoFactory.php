<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserPhoto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserPhoto>
 */
class UserPhotoFactory extends Factory
{
    protected $model = UserPhoto::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'photo_path' => 'photos/demo.jpg',
            'is_primary' => false,
            'display_order' => 1,
            'is_active' => true,
        ];
    }
}
