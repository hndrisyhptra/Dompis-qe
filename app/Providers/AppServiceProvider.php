<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Listeners\UpdateQueueLastProcessedAt;
use App\Models\QeEvidence;
use App\Models\QeLop;
use App\Models\User;
use App\Policies\EvidencePolicy;
use App\Policies\QeLopPolicy;
use App\Policies\UserPolicy;
use App\Services\AccountScopeService;
use App\Services\MyApprovalService;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
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
        Gate::define('export-data-boqs', fn (User $user) => $user->hasRole(...UserRole::adminLevel()));
        Gate::define('view-incident-monitoring', fn (User $user) => $user->hasRole(...UserRole::adminLevel()));
        Gate::define('import-aiss', fn (User $user) => $user->isActive() && $user->hasRole(...UserRole::adminLevel()));
        Gate::define('view-my-approvals', fn (User $user) => $user->hasRole(UserRole::ADMIN) && $user->hasPermission('approve_evidence'));

        View::composer('layouts.app', function ($view): void {
            $reminder = null;
            $user = request()->user();
            $pendingCount = null;
            if ($user?->can('view-my-approvals')) {
                $pageStats = $view->getData()['stats'] ?? [];
                $pendingCount = request()->routeIs('lop.my-approvals') && isset($pageStats['pending_lops'])
                    ? (int) $pageStats['pending_lops']
                    : app(MyApprovalService::class)->pendingLopCount($user);
            }
            if (request()->hasSession() && request()->session()->has('approval_reminder_user_id')) {
                $expectedId = request()->session()->pull('approval_reminder_user_id');
                if ($user !== null && (string) $user->getKey() === (string) $expectedId && $user->can('view-my-approvals')) {
                    $reminder = app(MyApprovalService::class)->reminder($user);
                }
            }
            $view->with('loginApprovalReminder', $reminder);
            $view->with('myApprovalPendingCount', $pendingCount);
            $view->with('accountScopeLabel', $user ? app(AccountScopeService::class)->label($user) : 'Scope belum diatur');
        });

        /**
         * Master Designator (Designator, Paket KHS, KHS) - ketiga entity
         * berbagi ATURAN OTORISASI YANG PERSIS SAMA (tidak ada nuansa
         * per-record seperti QeLopPolicy), jadi dipakai satu Gate ability
         * ketimbang 3 Policy class yang isinya cuma copy-paste satu sama
         * lain.
         */
        Gate::define('manage-master-data', fn (User $user) => $user->hasPermission('manage_master_data'));

        // Menu Laporan (BOQ Actual / Sisa Material). Permission `reporting`
        // dipegang MANAGER + SUPER_ADMIN sejak seeder awal; ADMIN ditambahkan
        // lewat migration 2026_09_07_000004 (terscope branch di controller).
        Gate::define('view-reports', fn (User $user) => $user->hasPermission('reporting'));

        // Register queue monitoring listener
        Event::listen(JobProcessed::class, UpdateQueueLastProcessedAt::class);
    }
}
