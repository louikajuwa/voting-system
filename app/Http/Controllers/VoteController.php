<?php

namespace App\Http\Controllers;

use App\Http\Requests\CastVoteRequest;
use App\Models\Election;
use App\Services\CastVote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class VoteController extends Controller
{
    public function show(Election $election): View
    {
        abort_if($election->status === 'draft', 404);
        $election->load(['candidates' => fn ($q) => $q->where('status', 'approved')]);

        // If voting isn't currently open, show a status page instead of a votable ballot.
        if (! $election->isOpen()) {
            return view('vote.closed', compact('election'));
        }

        return view('vote.ballot', compact('election'));
    }

    public function store(CastVoteRequest $request, Election $election, CastVote $castVote): JsonResponse|RedirectResponse
    {
        // Throws ValidationException (closed election / bad candidate / duplicate email);
        // for AJAX that surfaces as a 422 JSON, otherwise a redirect back with errors.
        $castVote->handle(
            $election,
            $request->integer('candidate_id'),
            (string) $request->string('email'),
            $request->ip(),
        );

        if ($request->expectsJson()) {
            return $this->ok('Your vote is counted.');
        }

        return redirect()->route('vote.thanks', $election)->with('voted_election', $election->id);
    }

    /** Only shown right after voting; anyone else is sent to the ballot. */
    public function thanks(Election $election): View|RedirectResponse
    {
        if (session('voted_election') !== $election->id) {
            return redirect()->route('vote.show', $election);
        }
        session()->keep('voted_election'); // survive a refresh of the thanks page

        return view('vote.thanks', compact('election'));
    }
}
