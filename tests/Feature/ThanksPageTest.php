<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Election;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThanksPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_thanks_redirects_to_the_ballot_without_a_recent_vote(): void
    {
        $election = Election::factory()->live()->create();

        $this->get(route('vote.thanks', $election))->assertRedirect(route('vote.show', $election));
    }

    public function test_thanks_renders_right_after_voting(): void
    {
        $election = Election::factory()->live()->has(Candidate::factory())->create();

        $this->followingRedirects()->post(route('vote.store', $election), [
            'candidate_id' => $election->candidates->first()->id,
            'email' => 'v@example.com',
        ])->assertOk()->assertSee('Vote recorded', false);
    }
}
