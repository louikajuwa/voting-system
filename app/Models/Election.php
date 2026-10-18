<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Election extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = ['draft', 'scheduled', 'live', 'closed'];

    protected $fillable = [
        'organization_id', 'title', 'slug', 'description',
        'status', 'hide_results', 'opens_at', 'closes_at',
    ];

    protected $casts = [
        'hide_results' => 'boolean',
        'opens_at' => 'datetime',
        'closes_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->where('status', 'live');
    }

    /** A ballot is open only while live and (if set) within its window. */
    public function isOpen(): bool
    {
        if ($this->status !== 'live') {
            return false;
        }
        $now = now();
        if ($this->opens_at && $now->lt($this->opens_at)) {
            return false;
        }
        if ($this->closes_at && $now->gt($this->closes_at)) {
            return false;
        }
        return true;
    }

    /** [candidate_id => vote count] for this election, including zero-vote candidates. */
    public function tallies(): array
    {
        $counts = $this->votes()
            ->selectRaw('candidate_id, count(*) as c')
            ->groupBy('candidate_id')
            ->pluck('c', 'candidate_id');

        return $this->candidates
            ->mapWithKeys(fn ($c) => [$c->id => (int) ($counts[$c->id] ?? 0)])
            ->all();
    }

    /** Live standings are visible unless the admin embargoed them while voting is open. */
    public function resultsVisible(): bool
    {
        return $this->status === 'closed' || ! $this->hide_results;
    }

    /** Candidate id with the most votes, or null on a tie/no votes. */
    public function winnerId(): ?int
    {
        $tallies = $this->tallies();
        if (array_sum($tallies) === 0) {
            return null;
        }
        $max = max($tallies);
        $leaders = array_keys($tallies, $max, true);

        return count($leaders) === 1 ? $leaders[0] : null;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
