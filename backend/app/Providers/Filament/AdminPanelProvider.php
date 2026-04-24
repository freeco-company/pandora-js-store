<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use App\Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
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

            // ── Branding ──
            ->brandName('仙女館 Admin')
            ->favicon('/favicon.svg')

            // ── Login ──
            ->login()
            ->loginRouteSlug('login')

            // ── Colors: brand gold-brown system ──
            ->colors([
                'primary' => Color::hex('#9F6B3E'),
                'danger' => Color::Red,
                'info' => Color::Sky,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'gray' => Color::Stone,
            ])

            // ── Dark mode off — light brand theme ──
            ->darkMode(false)

            // ── Navigation groups (Chinese) ──
            ->navigationGroups([
                '訂單管理',
                '商品管理',
                '行銷管理',
                '內容管理',
                '系統管理',
            ])

            // ── Typography ──
            ->font('Noto Sans TC')

            // ── Sidebar collapsible on desktop ──
            ->sidebarCollapsibleOnDesktop()

            // ── Max content width ──
            ->maxContentWidth('full')

            // ── Spa mode for faster nav (no full page reload) ──
            ->spa()

            // ── Global search ──
            ->globalSearch()
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])

            // ── Custom CSS for micro-interactions ──
            ->renderHook(
                \Filament\View\PanelsRenderHook::HEAD_END,
                fn () => new \Illuminate\Support\HtmlString(
                    '<link rel="stylesheet" href="/css/filament/custom.css?v=' . filemtime(public_path('css/filament/custom.css') ?: 0) . '">'
                ),
            )

            // ── Resources & Pages ──
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])

            // ── Widgets ──
            // No AccountWidget — the "Welcome Admin / Sign out" card is dead
            // space on a single-admin panel; sign-out lives in the user menu.
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([])

            // ── Middleware ──
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
