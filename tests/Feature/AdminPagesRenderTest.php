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

/** Smoke-renders every authenticated Blade page so template syntax errors are caught. */
class AdminPagesRenderTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        foreach (['Admin', 'Manager'] as $r) {
            Role::findOrCreate($r, 'web');
        }
        $org = Organization::factory()->create();
        $admin = User::factory()->create(['organization_id' => $org->id]);
        $admin->assignRole('Admin');

        return $admin;
    }

    public function test_admin_pages_render_without_tailwind_or_errors(): void
    {
        $admin = $this->admin();
        $election = Election::factory()->create(['organization_id' => $admin->organization_id]);
        Candidate::factory()->create(['election_id' => $election->id]);
        AuditLog::record('election.status_changed', $election, ['status' => 'draft'], ['status' => 'scheduled']);

        $pages = [
            route('dashboard') => 'Overview',
            route('admin.elections.show', $election) => 'Activity',
            route('admin.elections.create') => 'New election',
            route('admin.analytics') => 'Analytics',
            route('admin.members.index') => 'Members',
            route('admin.org.edit') => 'Organization settings',
            route('profile.edit') => 'Profile Information',
        ];

        foreach ($pages as $url => $needle) {
            $this->actingAs($admin)->get($url)
                ->assertOk()
                ->assertSee($needle)
                ->assertSee('skip-link', false)
                ->assertDontSee('cdn.tailwindcss.com');
        }
    }

    public function test_activity_card_shows_who_and_change_summary(): void
    {
        $admin = $this->admin();
        $election = Election::factory()->create(['organization_id' => $admin->organization_id]);
        AuditLog::record('election.status_changed', $election, ['status' => 'draft'], ['status' => 'live']);

        $this->actingAs($admin)->get(route('admin.elections.show', $election))
            ->assertOk()
            ->assertSee('Election status changed')
            ->assertSee('status: draft → live');
    }

    public function test_guest_pages_render(): void
    {
        foreach (['login', 'register', 'password.request'] as $name) {
            $this->get(route($name))->assertOk()->assertDontSee('text-gray-', false);
        }
    }
}
