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

    /** Public live-results page with a Socket.IO handshake token (none while results are embargoed). */
    public function show(Election $election, RealtimeService $realtime): View
    {
        abort_if($election->status === 'draft', 404);
        $election->load('approvedCandidates');

        $visible = $election->resultsVisible();
        $tallies = $visible ? $election->tallies() : [];

        return view('results.show', [
            'election' => $election,
            'tallies' => $tallies,
            'visible' => $visible,
            'winnerId' => $election->status === 'closed' ? $election->winnerId($tallies) : null,
            'socketToken' => $visible ? $realtime->token(["election.{$election->id}"]) : null,
        ]);
    }

    /** JSON tally — initial load + polling fallback; never leaks counts while embargoed. */
    public function tally(Election $election): JsonResponse
    {
        abort_if($election->status === 'draft', 404);

        if (! $election->resultsVisible()) {
            return response()->json(['election_id' => $election->id, 'visible' => false, 'hidden' => true, 'total' => 0, 'tallies' => (object) []]);
        }

        $election->load('approvedCandidates');
        $tallies = $election->tallies();

        return response()->json([
            'election_id' => $election->id,
            'visible' => true,
            'total' => array_sum($tallies),
            'tallies' => $tallies,
        ]);
    }
}
