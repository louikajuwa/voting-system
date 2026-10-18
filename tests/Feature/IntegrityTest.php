<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Election;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class IntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['Admin', 'Manager'] as $r) {
            Role::findOrCreate($r, 'web');
        }
    }

    private function admin(Organization $org): User
    {
        $u = User::factory()->create(['organization_id' => $org->id]);
        $u->assignRole('Admin');

        return $u;
    }

    public function test_pending_candidate_is_not_on_the_ballot_and_cannot_be_voted(): void
    {
        $election = Election::factory()->live()->create();
        $approved = Candidate::factory()->create(['election_id' => $election->id, 'status' => 'approved', 'name' => 'Approved One']);
        $pending = Candidate::factory()->create(['election_id' => $election->id, 'status' => 'pending', 'name' => 'Pending One']);

        $this->get(route('vote.show', $election))
            ->assertSee('Approved One')
            ->assertDontSee('Pending One');

        $this->post(route('vote.store', $election), [
            'candidate_id' => $pending->id,
            'email' => 'x@example.com',
        ])->assertSessionHasErrors('candidate_id');

        $this->assertDatabaseCount('votes', 0);
    }

    public function test_candidate_idor_is_blocked_across_elections(): void
    {
        $org = Organization::factory()->create();
        $electionA = Election::factory()->create(['organization_id' => $org->id]);
        $electionB = Election::factory()->create(['organization_id' => $org->id]);
        $candidateA = Candidate::factory()->create(['election_id' => $electionA->id]);

        // candidate of election A accessed via election B must 404 (scoped binding).
        $this->actingAs($this->admin($org))
            ->get(route('admin.elections.candidates.edit', [$electionB, $candidateA]))
            ->assertNotFound();
    }

    public function test_deleting_an_election_is_soft_delete_and_keeps_votes(): void
    {
        $org = Organization::factory()->create();
        $election = Election::factory()->closed()->create(['organization_id' => $org->id]);
        $candidate = Candidate::factory()->create(['election_id' => $election->id]);
        Vote::create(['election_id' => $election->id, 'candidate_id' => $candidate->id, 'voter_email' => 'a@b.com']);

        $this->actingAs($this->admin($org))
            ->delete(route('admin.elections.destroy', $election))
            ->assertRedirect();

        $this->assertSoftDeleted('elections', ['id' => $election->id]);
        $this->assertDatabaseHas('votes', ['election_id' => $election->id]); // votes preserved
    }
}
