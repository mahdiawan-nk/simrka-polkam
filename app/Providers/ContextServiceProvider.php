<?php

namespace App\Providers;

use App\Services\Context\ContextManager;
use App\Services\Context\Contracts\ContextManagerInterface;
use Illuminate\Support\ServiceProvider;

class ContextServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(ContextManagerInterface::class, ContextManager::class);
        $this->app->scoped('context.manager', ContextManager::class);
    }

    public function boot(): void
    {
        //
    }
}
