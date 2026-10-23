<?php

namespace App\Providers;

use App\Policies\ReportPolicy;
use Illuminate\Support\Facades\DB;
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
        DB::prohibitDestructiveCommands(! $this->app->environment('testing'));

        Gate::define('viewSchoolReports', [ReportPolicy::class, 'viewSchoolReports']);
        Gate::define('viewTeacherReport', [ReportPolicy::class, 'viewTeacherReport']);
    }
}
