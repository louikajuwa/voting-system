<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    public function index(): View
    {
        return view('admin.analytics');
    }

    public function data(): JsonResponse
    {
        $orgId = auth()->user()->organization_id;
        $electionIds = Election::where('organization_id', $orgId)->pluck('id');

        // Votes over the last 30 days (fill gaps with zero).
        $rows = Vote::whereIn('election_id', $electionIds)
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->selectRaw('DATE(created_at) as d, COUNT(*) as c')
            ->groupBy('d')->pluck('c', 'd');

        $series = [];
        for ($i = 29; $i >= 0; $i--) {
            $day = now()->subDays($i)->toDateString();
            $series[] = ['date' => Carbon::parse($day)->format('M j'), 'votes' => (int) ($rows[$day] ?? 0)];
        }

        // Votes per election (top 8 by volume).
        $perElection = Election::where('organization_id', $orgId)
            ->withCount('votes')->orderByDesc('votes_count')->take(8)->get()
            ->map(fn ($e) => ['title' => $e->title, 'votes' => $e->votes_count]);

        return $this->ok('', [
            'over_time' => $series,
            'per_election' => $perElection,
            'total' => (int) Vote::whereIn('election_id', $electionIds)->count(),
        ]);
    }
}
