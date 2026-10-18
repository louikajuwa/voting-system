<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreElectionRequest;
use App\Models\AuditLog;
use App\Models\Election;
use App\Support\Like;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ElectionController extends Controller
{
    use AuthorizesOrganization;

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
        $data = $this->withUtcWindow($request->validated(), $request->user()->organization?->timezone ?: 'UTC');

        // A new election can only start as a draft or scheduled; going live needs candidates first.
        if (! in_array($data['status'], ['draft', 'scheduled'], true)) {
            return $this->reject('A new election must start as draft or scheduled — add candidates before going live.', 409);
        }

        $election = Election::create([
            ...$data,
            'organization_id' => $request->user()->organization_id,
            'slug' => $this->uniqueSlug($data['title']),
        ]);
        AuditLog::record('election.created', $election, [], $this->snapshot($election));

        return $this->respond(route('admin.elections.show', $election), 'Election created.', [
            'election' => ['slug' => $election->slug, 'title' => $election->title, 'status' => $election->status],
        ]);
    }

    public function show(Election $election): View
    {
        $this->authorizeOrg($election);
        $election->load(['candidates', 'approvedCandidates']);

        $winner = null;
        if ($election->status === 'closed' && ($id = $election->winnerId())) {
            $winner = $election->approvedCandidates->firstWhere('id', $id)?->name;
        }

        // Last 50 audit entries for this election (candidate changes are logged against the election).
        $audit = AuditLog::with('user:id,name')
            ->where('subject_type', $election->getMorphClass())
            ->where('subject_id', $election->id)
            ->latest('id')
            ->limit(50)
            ->get();

        return view('elections.show', compact('election', 'winner', 'audit'));
    }

    public function edit(Election $election): View
    {
        $this->authorizeOrg($election);

        return view('elections.edit', compact('election'));
    }

    public function update(StoreElectionRequest $request, Election $election): JsonResponse|RedirectResponse
    {
        $this->authorizeOrg($election);
        $data = $this->withUtcWindow($request->validated(), $election->timezone());

        if ($error = $this->windowError($election, $data)) {
            return $this->reject($error, 409);
        }

        $newStatus = $data['status'] ?? $election->status;
        $changingStatus = $newStatus !== $election->status;
        if ($changingStatus && ($error = $this->transitionError($election, $newStatus))) {
            return $this->reject($error[0], $error[1]);
        }

        $before = $this->snapshot($election);
        $election->update($data);
        $after = $this->snapshot($election);

        if ($changingStatus) {
            AuditLog::record('election.status_changed', $election, ['status' => $before['status']], ['status' => $newStatus]);
        }
        $diff = array_keys(array_diff_assoc($after, $before));
        if (array_diff($diff, ['status'])) {
            AuditLog::record('election.updated', $election, array_intersect_key($before, array_flip($diff)), array_intersect_key($after, array_flip($diff)));
        }

        return $this->respond(route('admin.elections.show', $election), 'Election updated.', [
            'election' => ['slug' => $election->slug, 'title' => $election->title, 'status' => $election->status],
        ]);
    }

    public function destroy(Election $election): JsonResponse|RedirectResponse
    {
        $this->authorizeOrg($election);
        abort_unless(request()->user()->hasRole('Admin'), 403, 'Only an Admin can delete elections.');
        AuditLog::record('election.deleted', $election, $this->snapshot($election));
        $election->delete(); // soft delete — vote records are preserved

        return $this->respond(route('dashboard'), 'Election archived.');
    }

    /** Quick status transition (Draft -> Scheduled -> Live -> Closed). */
    public function status(Election $election): JsonResponse|RedirectResponse
    {
        $this->authorizeOrg($election);
        $status = (string) request()->string('status');
        abort_unless(in_array($status, Election::STATUSES, true), 422);

        if ($error = $this->transitionError($election, $status)) {
            return $this->reject($error[0], $error[1]);
        }

        $from = $election->status;
        $election->update(['status' => $status]);
        AuditLog::record('election.status_changed', $election, ['status' => $from], ['status' => $status]);

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
            $query->where('voter_email', 'like', '%'.Like::escape($search).'%');
        }
        if ($from) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to) {
            $query->whereDate('created_at', '<=', $to);
        }

        $p = $query->paginate(15)->withQueryString();
        $tz = $election->timezone();

        return $this->paginated($p, fn ($v) => [
            'email' => $v->voter_email,
            'candidate' => $v->candidate?->name ?? '—',
            'ip' => $v->ip,
            'time' => $v->created_at?->copy()->setTimezone($tz)->toDayDateTimeString(),
        ]);
    }

    private function uniqueSlug(string $title): string
    {
        return Str::slug($title).'-'.Str::lower(Str::random(6));
    }

    /** Convert org-local datetime-local input to UTC for storage. */
    private function withUtcWindow(array $data, string $tz): array
    {
        foreach (['opens_at', 'closes_at'] as $col) {
            if (array_key_exists($col, $data)) {
                $data[$col] = Election::parseLocal($data[$col], $tz);
            }
        }

        return $data;
    }

    /** Why the status move is not allowed: [message, http status], or null when fine. */
    private function transitionError(Election $election, string $to): ?array
    {
        if (! $election->canTransitionTo($to)) {
            return ["Cannot move an election from {$election->status} to {$to}.", 409];
        }
        if ($to === 'live' && ! $election->approvedCandidates()->exists()) {
            return ['Add at least one approved candidate before going live.', 409];
        }

        return null;
    }

    /** Window edit locks: nothing once closed; only a later closes_at once live. */
    private function windowError(Election $election, array $data): ?string
    {
        if (! in_array($election->status, ['live', 'closed'], true)) {
            return null;
        }

        $changed = fn (string $col) => array_key_exists($col, $data)
            && $this->minute($data[$col]) !== $this->minute($election->{$col});

        if ($election->status === 'closed') {
            return ($changed('opens_at') || $changed('closes_at')) ? 'A closed election\'s schedule cannot be changed.' : null;
        }
        if ($changed('opens_at')) {
            return 'The opening time cannot be changed once the election is live.';
        }
        if ($changed('closes_at') && $data['closes_at'] !== null && ! $data['closes_at']->isFuture()) {
            return 'The closing time must be in the future while the election is live.';
        }

        return null;
    }

    private function minute($dt): ?string
    {
        return $dt?->copy()->utc()->format('Y-m-d H:i');
    }

    private function snapshot(Election $election): array
    {
        return [
            'title' => $election->title,
            'description' => $election->description,
            'status' => $election->status,
            'hide_results' => (bool) $election->hide_results,
            'opens_at' => $election->opens_at?->toIso8601String(),
            'closes_at' => $election->closes_at?->toIso8601String(),
        ];
    }

    /** Uniform rejection: JSON envelope for AJAX, back-with-errors for a form post. */
    private function reject(string $message, int $status): JsonResponse|RedirectResponse
    {
        if (request()->expectsJson()) {
            return $this->fail($message, $status);
        }

        return back()->withInput()->withErrors(['status' => $message]);
    }
}
