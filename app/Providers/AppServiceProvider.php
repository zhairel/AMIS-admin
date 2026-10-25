<?php

namespace App\Providers;

use App\Listeners\LogSentEmail;
use App\Models\User;
use App\Policies\AcademicModulePolicy;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
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
        Gate::define('manage-academic', [AcademicModulePolicy::class, 'manage']);
        Gate::define('manage-microsoft-rosters', fn (User $user) => $user->hasRole(['super_admin', 'admin']) || $user->hasPermission('microsoft_roster_management')
        );

        Event::listen(MessageSent::class, LogSentEmail::class);

        \Illuminate\Support\Facades\View::composer(['partials.sidebar', 'layouts.admin', 'admin.*'], function ($view) {
            $service = app(\App\Services\SchoolYearService::class);
            $view->with([
                'currentSchoolYear' => $service->current(),
                'availableSchoolYears' => $service->available(),
            ]);
        });
    }
}
