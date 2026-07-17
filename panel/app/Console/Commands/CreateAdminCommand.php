<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

// Installer contract (CLAUDE.md Section 24 / installer/install-ubuntu22.04.sh
// create_admin_user): the installer calls this to provision the single
// panel admin (Section 16.1). Idempotent by email, and deliberately never
// overwrites an existing admin's password (CLAUDE.md Section 26.9) - the
// installer generates a fresh random --password on every run, and a
// re-run silently resetting the admin's login is exactly what made
// routine re-runs feel like "my credentials keep getting lost." The
// Profile page (Section 16.1) is the only intended way to change an
// existing admin's password.
class CreateAdminCommand extends Command
{
    protected $signature = 'panel:create-admin {--email=} {--password=}';

    protected $description = 'Create the Zonclave panel admin user, if it does not already exist';

    public function handle(): int
    {
        $email = (string) $this->option('email');
        $password = (string) $this->option('password');

        if ($email === '' || $password === '') {
            $this->error('Both --email and --password are required.');

            return self::FAILURE;
        }

        if (User::query()->where('email', $email)->exists()) {
            $this->info(sprintf('Admin user %s already exists; password left unchanged.', $email));

            return self::SUCCESS;
        }

        $user = new User(['email' => $email]);
        $user->name = 'Administrator';
        $user->password = Hash::make($password);
        $user->save();

        $this->info(sprintf('Admin user %s created.', $email));

        return self::SUCCESS;
    }
}
