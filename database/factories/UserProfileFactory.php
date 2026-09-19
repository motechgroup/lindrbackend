<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserProfile>
 */
class UserProfileFactory extends Factory
{
    protected $model = UserProfile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'display_name' => fake()->name(),
            'date_of_birth' => fake()->dateTimeBetween('-35 years', '-18 years')->format('Y-m-d'),
            'gender' => fake()->randomElement(['male', 'female']),
            'bio' => fake()->paragraph(),
            'city' => fake()->city(),
            'country' => 'Kenya',
            'latitude' => fake()->latitude(-1.4, -1.1),
            'longitude' => fake()->longitude(36.7, 37.0),
            'interests' => fake()->randomElements(['Music', 'Travel', 'Fitness', 'Food', 'Movies', 'Tech', 'Art'], 3),
            'online_status' => 'online',
            'profile_visibility' => true,
            'is_verified' => true,
        ];
    }
}
