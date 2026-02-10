<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceCase extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'title',
        'description',
        'status',
    ];

    /**
     * Get the client that created the case.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
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
}
