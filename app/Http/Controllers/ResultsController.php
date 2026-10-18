<?php

namespace App\Http\Controllers;

use App\Models\Election;
use App\Services\RealtimeService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class ResultsController extends Controller
{
    /** Public landing: the live elections anyone can vote in. */
    public function home(): View
    {
        $elections = Election::live()
            ->with('organization')
            ->withCount('votes')
            ->latest()
            ->get();

        return view('home', compact('elections'));
    }

    /** Public live-results page with a Socket.IO handshake token. */
    public function show(Election $election, RealtimeService $realtime): View
    {
        abort_if($election->status === 'draft', 404);
        $election->load(['candidates' => fn ($q) => $q->where('status', 'approved')]);

        return view('results.show', [
            'election' => $election,
            'tallies' => $election->resultsVisible() ? $election->tallies() : [],
            'visible' => $election->resultsVisible(),
            'winnerId' => $election->status === 'closed' ? $election->winnerId() : null,
            'socketToken' => $realtime->token(["election.{$election->id}"]),
        ]);
    }

    /** JSON tally — initial load + polling fallback if the socket drops. */
    public function tally(Election $election): JsonResponse
    {
        abort_if($election->status === 'draft', 404);

        if (! $election->resultsVisible()) {
            return response()->json(['election_id' => $election->id, 'total' => 0, 'tallies' => [], 'hidden' => true]);
        }

        $election->load(['candidates' => fn ($q) => $q->where('status', 'approved')]);
        $tallies = $election->tallies();

        return response()->json([
            'election_id' => $election->id,
            'total' => array_sum($tallies),
            'tallies' => $tallies,
        ]);
    }
}
