<?php

namespace App\Providers;

use App\Support\EstablecimientoActual;
use App\Support\RolesDelUsuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * Same scoped bindings as Vinkured's AppServiceProvider: one instance per request (or job),
     * so the state fixed by EstablecimientoActual::paraTrabajo() is shared by every resolution.
     */
    public function register(): void
    {
        $this->app->scoped(EstablecimientoActual::class);
        $this->app->scoped(RolesDelUsuario::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        DB::prohibitDestructiveCommands(! $this->app->environment('testing'));
    }
}
