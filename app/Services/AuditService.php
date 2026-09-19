<?php

namespace App\Services;

use App\Models\AdminAction;
use App\Models\User;

class AuditService
{
    /**
     * Log an admin action for auditing purposes.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function logAction(
        User $admin,
        string $action,
        string $entityType,
        string $entityId,
        ?string $reason = null,
        array $metadata = []
    ): AdminAction {
        return AdminAction::create([
            'admin_id' => $admin->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => (string) $entityId,
            'reason' => $reason,
            'metadata' => $metadata,
        ]);
    }
}
