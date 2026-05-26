<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TechnicianAsset extends Model
{
    protected $fillable = [
        'technician_id',
        'type',
        'image_path',
        'description',
        'status',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    /**
     * Get the technician that owns the asset.
     */
    public function technician()
    {
        return $this->belongsTo(Technician::class);
    }

    /**
     * Get the admin user who reviewed this asset.
     */
    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Scope to filter only certification assets.
     */
    public function scopeCertifications($query)
    {
        return $query->where('type', 'certification');
    }

    /**
     * Scope to filter only id_document assets (cédulas).
     */
    public function scopeIdDocuments($query)
    {
        return $query->where('type', 'id_document');
    }
}
