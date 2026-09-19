<?php

namespace Database\Seeders;

use App\Models\Interest;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class InterestSeeder extends Seeder
{
    public function run(): void
    {
        $initialInterests = [
            ['name' => 'Music', 'category' => 'Lifestyle'],
            ['name' => 'Travel', 'category' => 'Lifestyle'],
            ['name' => 'Movies', 'category' => 'Entertainment'],
            ['name' => 'Football', 'category' => 'Sports'],
            ['name' => 'Fitness', 'category' => 'Health'],
            ['name' => 'Gaming', 'category' => 'Tech & Gaming'],
            ['name' => 'Business', 'category' => 'Career'],
            ['name' => 'Technology', 'category' => 'Tech & Gaming'],
            ['name' => 'Fashion', 'category' => 'Lifestyle'],
            ['name' => 'Food', 'category' => 'Lifestyle'],
            ['name' => 'Photography', 'category' => 'Arts'],
            ['name' => 'Reading', 'category' => 'Arts'],
            ['name' => 'Dancing', 'category' => 'Lifestyle'],
            ['name' => 'Cooking', 'category' => 'Lifestyle'],
            ['name' => 'Nature', 'category' => 'Outdoors'],
            ['name' => 'Art', 'category' => 'Arts'],
            ['name' => 'Cars', 'category' => 'Interests'],
            ['name' => 'Entrepreneurship', 'category' => 'Career'],
        ];

        foreach ($initialInterests as $index => $item) {
            Interest::firstOrCreate([
                'slug' => Str::slug($item['name']),
            ], [
                'name' => $item['name'],
                'category' => $item['category'],
                'is_active' => true,
                'sort_order' => $index + 1,
            ]);
        }
    }
}
