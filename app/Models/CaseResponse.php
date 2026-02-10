<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseResponse extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_case_id',
        'technician_id',
        'estimated_cost',
        'questions',
    ];

    /**
     * Get the service case that this response belongs to.
     */
    public function serviceCase(): BelongsTo
    {
        return $this->belongsTo(ServiceCase::class);
    }

    /**
     * Get the technician that provided this response.
     */
    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }
}
