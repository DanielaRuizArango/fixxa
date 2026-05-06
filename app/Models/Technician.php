<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Technician extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'experience',
        'title',
        'is_available',
        'working_hours',
    ];

    protected $casts = [
        'is_available' => 'boolean',
    ];

    /**
     * Get the user that owns the technician profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the responses provided by the technician.
     */
    public function caseResponses(): HasMany
    {
        return $this->hasMany(CaseResponse::class);
    }

    /**
     * Get all ratings received by this technician.
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }

    /**
     * Get the average rating score for this technician.
     */
    public function getAverageRatingAttribute(): ?float
    {
        $avg = $this->ratings()->avg('score');
        return $avg !== null ? round($avg, 1) : null;
    }
    /**
     * Get the assets (tools, certifications, work) for the technician.
     */
    public function assets(): HasMany
    {
        return $this->hasMany(TechnicianAsset::class);
    }
}
