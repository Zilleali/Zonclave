<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SessionStatus;
use App\Models\RadiusAccounting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// RadiusAccounting::effectiveStatus() (CLAUDE.md Section 16.6): a session
// with no Acct-Stop is only "Connected" while recently active - UniFi
// doesn't always send a clean stop for a device that dies or loses power,
// so a session going quiet must eventually read as Stale, not Connected
// forever.
class RadiusAccountingTest extends TestCase
{
    use RefreshDatabase;

    private function makeSession(array $overrides = []): RadiusAccounting
    {
        return RadiusAccounting::query()->create(array_merge([
            'acctsessionid' => 'sess-'.bin2hex(random_bytes(4)),
            'acctuniqueid' => 'uniq-'.bin2hex(random_bytes(8)),
            'username' => 'ppsk_group001',
            'acctstarttime' => now()->subHour(),
            'acctupdatetime' => now(),
        ], $overrides));
    }

    public function test_recent_activity_with_no_stop_is_connected(): void
    {
        $session = $this->makeSession(['acctupdatetime' => now()->subMinutes(2)]);

        $this->assertSame(SessionStatus::Connected, $session->effectiveStatus());
    }

    public function test_stale_after_fifteen_minutes_of_silence(): void
    {
        $session = $this->makeSession(['acctupdatetime' => now()->subMinutes(16)]);

        $this->assertSame(SessionStatus::Stale, $session->effectiveStatus());
    }

    public function test_falls_back_to_start_time_when_no_interim_update_recorded(): void
    {
        $session = $this->makeSession(['acctstarttime' => now()->subMinutes(20), 'acctupdatetime' => null]);

        $this->assertSame(SessionStatus::Stale, $session->effectiveStatus());
    }

    public function test_a_stop_time_always_means_disconnected_even_if_recent(): void
    {
        $session = $this->makeSession([
            'acctupdatetime' => now(),
            'acctstoptime' => now(),
        ]);

        $this->assertSame(SessionStatus::Disconnected, $session->effectiveStatus());
    }

    public function test_open_scope_only_returns_sessions_without_a_stop_time(): void
    {
        $open = $this->makeSession();
        $this->makeSession(['acctstoptime' => now()]);

        $result = RadiusAccounting::query()->open()->get();

        $this->assertCount(1, $result);
        $this->assertSame($open->radacctid, $result->first()->radacctid);
    }
}
