<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;
use RuntimeException;

// Scheduled trigger for App\Services\BackupService (CLAUDE.md Section 16.8).
// Registered daily in bootstrap/app.php's withSchedule(). $adminUser is null
// here (system-triggered, not an admin action) - the same nullable
// convention every other service in this app already uses for cron-driven
// calls.
class BackupCommand extends Command
{
    protected $signature = 'zonclave:backup';

    protected $description = 'Create a full database backup';

    public function handle(BackupService $backups): int
    {
        try {
            $backup = $backups->create(adminUser: null);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('Backup created: %s', $backup->filename));

        return self::SUCCESS;
    }
}
