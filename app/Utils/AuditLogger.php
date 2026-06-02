<?php

namespace App\Utils;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    /**
     * Log an admin action.
     *
     * @param string $action Action name (e.g., 'block_user')
     * @param string|null $targetType Model name being affected
     * @param int|null $targetId ID of the model affected
     * @param string|null $description Human readable description
     * @param array|null $oldValues Previous values
     * @param array|null $newValues New values
     * @return AuditLog
     */
    public static function log($action, $targetType = null, $targetId = null, $description = null, $oldValues = null, $newValues = null)
    {
        return AuditLog::create([
            'actor_id'    => Auth::id(),
            'action'      => $action,
            'target_type' => $targetType,
            'target_id'   => $targetId,
            'description' => $description,
            'old_values'  => $oldValues,
            'new_values'  => $newValues,
            'ip_address'  => Request::ip(),
        ]);
    }
}
