<?php

namespace Database\Factories;

use App\Models\Election;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Election> */
class ElectionFactory extends Factory
{
    protected $model = Election::class;

    public function definition(): array
    {
        $title = rtrim(fake()->sentence(3), '.');

        return [
            'organization_id' => Organization::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 99999),
            'description' => fake()->paragraph(),
            'status' => 'draft',
            'opens_at' => null,
            'closes_at' => null,
        ];
    }

    public function live(): static
    {
        return $this->state(fn () => [
            'status' => 'live',
            'opens_at' => now()->subHour(),
            'closes_at' => now()->addDays(2),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => 'draft']);
    }

    public function scheduled(): static
    {
        return $this->state(fn () => [
            'status' => 'scheduled',
            'opens_at' => now()->addDay(),
            'closes_at' => now()->addDays(3),
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn () => ['status' => 'closed']);
    }
}
