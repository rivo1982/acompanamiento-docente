<?php

namespace App\Livewire\Invitaciones;

use App\Actions\AnularInvitacion;
use App\Actions\ReenviarInvitacion;
use App\Exceptions\InvitacionNoEncolada;
use App\Exceptions\InvitacionNoPendiente;
use App\Models\Funcionario;
use App\Models\Membresia;
use App\Support\DiasHabiles;
use App\Support\EstablecimientoActual;
use App\Support\EstadoDePlazo;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Pantalla "Invitaciones pendientes" (Q18; ruta `invitaciones.index`). Clase nueva del módulo para entregar a Vinkured.
 * Lista las membresías `invitada` del establecimiento activo, también las anuladas (sin hash ni vencimiento), con
 * reenviar y anular. Solo para el Administrador (gate `invitar-funcionarios`, revisada al abrir y en cada acción; las
 * acciones además releen su membresía de la base).
 *
 * Orden e insignias como el bloque "Usuarios por gestionar" de Vinkured (Panel\Usuarios): primero las vencidas, luego
 * por vencimiento; las anuladas (sin vencimiento) al final. `Membresia` no lleva filtro por establecimiento: se aplica
 * aquí a mano en la lista y en cada acción (un id de otro establecimiento da 404). Nunca se seleccionan ni muestran el
 * hash de la invitación, la contraseña ni el token de sesión.
 */
#[Layout('components.layouts.app', ['titulo' => 'Invitaciones pendientes'])]
class InvitacionesPendientes extends Component
{
    use WithPagination;

    public const POR_PAGINA = 20;

    public const EXITO = 'exito';

    public const AVISO = 'aviso';

    /** Id de la invitación cuya anulación se está confirmando (modal abierto). Solo la cambia el servidor. */
    #[Locked]
    public ?int $anulando = null;

    /** Mensaje de página tras la última acción. */
    #[Locked]
    public ?string $mensaje = null;

    #[Locked]
    public ?string $tipoDeMensaje = null;

    public function mount(): void
    {
        Gate::authorize('invitar-funcionarios');
    }

    public function reenviar(mixed $id, ReenviarInvitacion $reenvios, EstablecimientoActual $actual): void
    {
        Gate::authorize('invitar-funcionarios');
        $this->reset('mensaje', 'tipoDeMensaje', 'anulando');

        $invitacion = $this->invitacionDelEstablecimiento($id, $actual);
        $nombre = $this->nombreDe($invitacion);

        try {
            $reenvios->reenviar($actual->membresia() ?? abort(403), $invitacion->id);
            $this->mostrarMensaje(self::EXITO, "Invitación reenviada a {$nombre}.");
        } catch (InvitacionNoPendiente $e) {
            $this->mostrarMensaje(self::AVISO, $e->getMessage());
        } catch (InvitacionNoEncolada) {
            $this->mostrarMensaje(self::AVISO, "El enlace anterior de {$nombre} quedó sin efecto, pero el correo nuevo no se pudo programar. Reenviar la invitación en unos minutos.");
        }
    }

    /**
     * Abre el modal de confirmación. Solo para una invitación de este establecimiento con un enlace vivo.
     */
    public function confirmarAnulacion(mixed $id, EstablecimientoActual $actual): void
    {
        Gate::authorize('invitar-funcionarios');
        $this->reset('mensaje', 'tipoDeMensaje');

        $invitacion = $this->invitacionDelEstablecimiento($id, $actual);

        if ($invitacion->estado !== 'invitada' || $invitacion->invitacion_expira_en === null) {
            $this->anulando = null;
            $this->mostrarMensaje(self::AVISO, 'Esta invitación ya no tiene un enlace que anular.');

            return;
        }

        $this->anulando = $invitacion->id;
    }

    public function cancelar(): void
    {
        $this->anulando = null;
    }

    public function anular(AnularInvitacion $anulaciones, EstablecimientoActual $actual): void
    {
        Gate::authorize('invitar-funcionarios');

        if ($this->anulando === null) {
            return;
        }

        $invitacion = $this->invitacionDelEstablecimiento($this->anulando, $actual);
        $nombre = $this->nombreDe($invitacion);
        $this->reset('mensaje', 'tipoDeMensaje', 'anulando');

        try {
            $anulaciones->anular($actual->membresia() ?? abort(403), $invitacion->id);
            $this->mostrarMensaje(self::EXITO, "Invitación de {$nombre} anulada. Su enlace ya no sirve.");
        } catch (InvitacionNoPendiente $e) {
            $this->mostrarMensaje(self::AVISO, $e->getMessage());
        }
    }

    public function render(EstablecimientoActual $actual, DiasHabiles $dias): View
    {
        $establecimientoId = $actual->id() ?? abort(403);
        $paginador = $this->paginar($this->consulta($establecimientoId));
        $invitaciones = $paginador->getCollection();
        $funciones = $this->funcionesPorPersona($invitaciones);

        return view('livewire.invitaciones.invitaciones-pendientes', [
            'paginador' => $paginador,
            'filas' => $invitaciones->map(fn (Membresia $invitacion): array => [
                'id' => $invitacion->id,
                'nombre' => $this->nombreDe($invitacion),
                'correo' => $invitacion->usuario->correo,
                'funcion' => $funciones[$invitacion->persona_id] ?? 'Sin función',
                'invitada' => $invitacion->invitada_en,
                'anulable' => $invitacion->invitacion_expira_en !== null,
                'situacion' => $this->situacion($invitacion, $dias),
            ])->all(),
            'nombreAnulando' => $this->anulando === null ? null : $this->nombreDe(
                $this->consulta($establecimientoId)->whereKey($this->anulando)->first() ?? new Membresia,
            ),
        ]);
    }

    /**
     * Membresías `invitada` del establecimiento: vencidas primero (misma regla que EstadoDePlazo: `ahora >
     * vencimiento`), luego las vigentes por vencimiento y al final las anuladas.
     *
     * @return Builder<Membresia>
     */
    private function consulta(int $establecimientoId): Builder
    {
        return Membresia::query()
            ->select(['membresias.id', 'membresias.usuario_id', 'membresias.persona_id', 'membresias.estado', 'membresias.invitada_en', 'membresias.invitacion_expira_en'])
            ->where('membresias.establecimiento_id', $establecimientoId)
            ->where('membresias.estado', 'invitada')
            ->with(['usuario:id,correo', 'persona'])
            ->orderByRaw(
                'CASE WHEN membresias.invitacion_expira_en IS NULL THEN 2 WHEN membresias.invitacion_expira_en < ? THEN 0 ELSE 1 END',
                [now()->toDateTimeString()],
            )
            ->orderBy('membresias.invitacion_expira_en')
            ->orderBy('membresias.id');
    }

    /**
     * Pagina con la página ACOTADA a [1, última] antes de leer las filas, como FiltraPorEstado::paginar() de Vinkured.
     *
     * @param  Builder<Membresia>  $consulta
     * @return LengthAwarePaginator<int, Membresia>
     */
    private function paginar(Builder $consulta): LengthAwarePaginator
    {
        $total = (clone $consulta)->toBase()->getCountForPagination();
        $ultima = max(1, (int) ceil($total / self::POR_PAGINA));
        $solicitada = Paginator::resolveCurrentPage('page');
        $pagina = min(max(1, $solicitada), $ultima);

        if ($pagina !== $solicitada) {
            $this->setPage($pagina);
        }

        $filas = $total > 0 ? (clone $consulta)->forPage($pagina, self::POR_PAGINA)->get() : new Collection;

        return new LengthAwarePaginator($filas, $total, self::POR_PAGINA, $pagina, [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => 'page',
        ]);
    }

    /**
     * Nombres de las funciones de cada persona de la página, en una consulta (Funcionario lleva el filtro por
     * establecimiento).
     *
     * @param  Collection<int, Membresia>  $invitaciones
     * @return array<int, string> persona_id => "Docente, Directivo"
     */
    private function funcionesPorPersona(Collection $invitaciones): array
    {
        $personas = $invitaciones->pluck('persona_id')->filter()->unique()->values();

        if ($personas->isEmpty()) {
            return [];
        }

        return Funcionario::query()
            ->whereIn('persona_id', $personas)
            ->with('funcionesAsignadas.funcion:id,nombre')
            ->get(['id', 'persona_id'])
            ->mapWithKeys(fn (Funcionario $funcionario): array => [
                $funcionario->persona_id => $funcionario->funcionesAsignadas->pluck('funcion.nombre')->filter()->sort()->implode(', '),
            ])
            ->filter()
            ->all();
    }

    /**
     * Texto y variante de la insignia, con el mapeo de Vinkured (Panel\Usuarios::situacion) más "Anulada" (Neutro,
     * decisión del usuario 2026-10-24) para la invitación sin enlace. Siempre con texto; nunca solo color.
     *
     * @return array{texto: string, variante: string}
     */
    private function situacion(Membresia $invitacion, DiasHabiles $dias): array
    {
        if ($invitacion->invitacion_expira_en === null) {
            return ['texto' => 'Anulada', 'variante' => 'neutro'];
        }

        try {
            $plazo = EstadoDePlazo::calcular($invitacion->invitacion_expira_en, null, $dias);
        } catch (InvalidArgumentException) {
            return ['texto' => 'Vencimiento no disponible', 'variante' => 'neutro'];
        }

        return match (true) {
            $plazo->esVencido() => ['texto' => $plazo->dias === 0 ? 'Vencida hoy' : 'Vencida', 'variante' => 'peligro'],
            $plazo->tipo === EstadoDePlazo::VENCE_HOY => ['texto' => 'Vence hoy', 'variante' => 'aviso'],
            default => ['texto' => $plazo->etiqueta(), 'variante' => 'neutro'],
        };
    }

    /**
     * Invitación (cualquier estado) del establecimiento activo, o 404: un id de otro establecimiento o mal formado no
     * revela nada.
     */
    private function invitacionDelEstablecimiento(mixed $id, EstablecimientoActual $actual): Membresia
    {
        $id = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        abort_if($id === false, 404);

        return Membresia::query()
            ->whereKey($id)
            ->where('establecimiento_id', $actual->id() ?? abort(403))
            ->with('persona')
            ->first() ?? abort(404);
    }

    private function nombreDe(Membresia $invitacion): string
    {
        // La persona no resuelve si no es del establecimiento activo: se degrada, no se falla (como Panel\Usuarios).
        return $invitacion->persona?->nombre_completo ?? 'Sin persona asociada';
    }

    private function mostrarMensaje(string $tipo, string $mensaje): void
    {
        $this->tipoDeMensaje = $tipo;
        $this->mensaje = $mensaje;
        // Después de que el modal devuelva el foco a quien lo abrió (x-trap lo hace en un setTimeout 0).
        $this->js("setTimeout(() => document.getElementById('mensaje-invitaciones')?.focus(), 0)");
    }
}
