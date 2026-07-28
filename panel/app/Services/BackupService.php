<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AdminLogAction;
use App\Models\Backup;
use App\Repositories\AdminLogRepository;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

// The only write path for backups (CLAUDE.md Section 16.8), mirroring
// TunnelEgressIpService's shape. A backup is a full `pg_dump --format=custom`
// of the active database - Postgres-only by design, since this backs up the
// *production* database, not whatever driver a given environment happens to
// use for testing. create() is called from two places (the on-demand
// Filament action and the scheduled `zonclave:backup` command) so both go
// through identical logging and retention.
class BackupService
{
    private const DISK = 'local';

    private const DIRECTORY = 'backups';

    public function __construct(
        private readonly AdminLogRepository $auditLog,
    ) {}

    public function create(?string $adminUser): Backup
    {
        $connectionName = (string) config('database.default');
        $connection = (array) config("database.connections.{$connectionName}");

        if (($connection['driver'] ?? null) !== 'pgsql') {
            throw new RuntimeException(sprintf(
                'Backups require a PostgreSQL connection; the active connection ("%s") uses driver "%s".',
                $connectionName,
                $connection['driver'] ?? 'unknown',
            ));
        }

        $disk = Storage::disk(self::DISK);
        $disk->makeDirectory(self::DIRECTORY);

        $filename = sprintf('zonclave-%s.dump', now()->format('Ymd-His'));
        $diskPath = self::DIRECTORY.'/'.$filename;

        $result = Process::env(['PGPASSWORD' => (string) ($connection['password'] ?? '')])
            ->timeout(300)
            ->run([
                'pg_dump',
                '--format=custom',
                '--no-owner',
                '--no-acl',
                '--host='.$connection['host'],
                '--port='.$connection['port'],
                '--username='.$connection['username'],
                '--file='.$disk->path($diskPath),
                (string) $connection['database'],
            ]);

        if (! $result->successful()) {
            $disk->delete($diskPath);

            throw new RuntimeException('pg_dump failed: '.$result->errorOutput());
        }

        $backup = Backup::query()->create([
            'filename' => $filename,
            'disk_path' => $diskPath,
            'size_bytes' => $disk->size($diskPath),
        ]);

        $this->auditLog->log(AdminLogAction::BackupCreated, $adminUser, null, $filename);

        $this->pruneOldBackups();

        return $backup;
    }

    public function delete(Backup $backup, ?string $adminUser): void
    {
        Storage::disk(self::DISK)->delete($backup->disk_path);
        $filename = $backup->filename;
        $backup->delete();

        $this->auditLog->log(AdminLogAction::BackupDeleted, $adminUser, null, $filename);
    }

    // Kept separate from create() so retention can be unit-tested against
    // seeded Backup rows without needing a real pg_dump/Postgres connection.
    // Routine auto-pruning isn't logged to admin_log (Section 17 is about
    // admin actions; this is housekeeping, not one).
    public function pruneOldBackups(): void
    {
        $keep = (int) config('zonclave.backup_retention');

        // whereNotIn + limit rather than an offset-only query - portable
        // across sqlite (this test suite) and Postgres (production)
        // without relying on OFFSET-without-LIMIT support.
        $idsToKeep = Backup::query()->orderByDesc('created_at')->limit($keep)->pluck('id');

        Backup::query()
            ->whereNotIn('id', $idsToKeep)
            ->get()
            ->each(function (Backup $backup): void {
                Storage::disk(self::DISK)->delete($backup->disk_path);
                $backup->delete();
            });
    }
}
