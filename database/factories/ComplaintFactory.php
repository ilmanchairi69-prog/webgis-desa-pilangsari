<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ComplaintFactory extends Factory
{
    public function definition(): array
    {
        return ['submission_key' => (string) Str::uuid(), 'ticket_number' => 'ADU-'.strtoupper(Str::random(16)), 'name' => fake()->name(), 'phone' => '081234567890', 'category' => 'other', 'description' => fake()->sentence(), 'status' => 'new'];
    }
}
