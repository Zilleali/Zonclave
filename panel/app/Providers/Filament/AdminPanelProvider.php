<?php

namespace App\Providers\Filament;

use App\Filament\Pages\EditProfile;
use App\Filament\Widgets\NetworkTopologyWidget;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('Peng Balous')
            ->profile(EditProfile::class)
            ->colors([
                'primary' => Color::Sky,
            ])
            // Dark mode stays the default (client's original preference,
            // 2026-07-18), but is no longer forced (client request
            // 2026-07-28) - a light theme is now reachable via Filament's
            // own built-in toggle, so it needed real styling instead of
            // just inheriting whatever was left over from the dark-only
            // design.
            ->darkMode()
            ->sidebarCollapsibleOnDesktop()
            // Filament's own default (20rem) - client feedback 2026-07-28,
            // sidebar was taking up too much of the screen. Trimmed to
            // 15rem, then widened slightly to 16.5rem the same day once the
            // Sessions sub-pages shipped - "Inactive PPSK Users" (the new
            // longest label) was truncating with an ellipsis at 15rem.
            ->sidebarWidth('16.5rem')
            // Explicit order (client request 2026-07-28: Sessions right
            // after PPSK Groups, About Developer at the very bottom).
            // Filament always renders every ungrouped item (PPSK Groups,
            // Dashboard) as one block before any named group, so this is
            // what actually controls where "Sessions" and "System" land
            // relative to each other - without it, both would tie and fall
            // back to declaration order, which is fragile.
            ->navigationGroups(['Sessions', 'System'])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                NetworkTopologyWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->renderHook(
                PanelsRenderHook::SCRIPTS_AFTER,
                fn (): View => view('filament.clipboard-script'),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): View => view('filament.theme-enhancements'),
            )
            ->renderHook(
                PanelsRenderHook::FOOTER,
                fn (): View => view('filament.footer'),
            );
    }
}
