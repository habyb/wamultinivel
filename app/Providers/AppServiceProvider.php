<?php

namespace App\Providers;

use App\Models\Role;
use App\Models\User;
use Illuminate\View\View;
use App\Models\Permission;
use App\Policies\UserPolicy;
use Barryvdh\Debugbar\Facades\Debugbar;
use Illuminate\Support\ServiceProvider;
use App\Observers\UserObserver;

class AppServiceProvider extends ServiceProvider
{
    protected $policies = [
        User::class => UserPolicy::class,
        // Role::class => RolePolicy::class,
        // Permission::class => PermissionPolicy::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        \Illuminate\Support\Facades\View::composer('*', function (View $view) {
            Debugbar::addMessage('View carregada: ' . $view->getPath(), 'views');
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        User::observe(UserObserver::class);
    }
}
