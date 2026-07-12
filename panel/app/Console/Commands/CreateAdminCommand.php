<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

// Installer contract (CLAUDE.md Section 24 / installer/install.sh
// create_admin_user): the installer calls this to provision the single
// panel admin (Section 16.1). Idempotent by email.
class CreateAdminCommand extends Command
{
    protected $signature = 'panel:create-admin {--email=} {--password=}';

    protected $description = 'Create or update the Zonclave panel admin user';

    public function handle(): int
    {
        $email = (string) $this->option('email');
        $password = (string) $this->option('password');

        if ($email === '' || $password === '') {
            $this->error('Both --email and --password are required.');

            return self::FAILURE;
        }

        $user = User::query()->firstOrNew(['email' => $email]);
        $user->name = 'Administrator';
        $user->password = Hash::make($password);
        $user->save();

        $this->info(sprintf('Admin user %s ready.', $email));

        return self::SUCCESS;
    }
}
