<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Backups\BackupResource;
use App\Filament\Resources\Backups\Pages\ListBackups;
use App\Models\Backup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

// Backup list UI (CLAUDE.md Section 16.8). No create route - a backup isn't
// something an admin fills a form in for, only "Backup now" or the daily
// schedule produce one.
class BackupResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function seedBackup(): Backup
    {
        Storage::disk('local')->put('backups/test.dump', 'fake dump content');

        return Backup::query()->create([
            'filename' => 'test.dump',
            'disk_path' => 'backups/test.dump',
            'size_bytes' => 18,
        ]);
    }

    public function test_resource_has_no_create_route(): void
    {
        $this->actingAs(User::factory()->create());

        $this->assertFalse(BackupResource::canCreate());
        $this->get(BackupResource::getUrl('index'))->assertOk();
    }

    public function test_list_page_shows_seeded_backups(): void
    {
        $this->actingAs(User::factory()->create());
        $backup = $this->seedBackup();

        Livewire::test(ListBackups::class)
            ->assertCanSeeTableRecords([$backup]);
    }

    public function test_delete_action_removes_the_backup(): void
    {
        $this->actingAs(User::factory()->create());
        $backup = $this->seedBackup();

        Livewire::test(ListBackups::class)
            ->callTableAction('delete', $backup);

        $this->assertSame(0, Backup::query()->count());
        Storage::disk('local')->assertMissing('backups/test.dump');
    }

    public function test_download_route_streams_the_file_when_authenticated(): void
    {
        $this->actingAs(User::factory()->create());
        $backup = $this->seedBackup();

        $this->get(route('backups.download', $backup))->assertOk();
    }

    public function test_download_route_is_not_reachable_when_unauthenticated(): void
    {
        $backup = $this->seedBackup();

        $response = $this->get(route('backups.download', $backup));

        $response->assertRedirect();
    }
}
