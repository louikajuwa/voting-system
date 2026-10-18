<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VotingTest extends TestCase
{
    use RefreshDatabase;

    private function liveElection(): Election
    {
        return Election::factory()
            ->live()
            ->has(Candidate::factory()->count(2))
            ->create();
    }

    public function test_a_valid_vote_is_recorded(): void
    {
        $election = $this->liveElection();
        $candidate = $election->candidates->first();

        $this->post(route('vote.store', $election), [
            'candidate_id' => $candidate->id,
            'email' => 'voter@example.com',
        ])->assertRedirect(route('vote.thanks', $election));

        $this->assertDatabaseHas('votes', [
            'election_id' => $election->id,
            'candidate_id' => $candidate->id,
            'voter_email' => 'voter@example.com',
        ]);
    }

    public function test_the_same_email_cannot_vote_twice(): void
    {
        $election = $this->liveElection();
        $ids = $election->candidates->pluck('id');

        $this->post(route('vote.store', $election), ['candidate_id' => $ids[0], 'email' => 'dup@example.com']);
        $this->post(route('vote.store', $election), ['candidate_id' => $ids[1], 'email' => 'DUP@example.com'])
            ->assertSessionHasErrors('email'); // case-insensitive dedup

        $this->assertEquals(1, Vote::where('election_id', $election->id)->count());
    }

    public function test_a_closed_election_rejects_votes(): void
    {
        $election = Election::factory()->closed()->has(Candidate::factory())->create();

        $this->post(route('vote.store', $election), [
            'candidate_id' => $election->candidates->first()->id,
            'email' => 'late@example.com',
        ])->assertSessionHasErrors('candidate_id');

        $this->assertDatabaseCount('votes', 0);
    }

    public function test_an_invalid_email_is_rejected(): void
    {
        $election = $this->liveElection();

        $this->post(route('vote.store', $election), [
            'candidate_id' => $election->candidates->first()->id,
            'email' => 'not-an-email',
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseCount('votes', 0);
    }
}
