<?php

namespace App\Providers;

use App\Models\Acompanamiento;
use App\Models\AsignacionAcompanamiento;
use App\Models\Funcionario;
use App\Models\Indicador;
use App\Models\Pauta;
use App\Models\PautaBase;
use App\Models\Usuario;
use App\Policies\AcompanamientoPolicy;
use App\Policies\AsignacionAcompanamientoPolicy;
use App\Policies\IndicadorPolicy;
use App\Policies\PautaBasePolicy;
use App\Policies\PautaPolicy;
use App\Policies\ReportePolicy;
use App\Support\DiasHabiles;
use App\Support\EstablecimientoActual;
use App\Support\Importacion\LectorDePlanilla;
use App\Support\Importacion\LectorXlsx;
use App\Support\RolesDelModulo;
use App\Support\RolesDelUsuario;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        $this->app->scoped(RolesDelModulo::class);
        $this->app->scoped(DiasHabiles::class);

        $this->app->bind(LectorDePlanilla::class, LectorXlsx::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        DB::prohibitDestructiveCommands(! $this->app->environment('testing'));

        // Vinkured no define Password::defaults(): propuesta del módulo, mínimo 12 caracteres con letras y números.
        Password::defaults(fn (): Password => Password::min(12)->letters()->numbers());

        // Pantalla del enlace de invitación: 10 por minuto por enlace y por IP (frena a quien insiste con un mismo
        // enlace), más un techo holgado de 120 por minuto por IP (frena a quien prueba tokens distintos) que no bloquea
        // al personal de un colegio que sale a internet por una misma IP justo después de una carga masiva. El token
        // entra a la clave solo como SHA-256.
        RateLimiter::for('invitaciones', fn (Request $request): array => [
            Limit::perMinute(10)->by('enlace:'.$request->ip().':'.hash('sha256', (string) $request->route('token'))),
            Limit::perMinute(120)->by('ip:'.$request->ip()),
        ]);

        $this->registrarPermisos();
    }

    /**
     * Gates y políticas del módulo, como en Vinkured: solo se responde por el usuario autenticado en
     * su establecimiento activo (RolesDelModulo); para cualquier otro la respuesta es no.
     */
    private function registrarPermisos(): void
    {
        Gate::define('ver-reportes-establecimiento', fn (Usuario $usuario): bool => app(ReportePolicy::class)->verReportesEstablecimiento($usuario));
        Gate::define('ver-reporte-docente', fn (Usuario $usuario, Funcionario $docente): bool => app(ReportePolicy::class)->verReporteDocente($usuario, $docente));

        // Como `ver-panel-usuarios` de Vinkured: solo el Administrador del establecimiento activo.
        Gate::define('invitar-funcionarios', function (Usuario $usuario): bool {
            $roles = app(RolesDelUsuario::class);

            return $roles->esDelUsuario($usuario) && $roles->esAdministrador();
        });

        Gate::policy(Pauta::class, PautaPolicy::class);
        Gate::policy(PautaBase::class, PautaBasePolicy::class);
        Gate::policy(Indicador::class, IndicadorPolicy::class);
        Gate::policy(AsignacionAcompanamiento::class, AsignacionAcompanamientoPolicy::class);
        Gate::policy(Acompanamiento::class, AcompanamientoPolicy::class);
    }
}
