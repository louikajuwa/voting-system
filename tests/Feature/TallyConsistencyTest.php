<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TallyConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_approved_candidates_are_tallied(): void
    {
        $election = Election::factory()->live()->create();
        $ok = Candidate::factory()->create(['election_id' => $election->id]);
        $pending = Candidate::factory()->create(['election_id' => $election->id, 'status' => 'pending']);
        $withdrawn = Candidate::factory()->create(['election_id' => $election->id, 'status' => 'withdrawn']);
        Vote::create(['election_id' => $election->id, 'candidate_id' => $ok->id, 'voter_email' => 'a@b.com']);
        Vote::create(['election_id' => $election->id, 'candidate_id' => $withdrawn->id, 'voter_email' => 'c@b.com']);

        $tallies = $election->tallies();

        $this->assertSame([$ok->id => 1], $tallies);
        $this->assertArrayNotHasKey($pending->id, $tallies);
        $this->assertSame($ok->id, $election->winnerId());
    }

    public function test_tallies_reuse_a_loaded_relation(): void
    {
        $election = Election::factory()->live()->has(Candidate::factory()->count(3))->create();
        $election->load('approvedCandidates');

        DB::enableQueryLog();
        $election->tallies();

        $this->assertCount(1, DB::getQueryLog()); // only the vote count query
    }

    public function test_live_scope_requires_an_open_window(): void
    {
        Election::factory()->live()->create();
        Election::factory()->live()->create(['closes_at' => now()->subMinute()]);
        Election::factory()->live()->create(['opens_at' => now()->addHour()]);

        $this->assertSame(1, Election::live()->count());
    }
}
