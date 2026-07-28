<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

// An in-panel counterpart to the public /about page (resources/views/
// about.blade.php) - same Developer & Network Engineer story and social
// links, without leaving the admin panel (client request 2026-07-28).
// Static content only: no model, no table, nothing to authorize beyond the
// panel's own auth guard every other page already sits behind.
class AboutDeveloper extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static ?string $navigationLabel = 'About Developer';

    protected static ?string $title = 'About the Developer';

    // Grouped under "System" (client request 2026-07-28) - Filament always
    // renders ungrouped items as one block before any named group, so this
    // page had to join a named group to render after "Sessions" too. The
    // highest sort number in the group keeps it last within it - "bottom
    // bottom" of the whole sidebar, not just last among ungrouped items.
    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 100;

    protected string $view = 'filament.pages.about-developer';
}
