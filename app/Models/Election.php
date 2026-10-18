<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Election extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = ['draft', 'scheduled', 'live', 'closed'];

    /** Allowed status moves; closed is terminal. */
    public const TRANSITIONS = [
        'draft' => ['scheduled', 'live'],
        'scheduled' => ['live', 'draft'],
        'live' => ['closed'],
        'closed' => [],
    ];

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

    public function approvedCandidates(): HasMany
    {
        return $this->hasMany(Candidate::class)->where('status', 'approved');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    /** Live AND inside its window — mirrors isOpen(). */
    public function scopeLive(Builder $query): Builder
    {
        return $query->where('status', 'live')
            ->where(fn ($q) => $q->whereNull('opens_at')->orWhere('opens_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('closes_at')->orWhere('closes_at', '>', now()));
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
        if ($this->closes_at && $now->gte($this->closes_at)) {
            return false;
        }

        return true;
    }

    /** draft->scheduled|live, scheduled->live|draft, live->closed; closed is terminal. */
    public function canTransitionTo(string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$this->status] ?? [], true);
    }

    /** Timezone of the owning organization (UTC fallback). */
    public function timezone(): string
    {
        return $this->organization?->timezone ?: 'UTC';
    }

    /** Format a datetime column in the org timezone (default: datetime-local input value). */
    public function formatLocal(string $column, string $format = 'Y-m-d\TH:i'): string
    {
        $value = $this->{$column};

        return $value ? $value->copy()->setTimezone($this->timezone())->format($format) : '';
    }

    /** Parse org-local form input into UTC (null when empty). */
    public static function parseLocal(?string $value, string $timezone): ?Carbon
    {
        return filled($value) ? Carbon::parse($value, $timezone ?: 'UTC')->utc() : null;
    }

    /** UTC [start, end) of today's date in the given timezone. */
    public static function todayRangeUtc(string $timezone): array
    {
        $start = now($timezone ?: 'UTC')->startOfDay();

        return [$start->copy()->utc(), $start->copy()->addDay()->utc()];
    }

    /**
     * [candidate_id => vote count] for approved candidates, including zero-vote ones.
     * Uses the loaded approvedCandidates relation when present (no extra query).
     */
    public function tallies(): array
    {
        $counts = $this->votes()
            ->selectRaw('candidate_id, count(*) as c')
            ->groupBy('candidate_id')
            ->pluck('c', 'candidate_id');

        $candidates = $this->relationLoaded('approvedCandidates')
            ? $this->approvedCandidates
            : $this->approvedCandidates()->get(['id']);

        return $candidates
            ->mapWithKeys(fn ($c) => [$c->id => (int) ($counts[$c->id] ?? 0)])
            ->all();
    }

    /** Live standings are visible unless the admin embargoed them while voting is open. */
    public function resultsVisible(): bool
    {
        return $this->status === 'closed' || ! $this->hide_results;
    }

    /** Candidate id with the most votes, or null on a tie/no votes. */
    public function winnerId(?array $tallies = null): ?int
    {
        $tallies ??= $this->tallies();
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
