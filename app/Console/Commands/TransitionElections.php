<?php

namespace App\Console\Commands;

use App\Models\Election;
use Illuminate\Console\Command;

class TransitionElections extends Command
{
    protected $signature = 'elections:transition';

    protected $description = 'Auto-open scheduled elections and auto-close live ones past their window.';

    public function handle(): int
    {
        $opened = Election::where('status', 'scheduled')
            ->whereNotNull('opens_at')->where('opens_at', '<=', now())
            ->update(['status' => 'live']);

        $closed = Election::where('status', 'live')
            ->whereNotNull('closes_at')->where('closes_at', '<=', now())
            ->update(['status' => 'closed']);

        $this->info("Opened {$opened}, closed {$closed}.");

        return self::SUCCESS;
    }
}
