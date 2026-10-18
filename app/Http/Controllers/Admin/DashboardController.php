<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Election;
use App\Models\Vote;
use App\Services\RealtimeService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(RealtimeService $realtime): View
    {
        $orgId = auth()->user()->organization_id;

        return view('dashboard', [
            'stats' => $this->computeStats(),
            'orgId' => $orgId,
            'socketToken' => $realtime->token(["org.{$orgId}"]),
        ]);
    }

    /** JSON: org stat tiles (also used for live refresh). */
    public function stats(): JsonResponse
    {
        return $this->ok('', $this->computeStats());
    }

    /** JSON: server-side searched + paginated elections for the dynamic table. */
    public function elections(): JsonResponse
    {
        $orgId = auth()->user()->organization_id;
        $search = trim((string) request('search'));
        $status = (string) request('status');

        $query = Election::where('organization_id', $orgId)
            ->withCount(['votes', 'candidates'])
            ->latest();

        if ($search !== '') {
            $query->where('title', 'like', "%{$search}%");
        }
        if (in_array($status, Election::STATUSES, true)) {
            $query->where('status', $status);
        }

        $p = $query->paginate(8)->withQueryString();

        return $this->paginated($p, fn ($e) => [
            'title' => $e->title,
            'status' => $e->status,
            'candidates' => $e->candidates_count,
            'votes' => $e->votes_count,
            'manage_url' => route('admin.elections.show', $e),
            'results_url' => route('results.show', $e),
        ]);
    }

    private function computeStats(): array
    {
        $orgId = auth()->user()->organization_id;
        $electionIds = Election::where('organization_id', $orgId)->pluck('id');

        return [
            'active' => Election::where('organization_id', $orgId)->where('status', 'live')->count(),
            'elections' => $electionIds->count(),
            'votes' => (int) Vote::whereIn('election_id', $electionIds)->count(),
            'votes_today' => (int) Vote::whereIn('election_id', $electionIds)->whereDate('created_at', today())->count(),
        ];
    }
}
