<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MemberSafetyTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['Admin', 'Manager'] as $r) {
            Role::findOrCreate($r, 'web');
        }
        $this->org = Organization::factory()->create();
    }

    private function user(string $role): User
    {
        $u = User::factory()->create(['organization_id' => $this->org->id]);
        $u->assignRole($role);

        return $u;
    }

    public function test_admin_cannot_change_own_role_or_delete_self(): void
    {
        $a = $this->user('Admin');

        $this->actingAs($a)->patchJson(route('admin.members.update', $a), ['role' => 'Manager'])->assertForbidden();
        $this->actingAs($a)->deleteJson(route('admin.members.destroy', $a))->assertForbidden();
        $this->assertTrue($a->fresh()->hasRole('Admin'));
    }

    public function test_admin_can_demote_and_delete_another_admin_when_not_last(): void
    {
        $a = $this->user('Admin');
        $b = $this->user('Admin');

        $this->actingAs($a)->patchJson(route('admin.members.update', $b), ['role' => 'Manager'])->assertOk();
        $this->assertTrue($b->fresh()->hasRole('Manager'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'member.role_changed']);

        $this->actingAs($a)->deleteJson(route('admin.members.destroy', $b))->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'member.deleted']);
    }

    public function test_temp_password_only_in_json_not_in_session(): void
    {
        $a = $this->user('Admin');

        $res = $this->actingAs($a)->postJson(route('admin.members.store'), [
            'name' => 'New', 'email' => 'new@example.com', 'role' => 'Manager',
        ])->assertOk();
        $this->assertNotEmpty($res->json('data.temp_password'));
        $this->assertStringNotContainsString($res->json('data.temp_password'), (string) $res->json('message'));

        $this->actingAs($a)->post(route('admin.members.store'), [
            'name' => 'Other', 'email' => 'other@example.com', 'role' => 'Manager',
        ])->assertSessionHas('status', 'Member added.');
        $this->assertSame(2, AuditLog::where('action', 'member.created')->count());
    }
}
