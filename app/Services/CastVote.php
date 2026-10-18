<?php

namespace App\Services;

use App\Events\VoteCast;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
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
        $email = mb_strtolower(trim($email));

        try {
            $vote = DB::transaction(function () use ($election, $candidateId, $email, $ip) {
                // Serialize against status/window changes: re-read the election under a row lock.
                $fresh = Election::whereKey($election->id)->lockForUpdate()->first();

                if (! $fresh || ! $fresh->isOpen()) {
                    throw ValidationException::withMessages(['candidate_id' => 'This election is not open for voting.']);
                }

                if (! $fresh->candidates()->whereKey($candidateId)->where('status', 'approved')->exists()) {
                    throw ValidationException::withMessages(['candidate_id' => 'Please choose a valid candidate.']);
                }

                return Vote::create([
                    'election_id' => $fresh->id,
                    'candidate_id' => $candidateId,
                    'voter_email' => $email,
                    'ip' => $ip,
                ]);
            });
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                throw ValidationException::withMessages(['email' => 'This email has already voted in this election.']);
            }
            throw $e;
        }

        // Dispatched after commit; sync listener publishes the new tally to the Socket.IO bridge.
        VoteCast::dispatch($election->id);

        return $vote;
    }

    /** MySQL 1062 duplicate entry / SQLite "UNIQUE constraint failed". */
    private function isUniqueViolation(QueryException $e): bool
    {
        return ($e->errorInfo[1] ?? null) === 1062
            || str_contains($e->getMessage(), 'UNIQUE constraint failed');
    }
}
