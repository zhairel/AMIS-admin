<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffVerification extends Model
{
    use HasFactory;

    protected $table = 'staff_verifications';

    protected $fillable = [
        'slug',
        'full_name',
        'academic_title',
        'position',
        'department',
        'official_email',
        'phone_number',
        'office_address',
        'photo_url',
        'badge_title',
        'official_website',
        'status',
        'bio_notes',
        'verified_since',
    ];

    protected $casts = [
        'verified_since' => 'datetime',
    ];

    /**
     * Scope only active verified staff.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Get full verification URL.
     */
    public function getVerificationUrlAttribute(): string
    {
        return 'https://amis.edu.ph/verify/' . $this->slug;
    }

    /**
     * Get display name with academic title.
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->academic_title && !str_contains($this->full_name, $this->academic_title)) {
            return "{$this->full_name}, {$this->academic_title}";
        }
        return $this->full_name;
    }

    /**
     * Check if staff is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
