<?php

namespace Database\Factories;

use App\Models\Candidate;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Vote> */
class VoteFactory extends Factory
{
    protected $model = Vote::class;

    public function definition(): array
    {
        return [
            'election_id' => Election::factory(),
            'candidate_id' => Candidate::factory(),
            'voter_email' => fake()->unique()->safeEmail(),
            'ip' => fake()->ipv4(),
        ];
    }
}
