<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\PpskGroups\PpskGroupResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Section 16.2: the panel's home page is the PPSK list, not a separate
// widgets dashboard.
class DashboardRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_root_redirects_to_the_ppsk_list(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin')
            ->assertRedirect(PpskGroupResource::getUrl());
    }
}
