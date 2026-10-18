<?php

namespace Tests\Feature;

use App\Events\VoteCast;
use App\Listeners\PublishVoteTally;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Vote;
use App\Services\RealtimeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ResultsVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function hiddenElection(): Election
    {
        $election = Election::factory()->live()->create(['hide_results' => true]);
        $c = Candidate::factory()->create(['election_id' => $election->id]);
        Vote::create(['election_id' => $election->id, 'candidate_id' => $c->id, 'voter_email' => 'a@b.com']);

        return $election;
    }

    public function test_hidden_results_page_has_no_socket_token(): void
    {
        $election = $this->hiddenElection();

        $this->get(route('results.show', $election))
            ->assertOk()
            ->assertViewHas('socketToken', null)
            ->assertViewHas('visible', false);
    }

    public function test_tally_endpoint_does_not_leak_counts_while_hidden(): void
    {
        $election = $this->hiddenElection();

        $this->getJson(route('results.tally', $election))
            ->assertOk()
            ->assertJsonPath('visible', false)
            ->assertJsonPath('total', 0)
            ->assertJsonCount(0, 'tallies');
    }

    public function test_tally_is_visible_once_closed(): void
    {
        $election = $this->hiddenElection();
        $election->update(['status' => 'closed']);

        $this->getJson(route('results.tally', $election))
            ->assertJsonPath('visible', true)
            ->assertJsonPath('total', 1);
    }

    public function test_listener_skips_the_election_room_when_hidden_but_publishes_org_stats(): void
    {
        $election = $this->hiddenElection();
        $rt = Mockery::mock(RealtimeService::class);
        $rt->shouldReceive('publish')->once()->with(["org.{$election->organization_id}"], 'vote', Mockery::type('array'));

        (new PublishVoteTally($rt))->handle(new VoteCast($election->id));
    }

    public function test_listener_publishes_both_rooms_when_visible(): void
    {
        $election = $this->hiddenElection();
        $election->update(['hide_results' => false]);
        $rt = Mockery::mock(RealtimeService::class);
        $rt->shouldReceive('publish')->once()->with(["election.{$election->id}"], 'vote', Mockery::type('array'));
        $rt->shouldReceive('publish')->once()->with(["org.{$election->organization_id}"], 'vote', Mockery::type('array'));

        (new PublishVoteTally($rt))->handle(new VoteCast($election->id));
    }
}
