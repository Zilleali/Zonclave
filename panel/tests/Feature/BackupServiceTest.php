<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AdminLogAction;
use App\Models\AdminLog;
use App\Models\Backup;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

// BackupService (CLAUDE.md Section 16.8). create()'s actual pg_dump
// invocation can only be verified against a real Postgres server - this
// suite runs on sqlite, which is exactly the guard under test here (create()
// must fail loudly on a non-Postgres connection, not silently produce a
// broken file).
class BackupServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): BackupService
    {
        return app(BackupService::class);
    }

    private function seedBackup(string $createdAt): Backup
    {
        $filename = 'zonclave-'.md5($createdAt).'.dump';
        Storage::disk('local')->put('backups/'.$filename, 'fake dump content');

        $backup = Backup::query()->create([
            'filename' => $filename,
            'disk_path' => 'backups/'.$filename,
            'size_bytes' => 18,
        ]);

        $backup->forceFill(['created_at' => $createdAt])->save();

        return $backup;
    }

    public function test_create_throws_when_the_active_connection_is_not_postgres(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('PostgreSQL');

        $this->service()->create(adminUser: 'admin@test');
    }

    public function test_delete_removes_the_row_and_the_disk_file_and_logs_it(): void
    {
        Storage::fake('local');
        $backup = $this->seedBackup(now()->toDateTimeString());

        $this->service()->delete($backup, 'admin@test');

        $this->assertSame(0, Backup::query()->count());
        Storage::disk('local')->assertMissing($backup->disk_path);
        $this->assertSame(1, AdminLog::query()
            ->where('action', AdminLogAction::BackupDeleted->value)
            ->where('admin_user', 'admin@test')
            ->count());
    }

    public function test_prune_old_backups_keeps_only_the_configured_retention_count(): void
    {
        Storage::fake('local');
        config(['zonclave.backup_retention' => 2]);

        $oldest = $this->seedBackup(now()->subDays(3)->toDateTimeString());
        $middle = $this->seedBackup(now()->subDays(2)->toDateTimeString());
        $newest = $this->seedBackup(now()->subDay()->toDateTimeString());

        $this->service()->pruneOldBackups();

        $this->assertSame(0, Backup::query()->where('id', $oldest->id)->count());
        $this->assertSame(1, Backup::query()->where('id', $middle->id)->count());
        $this->assertSame(1, Backup::query()->where('id', $newest->id)->count());
        Storage::disk('local')->assertMissing($oldest->disk_path);
        Storage::disk('local')->assertExists($newest->disk_path);
    }

    public function test_prune_old_backups_does_not_write_to_admin_log(): void
    {
        Storage::fake('local');
        config(['zonclave.backup_retention' => 0]);

        $this->seedBackup(now()->toDateTimeString());

        $this->service()->pruneOldBackups();

        $this->assertSame(0, AdminLog::query()->where('action', AdminLogAction::BackupDeleted->value)->count());
    }

    // maybeAutoBackup() (Section 16.8, client request: back up after a
    // significant registry change, not just on the daily schedule).
    public function test_maybe_auto_backup_skips_when_a_recent_backup_already_exists(): void
    {
        Storage::fake('local');
        config(['zonclave.backup_auto_cooldown_minutes' => 30]);
        $this->seedBackup(now()->subMinutes(5)->toDateTimeString());

        Log::shouldReceive('warning')->never();

        $this->service()->maybeAutoBackup('admin@test');

        // Still just the one seeded row - nothing new attempted.
        $this->assertSame(1, Backup::query()->count());
    }

    public function test_maybe_auto_backup_attempts_a_backup_and_swallows_failure_when_none_recent(): void
    {
        config(['zonclave.backup_auto_cooldown_minutes' => 30]);

        Log::shouldReceive('warning')->once()->with(Mockery::pattern('/Auto-backup after a registry change failed/'));

        // Does not throw, even though create() fails internally (sqlite,
        // not Postgres) - a PPSK/VLAN create or delete must still succeed
        // regardless of whether the incidental backup attempt works.
        $this->service()->maybeAutoBackup('admin@test');

        $this->assertSame(0, Backup::query()->count());
    }

    public function test_maybe_auto_backup_runs_again_once_the_cooldown_has_passed(): void
    {
        Storage::fake('local');
        config(['zonclave.backup_auto_cooldown_minutes' => 30]);
        $this->seedBackup(now()->subMinutes(45)->toDateTimeString());

        Log::shouldReceive('warning')->once();

        $this->service()->maybeAutoBackup('admin@test');
    }
}
