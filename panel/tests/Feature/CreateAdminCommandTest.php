<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

// CLAUDE.md Section 26.9: re-running the installer must never silently
// rotate the admin's password - that's what made routine "update the
// code" re-runs feel like credentials were being lost.
class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_a_new_admin_when_none_exists(): void
    {
        $this->artisan('panel:create-admin', ['--email' => 'admin@example.com', '--password' => 'first-password'])
            ->assertSuccessful();

        $user = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('first-password', $user->password));
    }

    public function test_re_running_with_a_different_password_does_not_change_the_existing_admin(): void
    {
        $this->artisan('panel:create-admin', ['--email' => 'admin@example.com', '--password' => 'first-password'])
            ->assertSuccessful();

        $this->artisan('panel:create-admin', ['--email' => 'admin@example.com', '--password' => 'a-totally-different-password'])
            ->assertSuccessful();

        $user = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('first-password', $user->password));
        $this->assertFalse(Hash::check('a-totally-different-password', $user->password));
    }
}
