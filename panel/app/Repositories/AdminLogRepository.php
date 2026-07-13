<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\AdminLogAction;
use App\Models\AdminLog;

// Append-only writer for admin_log (CLAUDE.md Section 17).
class AdminLogRepository
{
    public function log(AdminLogAction $action, ?string $adminUser, ?int $targetPpskId = null, ?string $detail = null): void
    {
        AdminLog::query()->create([
            'ts' => now(),
            'admin_user' => $adminUser,
            'action' => $action->value,
            'target_ppsk_id' => $targetPpskId,
            'detail' => $detail,
        ]);
    }
}
