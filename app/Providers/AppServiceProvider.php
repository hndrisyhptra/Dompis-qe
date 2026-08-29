<?php

namespace App\Providers;

use App\Models\QeLop;
use App\Models\User;
use App\Policies\QeLopPolicy;
use App\Policies\UserPolicy;
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
        Gate::policy(QeLop::class, QeLopPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
    }
}
