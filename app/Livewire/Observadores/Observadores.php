<?php

namespace App\Livewire\Observadores;

use App\Actions\AsignarDocentesAObservador;
use App\Actions\HabilitarObservador;
use App\Actions\QuitarObservador;
use App\Exceptions\AsignacionAcompanamientoInvalida;
use App\Models\Funcionario;
use App\Models\Usuario;
use App\Support\ObservadoresDelEstablecimiento;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Pantalla "Observadores" (T6; ruta `observadores.index`). Clase nueva del módulo para entregar a Vinkured.
 * Una sola pantalla (decisión del usuario 2026-10-30): la lista de observadores del establecimiento activo y, al
 * elegir uno, los docentes que acompaña para marcarlos y guardarlos. Habilita a un funcionario vigente como observador
 * y quita a los habilitados; los automáticos (directivos) no se pueden quitar. Solo para el administrador del módulo
 * (gate `gestionar-observadores`, revisada al abrir y en cada acción; las acciones la revisan de nuevo).
 *
 * Todo se lee con ObservadoresDelEstablecimiento (modelos filtrados por el establecimiento activo): un id de otro
 * establecimiento no aparece y, si llega en una acción, da 404.
 */
#[Layout('components.layouts.app', ['titulo' => 'Observadores'])]
class Observadores extends Component
{
    public const EXITO = 'exito';

    public const AVISO = 'aviso';

    /** Funcionario del observador elegido. Solo lo cambia el servidor, tras comprobar que es observador de aquí. */
    #[Locked]
    public ?int $seleccionado = null;

    /** Ids (texto, como los envían las casillas) de los docentes marcados para el observador elegido. */
    public array $docentes = [];

    /** Id del funcionario elegido en "Habilitar observador". */
    public string $nuevoObservador = '';

    /** Si el modal de confirmación para quitar al observador elegido está abierto. */
    #[Locked]
    public bool $confirmandoQuita = false;

    #[Locked]
    public ?string $mensaje = null;

    #[Locked]
    public ?string $tipoDeMensaje = null;

    public function mount(): void
    {
        Gate::authorize('gestionar-observadores');
    }

    /**
     * Elige un observador y marca los docentes observables que hoy acompaña (los que ya no son observables no se
     * muestran ni se cuentan; al guardar salen).
     */
    public function seleccionar(mixed $id, ObservadoresDelEstablecimiento $lecturas): void
    {
        Gate::authorize('gestionar-observadores');
        $this->reset('mensaje', 'tipoDeMensaje', 'confirmandoQuita');

        $fila = $this->observadorDelEstablecimiento($id, $lecturas);
        $this->seleccionado = (int) $fila['funcionario']->getKey();
        $this->resetErrorBag();
        $this->marcarAsignados($lecturas);
        $this->js("setTimeout(() => document.getElementById('titulo-docentes')?.focus(), 0)");
    }

    public function guardar(AsignarDocentesAObservador $asignaciones, ObservadoresDelEstablecimiento $lecturas): void
    {
        Gate::authorize('gestionar-observadores');
        $this->reset('mensaje', 'tipoDeMensaje');

        if ($this->seleccionado === null) {
            return;
        }

        $fila = $this->observadorDelEstablecimiento($this->seleccionado, $lecturas);
        $nombre = $this->nombreDe($fila['funcionario']);
        $elegidos = array_values(array_filter(
            array_map(fn (mixed $id): int|false => filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]), $this->docentes),
        ));

        try {
            $asignaciones->sincronizar($this->usuario(), $this->seleccionado, $elegidos);
            $this->mostrarMensaje(self::EXITO, "Docentes de {$nombre} guardados.");
        } catch (AsignacionAcompanamientoInvalida) {
            $this->mostrarMensaje(self::AVISO, "No se guardó: algún docente marcado ya no se puede asignar a {$nombre}. Revisar la lista y volver a guardar.");
        }

        $this->marcarAsignados($lecturas);
    }

    public function habilitar(HabilitarObservador $habilitaciones): void
    {
        Gate::authorize('gestionar-observadores');
        $this->reset('mensaje', 'tipoDeMensaje', 'confirmandoQuita');
        $this->resetErrorBag();

        $id = filter_var($this->nuevoObservador, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($id === false) {
            $this->errorAlHabilitar('Elegir el funcionario que se va a habilitar.');

            return;
        }

        try {
            $observador = $habilitaciones->habilitar($this->usuario(), $id);
        } catch (ModelNotFoundException) {
            $this->errorAlHabilitar('El funcionario elegido ya no está vigente en este establecimiento. Elegir otro.');

            return;
        } catch (ValidationException $e) {
            $this->errorAlHabilitar((string) collect($e->errors())->flatten()->first());

            return;
        }

        $funcionario = Funcionario::query()->with('persona')->findOrFail($observador->funcionario_id);
        $this->nuevoObservador = '';
        $this->seleccionado = (int) $funcionario->getKey();
        $this->marcarAsignados(app(ObservadoresDelEstablecimiento::class));
        $this->mostrarMensaje(self::EXITO, "Se habilitó a {$this->nombreDe($funcionario)} como observador.");
    }

    /**
     * Abre el modal de confirmación. Solo para un observador elegido que no sea automático.
     */
    public function confirmarQuita(ObservadoresDelEstablecimiento $lecturas): void
    {
        Gate::authorize('gestionar-observadores');
        $this->reset('mensaje', 'tipoDeMensaje');

        if ($this->seleccionado === null) {
            return;
        }

        $this->confirmandoQuita = ! $this->observadorDelEstablecimiento($this->seleccionado, $lecturas)['automatico'];
    }

    public function cancelar(): void
    {
        $this->confirmandoQuita = false;
    }

    public function quitar(QuitarObservador $quitas, ObservadoresDelEstablecimiento $lecturas): void
    {
        Gate::authorize('gestionar-observadores');

        if (! $this->confirmandoQuita || $this->seleccionado === null) {
            return;
        }

        $fila = $this->observadorDelEstablecimiento($this->seleccionado, $lecturas);
        $nombre = $this->nombreDe($fila['funcionario']);
        $this->reset('mensaje', 'tipoDeMensaje', 'confirmandoQuita');

        try {
            $quitas->quitar($this->usuario(), $this->seleccionado);
        } catch (ValidationException $e) {
            $this->mostrarMensaje(self::AVISO, (string) collect($e->errors())->flatten()->first());

            return;
        }

        $this->reset('seleccionado', 'docentes');
        $this->mostrarMensaje(self::EXITO, "{$nombre} ya no es observador. Se quitaron también sus docentes asignados.");
    }

    public function render(ObservadoresDelEstablecimiento $lecturas): View
    {
        $observadores = $lecturas->observadores();
        (new Collection(array_column($observadores, 'funcionario')))->load('funcionesAsignadas.funcion:id,nombre');

        $filas = array_map(fn (array $fila): array => [
            'id' => (int) $fila['funcionario']->getKey(),
            'nombre' => $this->nombreDe($fila['funcionario']),
            'funcion' => $this->funcionesDe($fila['funcionario']),
            'automatico' => $fila['automatico'],
            'docentes' => $fila['docentes_asignados'],
        ], $observadores);

        $elegido = collect($filas)->firstWhere('id', $this->seleccionado);
        $idsDeObservadores = array_column($filas, 'id');

        return view('livewire.observadores.observadores', [
            'filas' => $filas,
            'hayHabilitados' => collect($filas)->contains('automatico', false),
            'elegido' => $elegido,
            'docentesObservables' => $elegido === null ? [] : $lecturas->docentesObservables()
                ->reject(fn (Funcionario $docente): bool => (int) $docente->getKey() === $elegido['id'])
                ->map(fn (Funcionario $docente): array => ['id' => (int) $docente->getKey(), 'nombre' => $this->nombreDe($docente)])
                ->values()->all(),
            'candidatos' => Funcionario::query()->vigentes()
                ->whereNotIn('id', $idsDeObservadores)
                ->with('persona')
                ->get()
                ->map(fn (Funcionario $funcionario): array => ['id' => (int) $funcionario->getKey(), 'nombre' => $this->nombreDe($funcionario)])
                ->sortBy(fn (array $candidato): string => mb_strtolower($candidato['nombre']))
                ->values()->all(),
        ]);
    }

    /**
     * Fila del observador (automático o habilitado, vigente) del establecimiento activo, o 404.
     *
     * @return array{funcionario: Funcionario, automatico: bool, docentes_asignados: int}
     */
    private function observadorDelEstablecimiento(mixed $id, ObservadoresDelEstablecimiento $lecturas): array
    {
        $id = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        abort_if($id === false, 404);

        foreach ($lecturas->observadores() as $fila) {
            if ((int) $fila['funcionario']->getKey() === $id) {
                return $fila;
            }
        }

        abort(404);
    }

    /** Marca los docentes observables que hoy acompaña el observador elegido. */
    private function marcarAsignados(ObservadoresDelEstablecimiento $lecturas): void
    {
        if ($this->seleccionado === null) {
            $this->docentes = [];

            return;
        }

        $observables = $lecturas->docentesObservables()->modelKeys();
        $this->docentes = array_map('strval', array_values(array_intersect(
            $lecturas->docentesAsignados($this->seleccionado),
            array_map('intval', $observables),
        )));
    }

    private function funcionesDe(Funcionario $funcionario): string
    {
        $funciones = $funcionario->funcionesAsignadas->pluck('funcion.nombre')->filter()->sort()->implode(', ');

        return $funciones !== '' ? $funciones : 'Sin función';
    }

    private function nombreDe(Funcionario $funcionario): string
    {
        return $funcionario->persona?->nombre_completo ?? 'Sin persona asociada';
    }

    private function usuario(): Usuario
    {
        $usuario = Auth::user();
        abort_unless($usuario instanceof Usuario, 403);

        return $usuario;
    }

    private function errorAlHabilitar(string $mensaje): void
    {
        $this->addError('nuevoObservador', $mensaje);
        $this->js("document.getElementById('nuevoObservador')?.focus()");
    }

    private function mostrarMensaje(string $tipo, string $mensaje): void
    {
        $this->tipoDeMensaje = $tipo;
        $this->mensaje = $mensaje;
        // Después de que el modal devuelva el foco a quien lo abrió (x-trap lo hace en un setTimeout 0).
        $this->js("setTimeout(() => document.getElementById('mensaje-observadores')?.focus(), 0)");
    }
}
