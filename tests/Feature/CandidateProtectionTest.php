<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Election;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CandidateProtectionTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['Admin', 'Manager'] as $r) {
            Role::findOrCreate($r, 'web');
        }
        $this->org = Organization::factory()->create();
        $this->admin = User::factory()->create(['organization_id' => $this->org->id]);
        $this->admin->assignRole('Admin');
    }

    public function test_deleting_a_candidate_from_a_live_election_is_blocked_and_votes_are_kept(): void
    {
        $election = Election::factory()->live()->create(['organization_id' => $this->org->id]);
        $candidate = Candidate::factory()->create(['election_id' => $election->id]);
        Vote::create(['election_id' => $election->id, 'candidate_id' => $candidate->id, 'voter_email' => 'a@b.com']);

        $this->actingAs($this->admin)
            ->deleteJson(route('admin.elections.candidates.destroy', [$election, $candidate]))
            ->assertStatus(409)
            ->assertJsonPath('result', 'error');

        $this->assertDatabaseHas('candidates', ['id' => $candidate->id]);
        $this->assertDatabaseCount('votes', 1);
    }

    public function test_status_change_is_blocked_once_live_but_other_edits_are_fine(): void
    {
        $election = Election::factory()->live()->create(['organization_id' => $this->org->id]);
        $candidate = Candidate::factory()->create(['election_id' => $election->id]);

        $this->actingAs($this->admin)
            ->putJson(route('admin.elections.candidates.update', [$election, $candidate]), ['name' => 'X', 'status' => 'withdrawn'])
            ->assertStatus(409);

        $this->actingAs($this->admin)
            ->putJson(route('admin.elections.candidates.update', [$election, $candidate]), ['name' => 'Renamed', 'status' => 'approved'])
            ->assertOk();
        $this->assertSame('Renamed', $candidate->fresh()->name);
    }

    public function test_draft_candidate_can_be_deleted(): void
    {
        $election = Election::factory()->create(['organization_id' => $this->org->id]);
        $candidate = Candidate::factory()->create(['election_id' => $election->id]);

        $this->actingAs($this->admin)
            ->deleteJson(route('admin.elections.candidates.destroy', [$election, $candidate]))
            ->assertOk();

        $this->assertDatabaseMissing('candidates', ['id' => $candidate->id]);
    }

    public function test_database_restricts_deleting_a_voted_candidate(): void
    {
        $election = Election::factory()->create(['organization_id' => $this->org->id]);
        $candidate = Candidate::factory()->create(['election_id' => $election->id]);
        Vote::create(['election_id' => $election->id, 'candidate_id' => $candidate->id, 'voter_email' => 'a@b.com']);
        \DB::statement('PRAGMA foreign_keys = ON');

        $this->expectException(QueryException::class);
        $candidate->delete();
    }
}
