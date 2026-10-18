<?php

namespace App\Services;

use App\Events\VoteCast;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class CastVote
{
    /**
     * Record one single-choice vote.
     *
     * ponytail: email dedup is soft identity (multi-email = multi-vote). Swap for an
     * invite-token roster or login when true one-person-one-vote is needed.
     *
     * @throws ValidationException on a closed election, bad candidate, or duplicate email.
     */
    public function handle(Election $election, int $candidateId, string $email, ?string $ip = null): Vote
    {
        if (! $election->isOpen()) {
            throw ValidationException::withMessages(['candidate_id' => 'This election is not open for voting.']);
        }

        if (! $election->candidates()->whereKey($candidateId)->where('status', 'approved')->exists()) {
            throw ValidationException::withMessages(['candidate_id' => 'Please choose a valid candidate.']);
        }

        try {
            $vote = Vote::create([
                'election_id' => $election->id,
                'candidate_id' => $candidateId,
                'voter_email' => mb_strtolower(trim($email)),
                'ip' => $ip,
            ]);
        } catch (QueryException $e) {
            // 23000 = integrity constraint violation -> this email already voted here.
            if ($e->getCode() === '23000') {
                throw ValidationException::withMessages(['email' => 'This email has already voted in this election.']);
            }
            throw $e;
        }

        // Sync listener publishes the new tally to the Socket.IO bridge.
        VoteCast::dispatch($election->id);

        return $vote;
    }
}
