<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Election;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vote;
use App\Support\Csv;
use App\Support\Like;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ExportSecurityTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private Election $election;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['Admin', 'Manager'] as $r) {
            Role::findOrCreate($r, 'web');
        }
        $this->org = Organization::factory()->create();
        $this->election = Election::factory()->live()->create(['organization_id' => $this->org->id]);
        $c = Candidate::factory()->create(['election_id' => $this->election->id]);
        Vote::create(['election_id' => $this->election->id, 'candidate_id' => $c->id, 'voter_email' => '=cmd@b.com']);
    }

    private function user(string $role): User
    {
        $u = User::factory()->create(['organization_id' => $this->org->id]);
        $u->assignRole($role);

        return $u;
    }

    public function test_manager_cannot_reach_voter_level_data(): void
    {
        $m = $this->user('Manager');

        $this->actingAs($m)->getJson(route('admin.elections.votes', $this->election))->assertForbidden();
        $this->actingAs($m)->get(route('admin.elections.votes.csv', $this->election))->assertForbidden();
        $this->actingAs($m)->get(route('admin.elections.votes.excel', $this->election))->assertForbidden();
        $this->actingAs($m)->get(route('admin.elections.report', $this->election))->assertOk();
    }

    public function test_admin_can_reach_voter_level_data(): void
    {
        $a = $this->user('Admin');

        $this->actingAs($a)->getJson(route('admin.elections.votes', $this->election))->assertOk();
        $this->actingAs($a)->get(route('admin.elections.votes.csv', $this->election))->assertOk();
        $this->actingAs($a)->get(route('admin.elections.votes.excel', $this->election))->assertOk();
    }

    public function test_votes_csv_neutralises_formulas(): void
    {
        $body = $this->actingAs($this->user('Admin'))
            ->get(route('admin.elections.votes.csv', $this->election))->streamedContent();

        $this->assertStringContainsString("'=cmd@b.com", $body);
    }

    public function test_csv_safe_helper(): void
    {
        foreach (['=1+1', '+1', '-1', '@a', "\tx", "\rx"] as $v) {
            $this->assertSame("'".$v, Csv::safe($v));
        }
        $this->assertSame('plain', Csv::safe('plain'));
        $this->assertSame(5, Csv::safe(5));
        $this->assertNull(Csv::safe(null));
        $this->assertSame('', Csv::safe(''));
    }

    public function test_like_escape(): void
    {
        $this->assertSame('100\\%\\_a\\\\b', Like::escape('100%_a\\b'));
    }
}
