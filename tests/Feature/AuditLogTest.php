<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['Admin', 'Manager'] as $r) {
            Role::findOrCreate($r, 'web');
        }
    }

    public function test_status_change_writes_an_audit_row_and_show_exposes_it(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->create(['organization_id' => $org->id]);
        $admin->assignRole('Admin');
        $election = Election::factory()->create(['organization_id' => $org->id]);
        Candidate::factory()->create(['election_id' => $election->id]);

        $this->actingAs($admin)->postJson(route('admin.elections.status', $election), ['status' => 'live'])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $org->id,
            'user_id' => $admin->id,
            'action' => 'election.status_changed',
            'subject_type' => $election->getMorphClass(),
            'subject_id' => $election->id,
        ]);

        $this->actingAs($admin)->get(route('admin.elections.show', $election))
            ->assertOk()
            ->assertViewHas('audit', fn ($a) => $a->first()->action === 'election.status_changed');
    }

    public function test_record_falls_back_to_the_acting_users_org_without_a_subject(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $org->id]);
        $this->actingAs($user);

        $log = AuditLog::record('member.added', null, [], ['email' => 'x@y.z']);

        $this->assertSame($org->id, $log->organization_id);
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame(['email' => 'x@y.z'], $log->after);
    }
}
