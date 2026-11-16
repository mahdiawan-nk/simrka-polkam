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

            // Widget permissions must be granted explicitly via Filament Shield even for super_admin.
            if ($this->isShieldWidgetPermission($ability)) {
                return null;
            }

            return true;
        });
    }

    private function isShieldWidgetPermission(string $ability): bool
    {
        try {
            $widgets = FilamentShield::getWidgets() ?? [];
        } catch (\Throwable) {
            return false;
        }

        foreach ($widgets as $widget) {
            $permissions = $widget['permissions'] ?? [];
            if (array_key_exists($ability, $permissions)) {
                return true;
            }
        }

        return false;
    }
}
