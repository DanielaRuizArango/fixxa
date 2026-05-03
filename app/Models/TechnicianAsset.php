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
    ];

    /**
     * Get the technician that owns the asset.
     */
    public function technician()
    {
        return $this->belongsTo(Technician::class);
    }
}
