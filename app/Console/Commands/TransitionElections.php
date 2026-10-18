<?php

namespace App\Console\Commands;

use App\Models\Election;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TransitionElections extends Command
{
    protected $signature = 'elections:transition';

    protected $description = 'Auto-open scheduled elections and auto-close live ones past their window.';

    public function handle(): int
    {
        $opened = 0;
        Election::where('status', 'scheduled')
            ->whereNotNull('opens_at')->where('opens_at', '<=', now())
            ->get()
            ->each(function (Election $election) use (&$opened) {
                if (! $election->canTransitionTo('live') || ! $election->approvedCandidates()->exists()) {
                    Log::warning("elections:transition left election {$election->id} scheduled: no approved candidate.");
                    $this->warn("Election {$election->id} has no approved candidate; left scheduled.");

                    return;
                }
                $election->update(['status' => 'live']);
                $opened++;
            });

        $closed = Election::where('status', 'live')
            ->whereNotNull('closes_at')->where('closes_at', '<=', now())
            ->update(['status' => 'closed']);

        $this->info("Opened {$opened}, closed {$closed}.");

        return self::SUCCESS;
    }
}
