<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\ActivePpskUsers\ActivePpskUserResource;
use App\Filament\Resources\ActivePpskUsers\Pages\ListActivePpskUsers;
use App\Filament\Resources\InactivePpskUsers\InactivePpskUserResource;
use App\Filament\Resources\InactivePpskUsers\Pages\ListInactivePpskUsers;
use App\Filament\Resources\StaleSessions\Pages\ListStaleSessions;
use App\Filament\Resources\StaleSessions\StaleSessionResource;
use App\Models\RadiusAccounting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

// The Active PPSK Users / Stale Sessions / Inactive PPSK Users sub-pages
// (CLAUDE.md Section 16.6, client request 2026-07-28) - each a
// RadiusAccounting::scopeWithStatus() slice of the same "All Sessions" data
// SessionLogResourceTest already covers, sitting under a "Sessions" nav
// group. Confirms each page only shows its own status bucket and never the
// others.
class SessionStatusPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

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

    public function test_active_page_shows_only_connected_sessions(): void
    {
        $connected = $this->makeSession();
        $stale = $this->makeSession(['acctupdatetime' => now()->subMinutes(20)]);
        $disconnected = $this->makeSession(['acctstoptime' => now()]);

        Livewire::test(ListActivePpskUsers::class)
            ->assertCanSeeTableRecords([$connected])
            ->assertCanNotSeeTableRecords([$stale, $disconnected]);
    }

    public function test_stale_page_shows_only_stale_sessions(): void
    {
        $connected = $this->makeSession();
        $stale = $this->makeSession(['acctupdatetime' => now()->subMinutes(20)]);
        $disconnected = $this->makeSession(['acctstoptime' => now()]);

        Livewire::test(ListStaleSessions::class)
            ->assertCanSeeTableRecords([$stale])
            ->assertCanNotSeeTableRecords([$connected, $disconnected]);
    }

    public function test_inactive_page_shows_only_disconnected_sessions(): void
    {
        $connected = $this->makeSession();
        $stale = $this->makeSession(['acctupdatetime' => now()->subMinutes(20)]);
        $disconnected = $this->makeSession(['acctstoptime' => now()]);

        Livewire::test(ListInactivePpskUsers::class)
            ->assertCanSeeTableRecords([$disconnected])
            ->assertCanNotSeeTableRecords([$connected, $stale]);
    }

    public function test_none_of_the_sub_pages_can_create(): void
    {
        $this->assertFalse(ActivePpskUserResource::canCreate());
        $this->assertFalse(StaleSessionResource::canCreate());
        $this->assertFalse(InactivePpskUserResource::canCreate());
    }

    public function test_active_and_stale_navigation_badges_reflect_live_counts(): void
    {
        $this->makeSession();
        $this->makeSession(['acctupdatetime' => now()->subMinutes(20)]);
        $this->makeSession(['acctupdatetime' => now()->subMinutes(20)]);

        $this->assertSame('1', ActivePpskUserResource::getNavigationBadge());
        $this->assertSame('2', StaleSessionResource::getNavigationBadge());
    }

    public function test_navigation_badges_are_hidden_when_zero(): void
    {
        $this->assertNull(ActivePpskUserResource::getNavigationBadge());
        $this->assertNull(StaleSessionResource::getNavigationBadge());
    }
}
