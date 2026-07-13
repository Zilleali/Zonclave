<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\AdminLogAction;
use PHPUnit\Framework\TestCase;

// admin_log.action values (CLAUDE.md Section 17): single source of truth
// for the writers (PpskService, LogAuthenticationEvents) and the reader
// (AdminLogsTable), so a case can't exist on one side without the other.
class AdminLogActionTest extends TestCase
{
    public function test_every_case_has_a_distinct_label(): void
    {
        $labels = array_map(fn (AdminLogAction $a): string => $a->label(), AdminLogAction::cases());

        $this->assertCount(count(AdminLogAction::cases()), array_unique($labels));
    }

    public function test_failed_login_and_deleted_are_danger(): void
    {
        $this->assertSame('danger', AdminLogAction::AdminLoginFailed->color());
        $this->assertSame('danger', AdminLogAction::PpskDeleted->color());
    }

    public function test_disabled_is_warning(): void
    {
        $this->assertSame('warning', AdminLogAction::PpskDisabled->color());
    }

    public function test_created_enabled_and_login_success_are_success(): void
    {
        $this->assertSame('success', AdminLogAction::PpskCreated->color());
        $this->assertSame('success', AdminLogAction::PpskEnabled->color());
        $this->assertSame('success', AdminLogAction::AdminLoginSuccess->color());
    }
}
