<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\AdminLogAction;
use App\Models\User;
use App\Repositories\AdminLogRepository;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;

// Admin login success/failure -> admin_log (CLAUDE.md Section 17). Only
// identifiers are logged, never credentials (Section 23.2).
class LogAuthenticationEvents
{
    public function __construct(private readonly AdminLogRepository $auditLog) {}

    public function handleLogin(Login $event): void
    {
        $email = $event->user instanceof User ? $event->user->email : null;

        $this->auditLog->log(AdminLogAction::AdminLoginSuccess, $email);
    }

    public function handleFailed(Failed $event): void
    {
        $email = is_string($event->credentials['email'] ?? null) ? $event->credentials['email'] : null;

        $this->auditLog->log(AdminLogAction::AdminLoginFailed, $email);
    }
}
