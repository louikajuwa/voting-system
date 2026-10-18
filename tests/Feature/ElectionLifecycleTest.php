<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Election;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ElectionLifecycleTest extends TestCase
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
        $this->org = Organization::factory()->create(['timezone' => 'America/New_York']);
        $this->admin = User::factory()->create(['organization_id' => $this->org->id]);
        $this->admin->assignRole('Admin');
    }

    private function election(string $state = 'draft', bool $withCandidate = true): Election
    {
        $election = Election::factory()->{$state}()->create(['organization_id' => $this->org->id]);
        if ($withCandidate) {
            Candidate::factory()->create(['election_id' => $election->id]);
        }

        return $election;
    }

    private function setStatus(Election $e, string $status)
    {
        return $this->actingAs($this->admin)->postJson(route('admin.elections.status', $e), ['status' => $status]);
    }

    public function test_valid_transitions_succeed(): void
    {
        $e = $this->election('draft');
        $this->setStatus($e, 'scheduled')->assertOk();
        $this->setStatus($e, 'draft')->assertOk();
        $this->setStatus($e, 'live')->assertOk();
        $this->setStatus($e, 'closed')->assertOk();
        $this->assertSame('closed', $e->fresh()->status);
    }

    public function test_invalid_transitions_are_rejected_with_409(): void
    {
        $closed = $this->election('closed');
        $this->setStatus($closed, 'live')->assertStatus(409)->assertJsonPath('result', 'error');

        $live = $this->election('live');
        $this->setStatus($live, 'draft')->assertStatus(409);
        $this->assertSame('live', $live->fresh()->status);
    }

    public function test_going_live_requires_an_approved_candidate(): void
    {
        $e = $this->election('draft', false);
        Candidate::factory()->create(['election_id' => $e->id, 'status' => 'pending']);

        $this->setStatus($e, 'live')->assertStatus(409);
        $this->assertSame('draft', $e->fresh()->status);
    }

    public function test_update_enforces_the_state_machine_too(): void
    {
        $closed = $this->election('closed');

        $this->actingAs($this->admin)->putJson(route('admin.elections.update', $closed), [
            'title' => 'T', 'status' => 'live',
        ])->assertStatus(409);
    }

    public function test_closed_election_window_cannot_be_edited(): void
    {
        $closed = $this->election('closed');
        $closed->update(['opens_at' => now()->subDays(2), 'closes_at' => now()->subDay()]);

        $this->actingAs($this->admin)->putJson(route('admin.elections.update', $closed), [
            'title' => 'T', 'status' => 'closed', 'closes_at' => now()->addDay()->format('Y-m-d\TH:i'),
        ])->assertStatus(409);
    }

    public function test_live_election_can_extend_close_but_not_change_open(): void
    {
        $live = $this->election('live');
        $localOpen = $live->formatLocal('opens_at');
        $newClose = now('America/New_York')->addDays(5)->format('Y-m-d\TH:i');

        $this->actingAs($this->admin)->putJson(route('admin.elections.update', $live), [
            'title' => 'T', 'status' => 'live', 'opens_at' => $localOpen, 'closes_at' => $newClose,
        ])->assertOk();

        $this->actingAs($this->admin)->putJson(route('admin.elections.update', $live), [
            'title' => 'T', 'status' => 'live', 'opens_at' => now('America/New_York')->subDays(9)->format('Y-m-d\TH:i'),
        ])->assertStatus(409);

        $this->actingAs($this->admin)->putJson(route('admin.elections.update', $live), [
            'title' => 'T', 'status' => 'live', 'opens_at' => $localOpen, 'closes_at' => now('America/New_York')->subHour()->format('Y-m-d\TH:i'),
        ])->assertStatus(409);
    }

    public function test_form_times_are_interpreted_in_the_org_timezone_and_stored_as_utc(): void
    {
        $this->actingAs($this->admin)->postJson(route('admin.elections.store'), [
            'title' => 'TZ test', 'status' => 'draft',
            'opens_at' => '2030-01-15T09:00', 'closes_at' => '2030-01-15T17:00',
        ])->assertOk();

        $e = Election::where('title', 'TZ test')->firstOrFail();
        $this->assertSame('2030-01-15 14:00:00', $e->opens_at->utc()->format('Y-m-d H:i:s')); // EST = UTC-5
        $this->assertSame('2030-01-15T09:00', $e->formatLocal('opens_at'));
    }

    public function test_hide_results_is_saved_from_the_form(): void
    {
        $this->actingAs($this->admin)->postJson(route('admin.elections.store'), [
            'title' => 'Hidden', 'status' => 'draft', 'hide_results' => '1',
        ])->assertOk();
        $this->assertTrue(Election::where('title', 'Hidden')->firstOrFail()->hide_results);

        $this->actingAs($this->admin)->postJson(route('admin.elections.store'), [
            'title' => 'Shown', 'status' => 'draft', 'hide_results' => '0',
        ])->assertOk();
        $this->assertFalse(Election::where('title', 'Shown')->firstOrFail()->hide_results);
    }

    public function test_scheduler_only_opens_elections_with_an_approved_candidate(): void
    {
        $with = $this->election('scheduled');
        $with->update(['opens_at' => now()->subMinute()]);
        $without = $this->election('scheduled', false);
        $without->update(['opens_at' => now()->subMinute()]);

        $this->artisan('elections:transition')->assertSuccessful();

        $this->assertSame('live', $with->fresh()->status);
        $this->assertSame('scheduled', $without->fresh()->status);
    }
}
