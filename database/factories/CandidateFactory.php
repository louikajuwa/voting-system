<?php

namespace Database\Factories;

use App\Models\Candidate;
use App\Models\Election;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Candidate> */
class CandidateFactory extends Factory
{
    protected $model = Candidate::class;

    public function definition(): array
    {
        return [
            'election_id' => Election::factory(),
            'name' => fake()->name(),
            'photo' => null,
            'position' => 'President',
            'party' => fake()->randomElement(['Independent', 'Progressive Coalition', 'Student First', 'Reform Slate']),
            'bio' => fake()->sentence(10),
            'manifesto' => fake()->paragraph(),
            'status' => 'approved',
        ];
    }
}
