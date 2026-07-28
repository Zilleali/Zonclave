<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

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

    // Sits after every functional resource/page, not grouped with them -
    // this is attribution, not a workflow tool.
    protected static ?int $navigationSort = 100;

    protected string $view = 'filament.pages.about-developer';
}
