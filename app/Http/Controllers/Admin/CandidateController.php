<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCandidateRequest;
use App\Models\AuditLog;
use App\Models\Candidate;
use App\Models\Election;
use App\Support\Like;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CandidateController extends Controller
{
    use AuthorizesOrganization;

    public function create(Election $election): View
    {
        $this->authorizeOrg($election);

        return view('candidates.create', [
            'election' => $election,
            'candidate' => new Candidate(['status' => 'approved']),
        ]);
    }

    /** JSON: searchable + paginated candidate list for the dynamic admin table. */
    public function list(Election $election): JsonResponse
    {
        $this->authorizeOrg($election);
        $search = trim((string) request('search'));

        $query = $election->candidates()->orderBy('name');
        if ($search !== '') {
            $query->where(fn ($q) => $q->where('name', 'like', '%'.Like::escape($search).'%')->orWhere('party', 'like', '%'.Like::escape($search).'%'));
        }

        $p = $query->paginate(8)->withQueryString();

        return $this->paginated($p, fn ($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'position' => $c->position,
            'party' => $c->party,
            'bio' => $c->bio,
            'manifesto' => $c->manifesto,
            'status' => $c->status,
            'photo' => $c->photoUrl(),
            'update_url' => route('admin.elections.candidates.update', [$election, $c]),
            'delete_url' => route('admin.elections.candidates.destroy', [$election, $c]),
        ]);
    }

    public function store(StoreCandidateRequest $request, Election $election): JsonResponse|RedirectResponse
    {
        $this->authorizeOrg($election);

        $candidate = $election->candidates()->create([
            ...$request->safe()->except('photo'),
            'photo' => $this->storePhoto($request),
        ]);
        AuditLog::record('candidate.created', $election, [], $this->summary($candidate));

        return $this->respond(route('admin.elections.show', $election), 'Candidate added.');
    }

    public function edit(Election $election, Candidate $candidate): View
    {
        $this->authorizeOrg($election);

        return view('candidates.edit', compact('election', 'candidate'));
    }

    public function update(StoreCandidateRequest $request, Election $election, Candidate $candidate): JsonResponse|RedirectResponse
    {
        $this->authorizeOrg($election);

        $data = $request->safe()->except('photo');
        if (($data['status'] ?? $candidate->status) !== $candidate->status && $this->isLocked($election)) {
            return $this->locked('Candidate status cannot be changed once the election is live, closed, or has votes.');
        }

        $before = $this->summary($candidate);
        if ($photo = $this->storePhoto($request)) {
            $this->deletePhoto($candidate);
            $data['photo'] = $photo;
        }
        $candidate->update($data);
        AuditLog::record('candidate.updated', $election, $before, $this->summary($candidate));

        return $this->respond(route('admin.elections.show', $election), 'Candidate updated.');
    }

    public function destroy(Election $election, Candidate $candidate): JsonResponse|RedirectResponse
    {
        $this->authorizeOrg($election);
        if ($this->isLocked($election)) {
            return $this->locked('Candidates cannot be removed once the election is live, closed, or has votes.');
        }

        $this->deletePhoto($candidate);
        $candidate->delete();
        AuditLog::record('candidate.deleted', $election, $this->summary($candidate));

        return $this->respond(route('admin.elections.show', $election), 'Candidate removed.');
    }

    private function isLocked(Election $election): bool
    {
        return in_array($election->status, ['live', 'closed'], true) || $election->votes()->exists();
    }

    private function locked(string $message): JsonResponse|RedirectResponse
    {
        if (request()->expectsJson()) {
            return $this->fail($message, 409);
        }

        return back()->withErrors(['status' => $message]);
    }

    private function summary(Candidate $candidate): array
    {
        return ['candidate_id' => $candidate->id, 'name' => $candidate->name, 'status' => $candidate->status];
    }

    /** Store the uploaded photo on the public disk; returns the path or null. */
    private function storePhoto(StoreCandidateRequest $request): ?string
    {
        if (! $request->hasFile('photo')) {
            return null;
        }

        return $request->file('photo')->store('candidates', 'public');
    }

    private function deletePhoto(Candidate $candidate): void
    {
        if ($candidate->photo) {
            Storage::disk('public')->delete($candidate->photo);
        }
    }
}
