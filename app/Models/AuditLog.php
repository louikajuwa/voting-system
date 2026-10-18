<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $fillable = [
        'organization_id', 'user_id', 'action', 'subject_type', 'subject_id', 'before', 'after', 'ip',
    ];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Write one audit row; org comes from the subject (else the acting user). */
    public static function record(string $action, ?Model $subject = null, array $before = [], array $after = []): self
    {
        $user = auth()->user();

        return static::create([
            'organization_id' => $subject?->organization_id ?? $user?->organization_id,
            'user_id' => $user?->id,
            'action' => $action,
            'subject_type' => $subject ? $subject->getMorphClass() : null,
            'subject_id' => $subject?->getKey(),
            'before' => $before ?: null,
            'after' => $after ?: null,
            'ip' => request()->ip(),
        ]);
    }
}
