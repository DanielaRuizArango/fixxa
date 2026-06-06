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

    protected $appends = [
        'average_rating',
        'ratings_count',
        'is_verified',
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
    public function getAverageRatingAttribute(): float
    {
        if (array_key_exists('ratings_avg_score', $this->attributes)) {
            $avg = $this->attributes['ratings_avg_score'];
            return $avg !== null ? round((float) $avg, 1) : 0.0;
        }
        $avg = $this->ratings()->avg('score');
        return $avg !== null ? round($avg, 1) : 0.0;
    }

    /**
     * Get the number of ratings for this technician.
     */
    public function getRatingsCountAttribute(): int
    {
        if (array_key_exists('ratings_count', $this->attributes)) {
            return (int) $this->attributes['ratings_count'];
        }
        return $this->ratings()->count();
    }
    /**
     * Get the assets (tools, certifications, work) for the technician.
     */
    public function assets(): HasMany
    {
        return $this->hasMany(TechnicianAsset::class);
    }

    /**
     * Whether the technician is fully verified (Option A):
     * approved id_document + at least one certification + all certifications approved.
     */
    public function getIsVerifiedAttribute(): bool
    {
        if ($this->relationLoaded('assets')) {
            $assets = $this->assets;

            $hasApprovedId = $assets
                ->where('type', 'id_document')
                ->where('status', 'approved')
                ->isNotEmpty();

            $certifications = $assets->where('type', 'certification');

            return $hasApprovedId
                && $certifications->isNotEmpty()
                && $certifications->every(fn ($asset) => $asset->status === 'approved');
        }

        $hasApprovedId = $this->assets()
            ->idDocuments()
            ->where('status', 'approved')
            ->exists();

        if (! $hasApprovedId) {
            return false;
        }

        $certQuery = $this->assets()->certifications();

        if (! $certQuery->exists()) {
            return false;
        }

        return ! $certQuery->where('status', '!=', 'approved')->exists();
    }
}
