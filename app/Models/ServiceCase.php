<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ServiceCase extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'title',
        'description',
        'city',
        'status',
        'accepted_technician_id',
    ];

    /**
     * Get the client that created the case.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    /**
     * Get the images for the service case.
     */
    public function images(): HasMany
    {
        return $this->hasMany(CaseImage::class);
    }

    /**
     * Get the responses (quotes) for the service case.
     */
    public function responses(): HasMany
    {
        return $this->hasMany(CaseResponse::class);
    }

    /**
     * Get the technician accepted for this case.
     */
    public function acceptedTechnician(): BelongsTo
    {
        return $this->belongsTo(Technician::class, 'accepted_technician_id');
    }

    /**
     * Get the rating for this service case.
     */
    public function rating(): HasOne
    {
        return $this->hasOne(Rating::class);
    }
}
