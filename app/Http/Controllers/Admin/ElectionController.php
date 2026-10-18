<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreElectionRequest;
use App\Models\Election;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ElectionController extends Controller
{
    public function index(): RedirectResponse
    {
        // The dashboard is the elections overview.
        return redirect()->route('dashboard');
    }

    public function create(): View
    {
        return view('elections.create', ['election' => new Election(['status' => 'draft'])]);
    }

    public function store(StoreElectionRequest $request): JsonResponse|RedirectResponse
    {
        $election = Election::create([
            ...$request->validated(),
            'organization_id' => $request->user()->organization_id,
            'slug' => $this->uniqueSlug($request->validated()['title']),
        ]);

        return $this->respond(route('admin.elections.show', $election), 'Election created.', [
            'election' => ['slug' => $election->slug, 'title' => $election->title, 'status' => $election->status],
        ]);
    }

    public function show(Election $election): View
    {
        $this->authorizeOrg($election);
        $election->load('candidates');

        $winner = null;
        if ($election->status === 'closed' && ($id = $election->winnerId())) {
            $winner = $election->candidates->firstWhere('id', $id)?->name;
        }

        return view('elections.show', compact('election', 'winner'));
    }

    public function edit(Election $election): View
    {
        $this->authorizeOrg($election);

        return view('elections.edit', compact('election'));
    }

    public function update(StoreElectionRequest $request, Election $election): JsonResponse|RedirectResponse
    {
        $this->authorizeOrg($election);
        $election->update($request->validated());

        return $this->respond(route('admin.elections.show', $election), 'Election updated.', [
            'election' => ['slug' => $election->slug, 'title' => $election->title, 'status' => $election->status],
        ]);
    }

    public function destroy(Election $election): JsonResponse|RedirectResponse
    {
        $this->authorizeOrg($election);
        abort_unless(request()->user()->hasRole('Admin'), 403, 'Only an Admin can delete elections.');
        $election->delete(); // soft delete — vote records are preserved

        return $this->respond(route('dashboard'), 'Election archived.');
    }

    /** Quick status transition (Draft -> Scheduled -> Live -> Closed). */
    public function status(Election $election): JsonResponse|RedirectResponse
    {
        $this->authorizeOrg($election);
        $status = (string) request()->string('status');
        abort_unless(in_array($status, Election::STATUSES, true), 422);

        $election->update(['status' => $status]);

        return $this->respond(route('admin.elections.show', $election), "Election marked {$status}.", [
            'status' => $status,
        ]);
    }

    /** JSON: admin voter-audit log — email/candidate, searchable + date-filtered + paginated. */
    public function votes(Election $election): JsonResponse
    {
        $this->authorizeOrg($election);

        $search = trim((string) request('search'));
        $from = request('from');
        $to = request('to');

        $query = $election->votes()->with('candidate:id,name')->latest();
        if ($search !== '') {
            $query->where('voter_email', 'like', "%{$search}%");
        }
        if ($from) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to) {
            $query->whereDate('created_at', '<=', $to);
        }

        $p = $query->paginate(15)->withQueryString();

        return $this->paginated($p, fn ($v) => [
            'email' => $v->voter_email,
            'candidate' => $v->candidate?->name ?? '—',
            'ip' => $v->ip,
            'time' => $v->created_at?->toDayDateTimeString(),
        ]);
    }

    private function uniqueSlug(string $title): string
    {
        return Str::slug($title).'-'.Str::lower(Str::random(6));
    }

    private function authorizeOrg(Election $election): void
    {
        abort_unless($election->organization_id === request()->user()->organization_id, 403);
    }
}
