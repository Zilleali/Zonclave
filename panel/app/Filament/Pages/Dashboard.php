<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Resources\PpskGroups\PpskGroupResource;
use Filament\Pages\Dashboard as BaseDashboard;

// Section 16.2 names the PPSK list as the panel's home page ("Dashboard /
// PPSK List (home page)"), not a separate widgets page. Phase 1 has exactly
// one resource, so the root route just forwards to it instead of showing an
// otherwise-empty dashboard.
class Dashboard extends BaseDashboard
{
    public function mount(): void
    {
        $this->redirect(PpskGroupResource::getUrl());
    }
}
