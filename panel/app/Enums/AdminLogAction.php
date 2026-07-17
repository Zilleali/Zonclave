<?php

declare(strict_types=1);

namespace App\Enums;

// admin_log.action values (CLAUDE.md Section 17). Single source of truth
// for the action string, its display label, and its badge color, so the
// writers (PpskService, LogAuthenticationEvents) and AdminLogsTable can
// never drift out of sync the way two independently-maintained string
// lists could.
enum AdminLogAction: string
{
    case AdminLoginSuccess = 'admin_login_success';
    case AdminLoginFailed = 'admin_login_failed';
    case PpskCreated = 'ppsk_created';
    case PpskUpdated = 'ppsk_updated';
    case PpskEnabled = 'ppsk_enabled';
    case PpskDisabled = 'ppsk_disabled';
    case PpskDeleted = 'ppsk_deleted';
    case PpskPasswordRegenerated = 'ppsk_password_regenerated';

    public function label(): string
    {
        return match ($this) {
            self::AdminLoginSuccess => 'Admin login success',
            self::AdminLoginFailed => 'Admin login failed',
            self::PpskCreated => 'PPSK created',
            self::PpskUpdated => 'PPSK updated',
            self::PpskEnabled => 'PPSK enabled',
            self::PpskDisabled => 'PPSK disabled',
            self::PpskDeleted => 'PPSK deleted',
            self::PpskPasswordRegenerated => 'PPSK password regenerated',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::AdminLoginFailed, self::PpskDeleted => 'danger',
            self::PpskDisabled => 'warning',
            default => 'success',
        };
    }

    // Icon per action, so the list reads at a glance (Sancover UX request
    // 2026-07-17, admin log visual clarity pass) - purely cosmetic, no new
    // data or behavior.
    public function icon(): string
    {
        return match ($this) {
            self::AdminLoginSuccess => 'heroicon-o-arrow-right-on-rectangle',
            self::AdminLoginFailed => 'heroicon-o-lock-closed',
            self::PpskCreated => 'heroicon-o-plus-circle',
            self::PpskUpdated => 'heroicon-o-pencil-square',
            self::PpskEnabled => 'heroicon-o-play-circle',
            self::PpskDisabled => 'heroicon-o-pause-circle',
            self::PpskDeleted => 'heroicon-o-trash',
            self::PpskPasswordRegenerated => 'heroicon-o-arrow-path',
        };
    }
}
