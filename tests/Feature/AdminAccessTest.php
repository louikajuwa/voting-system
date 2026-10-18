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

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['Admin', 'Manager'] as $r) {
            Role::findOrCreate($r, 'web');
        }
    }

    private function user(string $role, Organization $org): User
    {
        $u = User::factory()->create(['organization_id' => $org->id]);
        $u->assignRole($role);

        return $u;
    }

    public function test_manager_cannot_access_members_but_admin_can(): void
    {
        $org = Organization::factory()->create();

        $this->actingAs($this->user('Manager', $org))->get(route('admin.members.index'))->assertForbidden();
        $this->actingAs($this->user('Admin', $org))->get(route('admin.members.index'))->assertOk();
    }

    public function test_manager_cannot_delete_election(): void
    {
        $org = Organization::factory()->create();
        $election = Election::factory()->create(['organization_id' => $org->id]);

        $this->actingAs($this->user('Manager', $org))
            ->delete(route('admin.elections.destroy', $election))
            ->assertForbidden();

        $this->assertDatabaseHas('elections', ['id' => $election->id, 'deleted_at' => null]);
    }

    public function test_votes_json_requires_auth_and_filters_by_email(): void
    {
        $org = Organization::factory()->create();
        $election = Election::factory()->live()->create(['organization_id' => $org->id]);
        $candidate = Candidate::factory()->create(['election_id' => $election->id]);
        Vote::create(['election_id' => $election->id, 'candidate_id' => $candidate->id, 'voter_email' => 'alice@acme.edu']);
        Vote::create(['election_id' => $election->id, 'candidate_id' => $candidate->id, 'voter_email' => 'bob@acme.edu']);

        // Unauthenticated → redirect to login.
        $this->getJson(route('admin.elections.votes', $election))->assertUnauthorized();

        $res = $this->actingAs($this->user('Admin', $org))
            ->getJson(route('admin.elections.votes', $election).'?search=alice');
        $res->assertOk()
            ->assertJsonPath('result', 'success')
            ->assertJsonCount(1, 'data.rows')
            ->assertJsonPath('data.rows.0.email', 'alice@acme.edu');
    }

    public function test_excel_export_downloads(): void
    {
        $org = Organization::factory()->create();
        $election = Election::factory()->closed()->create(['organization_id' => $org->id]);
        $candidate = Candidate::factory()->create(['election_id' => $election->id]);
        Vote::create(['election_id' => $election->id, 'candidate_id' => $candidate->id, 'voter_email' => 'a@b.com']);

        $this->actingAs($this->user('Admin', $org))
            ->get(route('admin.elections.votes.excel', $election))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_scheduler_opens_and_closes_elections(): void
    {
        $org = Organization::factory()->create();
        $toOpen = Election::factory()->create(['organization_id' => $org->id, 'status' => 'scheduled', 'opens_at' => now()->subMinute()]);
        $toClose = Election::factory()->create(['organization_id' => $org->id, 'status' => 'live', 'closes_at' => now()->subMinute()]);

        $this->artisan('elections:transition')->assertSuccessful();

        $this->assertEquals('live', $toOpen->fresh()->status);
        $this->assertEquals('closed', $toClose->fresh()->status);
    }
}
