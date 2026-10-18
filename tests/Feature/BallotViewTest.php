<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Election;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BallotViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_ballot_is_accessible_and_has_confirm_step(): void
    {
        $election = Election::factory()->live()->has(Candidate::factory()->count(2))->create();

        $this->get(route('vote.show', $election))
            ->assertOk()
            ->assertSee('<fieldset', false)
            ->assertSee('<legend', false)
            ->assertSee('for="voter-email"', false)
            ->assertSee('Cast my vote')
            ->assertSee('visually-hidden', false)
            ->assertDontSee('cdn.tailwindcss.com');
    }

    public function test_ballot_with_no_approved_candidates_shows_empty_state(): void
    {
        $election = Election::factory()->live()->create();

        $this->get(route('vote.show', $election))
            ->assertOk()
            ->assertSee('no approved candidates');
    }

    public function test_pages_pin_cdn_scripts_with_integrity(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('alpinejs@3.14.1', false)
            ->assertSee('integrity="sha384-', false)
            ->assertSee('ballotbox.css?v=', false);
    }

    public function test_public_header_links_to_login_for_guests(): void
    {
        $this->get('/')->assertOk()->assertSee('Log in')->assertSee('id="main"', false);
    }
}
