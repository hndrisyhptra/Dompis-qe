<?php

namespace App\Providers;

use App\Models\QeEvidence;
use App\Models\QeLop;
use App\Models\User;
use App\Policies\EvidencePolicy;
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
        Gate::policy(QeEvidence::class, EvidencePolicy::class);

        /**
         * Master Designator (Designator, Paket KHS, KHS) - ketiga entity
         * berbagi ATURAN OTORISASI YANG PERSIS SAMA (tidak ada nuansa
         * per-record seperti QeLopPolicy), jadi dipakai satu Gate ability
         * ketimbang 3 Policy class yang isinya cuma copy-paste satu sama
         * lain.
         */
        Gate::define('manage-master-data', fn (User $user) => $user->hasPermission('manage_master_data'));
    }
}
