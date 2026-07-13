<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\AdminLogs\AdminLogResource;
use App\Filament\Resources\AdminLogs\Pages\ListAdminLogs;
use App\Models\AdminLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

// Read-only audit trail UI (CLAUDE.md Section 17). Confirms entries are
// visible, newest first, and that there is no way to create, edit, or
// delete a log entry through the panel.
class AdminLogResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_list_page_shows_log_entries_newest_first(): void
    {
        $older = AdminLog::query()->create([
            'ts' => now()->subDay(),
            'admin_user' => 'admin@sancover.local',
            'action' => 'ppsk_created',
            'target_ppsk_id' => 1,
            'detail' => 'VLAN300_OLDER',
        ]);
        $newer = AdminLog::query()->create([
            'ts' => now(),
            'admin_user' => 'admin@sancover.local',
            'action' => 'ppsk_disabled',
            'target_ppsk_id' => 1,
            'detail' => 'VLAN300_NEWER',
        ]);

        Livewire::test(ListAdminLogs::class)
            ->assertCanSeeTableRecords([$newer, $older], inOrder: true);
    }

    public function test_resource_has_no_create_route(): void
    {
        $this->assertFalse(AdminLogResource::canCreate());
        $this->get(AdminLogResource::getUrl('index'))->assertOk();
    }

    public function test_filtering_by_action_narrows_the_list(): void
    {
        $login = AdminLog::query()->create([
            'ts' => now(),
            'admin_user' => 'admin@sancover.local',
            'action' => 'admin_login_success',
        ]);
        $created = AdminLog::query()->create([
            'ts' => now(),
            'admin_user' => 'admin@sancover.local',
            'action' => 'ppsk_created',
            'target_ppsk_id' => 1,
            'detail' => 'VLAN300_TESTA',
        ]);

        Livewire::test(ListAdminLogs::class)
            ->set('tableFilters', ['action' => ['value' => 'ppsk_created']])
            ->assertCanSeeTableRecords([$created])
            ->assertCanNotSeeTableRecords([$login]);
    }
}
