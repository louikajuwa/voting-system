<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Election;
use App\Models\Vote;
use App\Services\CastVote;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CastVoteTest extends TestCase
{
    use RefreshDatabase;

    private function liveElection(): Election
    {
        return Election::factory()->live()->has(Candidate::factory()->count(2))->create();
    }

    public function test_json_vote_returns_the_success_envelope(): void
    {
        $election = $this->liveElection();

        $this->postJson(route('vote.store', $election), [
            'candidate_id' => $election->candidates->first()->id,
            'email' => 'json@example.com',
        ])->assertOk()->assertJsonPath('result', 'success');

        $this->assertDatabaseCount('votes', 1);
    }

    public function test_email_is_normalized_so_padded_and_cased_duplicates_are_rejected(): void
    {
        $election = $this->liveElection();
        $ids = $election->candidates->pluck('id');
        $service = app(CastVote::class);

        $vote = $service->handle($election, $ids[0], ' A@B.com ');
        $this->assertSame('a@b.com', $vote->voter_email);

        try {
            $service->handle($election, $ids[1], 'a@B.COM');
            $this->fail('Expected a duplicate vote to be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('email', $e->errors());
        }
        $this->assertSame(1, Vote::count());
    }

    public function test_voting_after_the_window_ended_is_rejected_even_if_status_is_still_live(): void
    {
        $election = Election::factory()->live()->has(Candidate::factory())->create(['closes_at' => now()->subMinute()]);

        $this->postJson(route('vote.store', $election), [
            'candidate_id' => $election->candidates->first()->id,
            'email' => 'late@example.com',
        ])->assertStatus(422)->assertJsonValidationErrors('candidate_id');

        $this->assertDatabaseCount('votes', 0);
    }

    public function test_a_pending_candidate_cannot_receive_votes(): void
    {
        $election = Election::factory()->live()->create();
        $pending = Candidate::factory()->create(['election_id' => $election->id, 'status' => 'pending']);

        $this->postJson(route('vote.store', $election), ['candidate_id' => $pending->id, 'email' => 'x@example.com'])
            ->assertStatus(422)->assertJsonValidationErrors('candidate_id');
    }

    public function test_non_unique_database_errors_are_not_reported_as_already_voted(): void
    {
        $election = $this->liveElection();
        $candidateId = $election->candidates->first()->id;
        Schema::drop('votes'); // force a "no such table" QueryException

        $this->expectException(QueryException::class);
        app(CastVote::class)->handle($election, $candidateId, 'boom@example.com');
    }
}
