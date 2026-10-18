<?php

namespace Database\Seeders;

use App\Models\Candidate;
use App\Models\Election;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    private array $palette = ['#2F6BED', '#12A150', '#B7791F', '#8257E6', '#E0518A', '#0EA5E9'];

    public function run(): void
    {
        foreach (['Admin', 'Manager', 'Voter'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        // Two organizations demonstrate tenant isolation.
        $this->seedOrg('Acme University', 'acme-university', 'admin@ballotbox.test', 'manager@ballotbox.test');
        $this->seedOrg('Globex Corporation', 'globex-corp', 'admin@globex.test', 'manager@globex.test');
    }

    private function seedOrg(string $name, string $slug, string $adminEmail, string $managerEmail): void
    {
        $org = Organization::firstOrCreate(['slug' => $slug], ['name' => $name, 'timezone' => 'UTC']);

        $admin = User::firstOrCreate($this->userKey($adminEmail),
            ['name' => fake()->name(), 'password' => Hash::make('password'), 'organization_id' => $org->id]);
        $admin->syncRoles('Admin');

        $manager = User::firstOrCreate($this->userKey($managerEmail),
            ['name' => fake()->name(), 'password' => Hash::make('password'), 'organization_id' => $org->id]);
        $manager->syncRoles('Manager');

        // Demo accounts skip the email-verification gate on the dashboard.
        foreach ([$admin, $manager] as $user) {
            if (! $user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }
        }

        // A spread of elections across every lifecycle status.
        $blueprints = [
            ['Student Union President 2026', 'live', 1600],
            ['Faculty Senate Seat — Sciences', 'live', 900],
            ['Library Hours Referendum', 'closed', 2100],
            ['Alumni Board 2025', 'closed', 1200],
            ['Homecoming Committee Chair', 'scheduled', 0],
            ['Best Dining Hall Poll', 'draft', 0],
        ];

        foreach ($blueprints as [$title, $status, $voteCount]) {
            $this->seedElection($org, $title, $status, $voteCount);
        }
    }

    private function seedElection(Organization $org, string $title, string $status, int $voteCount): void
    {
        $slug = Str::slug($title).'-'.Str::lower(Str::random(5));

        $window = match ($status) {
            'live' => ['opens_at' => now()->subDays(3), 'closes_at' => now()->addDays(2)],
            'closed' => ['opens_at' => now()->subDays(30), 'closes_at' => now()->subDays(2)],
            'scheduled' => ['opens_at' => now()->addDays(4), 'closes_at' => now()->addDays(10)],
            default => ['opens_at' => null, 'closes_at' => null],
        };

        $election = Election::create([
            'organization_id' => $org->id,
            'title' => $title,
            'slug' => $slug,
            'description' => 'Official '.strtolower($title).' for '.$org->name.'. One vote per member.',
            'status' => $status,
            ...$window,
        ]);

        $roster = array_slice([
            ['Maya Rodriguez', 'Progressive Coalition'],
            ['David Chen', 'Independent'],
            ['Sarah Okonkwo', 'Student First'],
            ['James Park', 'Reform Slate'],
            ['Aisha Bello', 'Unity Bloc'],
        ], 0, rand(3, 5));

        $candidates = [];
        foreach ($roster as $i => [$cname, $party]) {
            $candidates[] = Candidate::create([
                'election_id' => $election->id,
                'name' => $cname,
                'photo' => $this->makePhoto($cname, $this->palette[$i % count($this->palette)]),
                'position' => 'President',
                'party' => $party,
                'bio' => fake()->sentence(9),
                'manifesto' => fake()->paragraphs(2, true),
                'status' => 'approved',
            ]);
        }

        if ($voteCount > 0) {
            $this->seedVotes($election, collect($candidates), $voteCount);
        }
    }

    /** Weighted votes with unique per-election emails, timestamps spread across the window. */
    private function seedVotes(Election $election, $candidates, int $count): void
    {
        $weights = [];
        foreach ($candidates as $idx => $c) {
            $weights[$c->id] = max(1, count($candidates) - $idx) + rand(0, 3);
        }
        $pool = [];
        foreach ($weights as $id => $w) {
            $pool = array_merge($pool, array_fill(0, $w, $id));
        }

        $start = ($election->opens_at ?? now()->subDays(30))->timestamp;
        $end = min(($election->closes_at ?? now())->timestamp, now()->timestamp);

        $rows = [];
        for ($n = 1; $n <= $count; $n++) {
            $ts = date('Y-m-d H:i:s', rand($start, max($start + 1, $end)));
            $rows[] = [
                'election_id' => $election->id,
                'candidate_id' => $pool[array_rand($pool)],
                'voter_email' => "voter{$n}@{$election->slug}.test",
                'ip' => rand(10, 250).'.'.rand(0, 255).'.'.rand(0, 255).'.'.rand(1, 254),
                'created_at' => $ts,
                'updated_at' => $ts,
            ];
            if (count($rows) >= 1000) {
                Vote::insert($rows);
                $rows = [];
            }
        }
        if ($rows) {
            Vote::insert($rows);
        }
    }

    private function userKey(string $email): array
    {
        return ['email' => $email];
    }

    private function makePhoto(string $name, string $color): string
    {
        $svg = <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
          <rect width="100" height="100" fill="{$color}"/>
          <circle cx="50" cy="40" r="19" fill="#fff" opacity=".92"/>
          <path d="M18 100c0-21 14-33 32-33s32 12 32 33z" fill="#fff" opacity=".92"/>
        </svg>
        SVG;

        $path = 'candidates/'.Str::slug($name).'-'.Str::lower(Str::random(4)).'.svg';
        Storage::disk('public')->put($path, $svg);

        return $path;
    }
}
