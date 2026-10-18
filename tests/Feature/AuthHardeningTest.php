<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_user_is_redirected_to_verification_notice(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->unverified()->create(['organization_id' => $org->id]);

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('verification.notice'));
        $this->actingAs($user)->get('/profile')->assertOk();
    }

    public function test_register_is_throttled_after_five_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/register', ['email' => 'bad']);
        }

        $this->post('/register', ['email' => 'bad'])->assertStatus(429);
    }

    public function test_registered_user_must_verify_email(): void
    {
        Role::findOrCreate('Admin', 'web');
        $this->post('/register', [
            'name' => 'T', 'organization' => 'O', 'email' => 't@example.com',
            'password' => 'password', 'password_confirmation' => 'password',
        ]);

        $this->assertFalse(User::where('email', 't@example.com')->first()->hasVerifiedEmail());
        $this->get('/dashboard')->assertRedirect(route('verification.notice'));
    }
}
