<?php

namespace App\Livewire\Acompanamientos;

use App\Actions\DescartarAcompanamiento;
use App\Enums\EstadoAcompanamiento;
use App\Models\Acompanamiento;
use App\Models\Funcionario;
use App\Models\Usuario;
use App\Support\RolesDelModulo;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Pantalla "Mis acompañamientos" (T7; ruta `acompanamientos.index`). Clase nueva del módulo para entregar a Vinkured.
 * Para cualquier funcionario del establecimiento activo: el observador ve los suyos separados en "En curso" (continuar
 * o descartar, con confirmación) y "Finalizados" (enlace al resultado). El administrador del módulo puede además ver
 * todos los del establecimiento (AcompanamientoPolicy::ver), sin acciones sobre los de otros observadores. Arriba,
 * "Mis resultados" lista al docente (o a quien ya fue observado) sus acompañamientos finalizados, los más recientes
 * primero y hasta POR_PAGINA; un docente que no observa ve además un estado vacío en lugar de las secciones del
 * observador.
 *
 * Todo se lee con modelos filtrados por el establecimiento activo: un id de otro establecimiento no aparece y, si
 * llega en una acción, da 404. Descartar lo revisa AcompanamientoPolicy::actualizar (aquí y en la acción).
 */
#[Layout('components.layouts.app', ['titulo' => 'Mis acompañamientos'])]
class Acompanamientos extends Component
{
    use WithPagination;

    public const POR_PAGINA = 20;

    public const ALCANCE_PROPIOS = 'mios';

    public const ALCANCE_ESTABLECIMIENTO = 'establecimiento';

    /** Qué acompañamientos listar. Solo el administrador del módulo puede elegir los del establecimiento. */
    #[Url]
    public string $alcance = self::ALCANCE_PROPIOS;

    /** Acompañamiento cuyo descarte se está confirmando. Solo lo fija el servidor, tras comprobar que se puede. */
    #[Locked]
    public ?int $descartando = null;

    #[Locked]
    public ?string $mensaje = null;

    public function mount(RolesDelModulo $roles): void
    {
        abort_unless($roles->esFuncionario($this->usuario()), 403);
    }

    public function updatedAlcance(): void
    {
        $this->resetPage();
    }

    public function confirmarDescarte(mixed $id): void
    {
        $this->reset('mensaje');
        $acompanamiento = $this->acompanamientoDelEstablecimiento($id);
        Gate::authorize('actualizar', $acompanamiento);

        $this->descartando = (int) $acompanamiento->getKey();
    }

    public function cancelar(): void
    {
        $this->descartando = null;
    }

    public function descartar(DescartarAcompanamiento $descartes): void
    {
        if ($this->descartando === null) {
            return;
        }

        $acompanamiento = $this->acompanamientoDelEstablecimiento($this->descartando);
        Gate::authorize('actualizar', $acompanamiento);
        $nombre = $this->nombreDe(Funcionario::query()->with('persona')->find($acompanamiento->docente_id));

        $descartes->descartar($this->usuario(), (int) $acompanamiento->getKey());

        $this->descartando = null;
        $this->mensaje = "Se descartó el acompañamiento de {$nombre}.";
        // Después de que el modal devuelva el foco a quien lo abrió (x-trap lo hace en un setTimeout 0).
        $this->js("setTimeout(() => document.getElementById('mensaje-acompanamientos')?.focus(), 0)");
    }

    public function render(RolesDelModulo $roles): View
    {
        $usuario = $this->usuario();
        $esObservador = $roles->esObservador($usuario);
        $esAdministrador = $roles->esAdministrador($usuario);
        $deTodos = $esAdministrador && $this->alcance === self::ALCANCE_ESTABLECIMIENTO;
        $propios = $roles->funcionarios();

        $consulta = fn (EstadoAcompanamiento $estado): Builder => Acompanamiento::query()
            ->where('estado', $estado->value)
            ->when(! $deTodos, fn (Builder $q) => $q->whereIn('observador_id', $propios))
            ->with(['docente.persona', 'observador.persona', 'curso.grado']);

        $enCurso = $consulta(EstadoAcompanamiento::EnCurso)->orderByDesc('iniciado_en')->orderByDesc('id')->get();
        $finalizados = $consulta(EstadoAcompanamiento::Finalizado)->orderByDesc('finalizado_en')->orderByDesc('id')
            ->paginate(self::POR_PAGINA);

        // Resultados propios: solo finalizados en que el usuario es el docente observado (AcompanamientoPolicy::ver).
        $consultaResultados = Acompanamiento::query()
            ->where('estado', EstadoAcompanamiento::Finalizado->value)
            ->whereIn('docente_id', $propios);
        $totalResultados = $consultaResultados->clone()->count();
        $resultados = $consultaResultados
            ->with(['docente.persona', 'observador.persona', 'curso.grado'])
            ->orderByDesc('finalizado_en')->orderByDesc('id')
            ->limit(self::POR_PAGINA)
            ->get();

        $asignaturas = DB::table('asignaturas')
            ->whereIn('id', $enCurso->pluck('asignatura_id')
                ->merge($finalizados->getCollection()->pluck('asignatura_id'))
                ->merge($resultados->pluck('asignatura_id'))
                ->unique()->all())
            ->pluck('nombre', 'id');

        $fila = fn (Acompanamiento $acompanamiento): array => [
            'id' => (int) $acompanamiento->getKey(),
            'docente' => $this->nombreDe($acompanamiento->docente),
            'observador' => $this->nombreDe($acompanamiento->observador),
            'pauta' => (string) ($acompanamiento->pauta_snapshot['nombre'] ?? ''),
            'curso' => $acompanamiento->curso?->nombreCompleto() ?? 'Curso no disponible',
            'asignatura' => $asignaturas[$acompanamiento->asignatura_id] ?? 'Asignatura no disponible',
            'fecha' => $acompanamiento->finalizado_en ?? $acompanamiento->iniciado_en,
            'finalizado' => $acompanamiento->estaFinalizado(),
            'esPropio' => in_array((int) $acompanamiento->observador_id, $propios, true),
        ];

        $filasEnCurso = $enCurso->map($fila)->all();

        /** @var LengthAwarePaginator<int, array<string, mixed>> $paginaFinalizados */
        $paginaFinalizados = $finalizados->through($fila);

        return view('livewire.acompanamientos.acompanamientos', [
            'mostrarResultados' => $roles->esDocente($usuario) || $totalResultados > 0,
            'resultados' => $resultados->map($fila)->all(),
            'totalResultados' => $totalResultados,
            'esObservador' => $esObservador,
            'esAdministrador' => $esAdministrador,
            'deTodos' => $deTodos,
            'enCurso' => $filasEnCurso,
            'finalizados' => $paginaFinalizados,
            'porDescartar' => $this->descartando === null ? null : collect($filasEnCurso)->firstWhere('id', $this->descartando),
        ]);
    }

    private function acompanamientoDelEstablecimiento(mixed $id): Acompanamiento
    {
        $id = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        abort_if($id === false, 404);

        return Acompanamiento::query()->find($id) ?? abort(404);
    }

    private function nombreDe(?Funcionario $funcionario): string
    {
        return $funcionario?->persona?->nombre_completo ?? 'Sin persona asociada';
    }

    private function usuario(): Usuario
    {
        $usuario = Auth::user();
        abort_unless($usuario instanceof Usuario, 403);

        return $usuario;
    }
}
