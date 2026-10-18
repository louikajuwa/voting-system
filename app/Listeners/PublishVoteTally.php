<?php

namespace App\Listeners;

use App\Events\VoteCast;
use App\Models\Election;
use App\Models\Vote;
use App\Services\RealtimeService;

class PublishVoteTally
{
    public function __construct(private RealtimeService $realtime) {}

    public function handle(VoteCast $event): void
    {
        $election = Election::with('candidates')->find($event->electionId);
        if (! $election) {
            return;
        }

        $tallies = $election->tallies();
        $total = array_sum($tallies);

        // Results page room — per-election live standings.
        $this->realtime->publish(["election.{$election->id}"], 'vote', [
            'election_id' => $election->id,
            'total' => $total,
            'tallies' => $tallies,
        ]);

        // Dashboard room — org-wide live tiles + the affected election's row.
        $orgId = $election->organization_id;
        $ids = Election::where('organization_id', $orgId)->pluck('id');
        $this->realtime->publish(["org.{$orgId}"], 'vote', [
            'election_id' => $election->id,
            'election_votes' => $total,
            'votes_today' => (int) Vote::whereIn('election_id', $ids)->whereDate('created_at', today())->count(),
            'votes_total' => (int) Vote::whereIn('election_id', $ids)->count(),
        ]);
    }
}
