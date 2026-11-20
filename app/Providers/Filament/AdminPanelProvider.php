<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Services\Branding\BrandSettingsService;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Enums\UserMenuPosition;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackupPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->maxContentWidth(Width::Full)
            // ->topNavigation()
            ->spa()
            ->login(Login::class)
            ->breadcrumbs(false)
            ->sidebarWidth('20rem')
            // ->sidebarCollapsibleOnDesktop()
            ->globalSearch(false)
            ->brandName(fn (): string => app(BrandSettingsService::class)->getBrowserTitle())
            ->brandLogo(fn () => view('filament.logo'))
            ->favicon(fn (): ?string => app(BrandSettingsService::class)->getFaviconUrl())
            ->colors([
                'primary' => Color::hex('#17324D'),
                'secondary' => Color::hex('#245B85'),
                'accent' => Color::hex('#168C83'),
                'highlight' => Color::hex('#C69A45'),
                'success' => Color::hex('#15803D'),
                'warning' => Color::hex('#B7791F'),
                'danger' => Color::hex('#BE123C'),
                'info' => Color::hex('#2563EB'),
                'gray' => Color::Slate,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                // AccountWidget::class,
                // FilamentInfoWidget::class,
            ])
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn (): string => Blade::render('@livewire(\'context-switcher\')')
            )
            ->userMenu(position: UserMenuPosition::Topbar)
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
            ->plugins([
                FilamentShieldPlugin::make()
                    ->navigationGroup('Pengaturan Sistem')
                    ->navigationLabel('Peran & Izin'),
                // FilamentSpatieLaravelBackupPlugin::make()
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
