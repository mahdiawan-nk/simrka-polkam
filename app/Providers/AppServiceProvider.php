<?php

namespace App\Providers;

use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use BezhanSalleh\FilamentShield\Support\Utils;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(function (object $user, string $ability): ?bool {
            if (! Utils::isSuperAdminEnabled()) {
                return null;
            }

            if (! method_exists($user, 'hasRole') || ! $user->hasRole(Utils::getSuperAdminName())) {
                return null;
            }

            // Widget and Page permissions must be granted explicitly via Filament Shield even for super_admin.
            if ($this->isExplicitlyEnforcedShieldPermission($ability)) {
                return null;
            }

            return true;
        });
    }

    private function isExplicitlyEnforcedShieldPermission(string $ability): bool
    {
        try {
            $widgets = FilamentShield::getWidgets() ?? [];
            $pages = FilamentShield::getPages() ?? [];
        } catch (\Throwable) {
            return false;
        }

        foreach (array_merge($widgets, $pages) as $item) {
            $permissions = $item['permissions'] ?? [];
            if (array_key_exists($ability, $permissions)) {
                return true;
            }
        }

        return false;
    }
}
