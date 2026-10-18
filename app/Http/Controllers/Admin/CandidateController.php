<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCandidateRequest;
use App\Models\Candidate;
use App\Models\Election;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CandidateController extends Controller
{
    public function create(Election $election): View
    {
        $this->authorizeOrg($election);

        return view('candidates.create', [
            'election' => $election,
            'candidate' => new Candidate(['status' => 'approved']),
        ]);
    }

    /** JSON: searchable + paginated candidate list for the dynamic admin table. */
    public function list(Election $election): \Illuminate\Http\JsonResponse
    {
        $this->authorizeOrg($election);
        $search = trim((string) request('search'));

        $query = $election->candidates()->orderBy('name');
        if ($search !== '') {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('party', 'like', "%{$search}%"));
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

        $election->candidates()->create([
            ...$request->safe()->except('photo'),
            'photo' => $this->storePhoto($request),
        ]);

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
        if ($photo = $this->storePhoto($request)) {
            $this->deletePhoto($candidate);
            $data['photo'] = $photo;
        }
        $candidate->update($data);

        return $this->respond(route('admin.elections.show', $election), 'Candidate updated.');
    }

    public function destroy(Election $election, Candidate $candidate): JsonResponse|RedirectResponse
    {
        $this->authorizeOrg($election);
        $this->deletePhoto($candidate);
        $candidate->delete();

        return $this->respond(route('admin.elections.show', $election), 'Candidate removed.');
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

    private function authorizeOrg(Election $election): void
    {
        abort_unless($election->organization_id === request()->user()->organization_id, 403);
    }
}
