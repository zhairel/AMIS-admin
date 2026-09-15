<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RosterLock extends Model
{
    protected $fillable = [
        'roster_type',
        'roster_identifier',
        'school_year',
        'status',
        'locked_at',
        'locked_by',
        'unlocked_at',
        'unlocked_by',
        'unlock_reason',
    ];

    protected $casts = [
        'locked_at' => 'datetime',
        'unlocked_at' => 'datetime',
    ];

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function unlockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'unlocked_by');
    }

    public static function isLocked(string $type, string $identifier, string $schoolYear = '2026-2027'): bool
    {
        return static::where('roster_type', $type)
            ->where('roster_identifier', (string) $identifier)
            ->where('school_year', $schoolYear)
            ->where('status', 'locked')
            ->exists();
    }

    public static function getLock(string $type, string $identifier, string $schoolYear = '2026-2027'): ?self
    {
        return static::where('roster_type', $type)
            ->where('roster_identifier', (string) $identifier)
            ->where('school_year', $schoolYear)
            ->with(['lockedBy', 'unlockedBy'])
            ->first();
    }
}
