<?php

namespace App\Livewire\Acompanamientos;

use App\Actions\FinalizarAcompanamiento;
use App\Actions\GuardarRespuesta;
use App\Enums\NivelLogro;
use App\Exceptions\AcompanamientoYaFinalizado;
use App\Models\Acompanamiento;
use App\Models\Funcionario;
use App\Models\RespuestaAcompanamiento;
use App\Models\Usuario;
use App\Support\FormatoDelPanel;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Formulario de observación de un acompañamiento en curso (T7; ruta `acompanamientos.edit`). Clase nueva del módulo
 * para entregar a Vinkured. Solo su observador mientras sigue en curso (AcompanamientoPolicy::responder al abrir y
 * en cada acción; las acciones lo revisan de nuevo). Uno finalizado lleva a su resultado (a quien puede verlo).
 *
 * Muestra los indicadores del snapshot de la pauta, agrupados por ámbito en su orden. Cada nivel se guarda apenas se
 * elige (GuardarRespuesta) y la retroalimentación personal al salir del campo; escrita antes del nivel, se guarda
 * con él. Finalizar pide confirmación y lo hace FinalizarAcompanamiento (exige todos los niveles y avisa al docente).
 * Un acompañamiento de otro establecimiento da 404.
 */
class ObservarAcompanamiento extends Component
{
    #[Locked]
    public int $acompanamientoId;

    /** @var array<int, string> Nivel elegido por id de indicador. */
    public array $niveles = [];

    /** @var array<int, string> Retroalimentación personal por id de indicador. */
    public array $retroalimentaciones = [];

    /** Si el modal de confirmación para finalizar está abierto. */
    #[Locked]
    public bool $confirmando = false;

    /** Último estado de guardado, anunciado en una región aria-live. */
    #[Locked]
    public ?string $estadoDeGuardado = null;

    public function mount(mixed $acompanamiento): void
    {
        $modelo = $this->acompanamiento($acompanamiento);
        $this->acompanamientoId = (int) $modelo->getKey();

        if ($modelo->estaFinalizado()) {
            Gate::authorize('ver', $modelo);
            $this->redirectRoute('acompanamientos.show', ['acompanamiento' => $this->acompanamientoId]);

            return;
        }

        Gate::authorize('responder', $modelo);

        foreach (RespuestaAcompanamiento::query()->where('acompanamiento_id', $this->acompanamientoId)->get() as $respuesta) {
            $this->niveles[(int) $respuesta->indicador_id] = $respuesta->nivel->value;
            $this->retroalimentaciones[(int) $respuesta->indicador_id] = (string) $respuesta->retroalimentacion_personal;
        }
    }

    public function updatedNiveles(mixed $valor, mixed $clave): void
    {
        $indicadorId = (int) $clave;

        if (! $this->sonTextos($indicadorId)) {
            return;
        }

        $this->guardar($indicadorId, "Nivel del indicador {$this->numeroDe($indicadorId)} guardado.");
    }

    public function updatedRetroalimentaciones(mixed $valor, mixed $clave): void
    {
        $indicadorId = (int) $clave;

        if (! $this->sonTextos($indicadorId)) {
            return;
        }

        if (($this->niveles[$indicadorId] ?? '') === '') {
            $this->estadoDeGuardado = "La retroalimentación del indicador {$this->numeroDe($indicadorId)} se guarda al elegir su nivel.";

            return;
        }

        $this->guardar($indicadorId, "Retroalimentación del indicador {$this->numeroDe($indicadorId)} guardada.");
    }

    /**
     * Abre el modal de finalizar. Si se finalizó entretanto (otra pestaña), lleva a su resultado; una negación por
     * otro motivo se mantiene.
     */
    public function confirmarFinalizacion(): void
    {
        $acompanamiento = $this->acompanamiento($this->acompanamientoId);

        try {
            Gate::authorize('finalizar', $acompanamiento);
        } catch (AuthorizationException $e) {
            if (! $acompanamiento->estaFinalizado()) {
                throw $e;
            }

            $this->redirectRoute('acompanamientos.show', ['acompanamiento' => $this->acompanamientoId]);

            return;
        }

        $this->resetErrorBag('respuestas');

        $this->confirmando = true;
    }

    public function cancelar(): void
    {
        $this->confirmando = false;
    }

    public function finalizar(FinalizarAcompanamiento $finalizaciones): void
    {
        if (! $this->confirmando) {
            return;
        }

        $this->confirmando = false;
        $acompanamiento = $this->acompanamiento($this->acompanamientoId);

        try {
            $finalizaciones->finalizar($this->usuario(), $this->acompanamientoId);
        } catch (ValidationException $e) {
            $this->addError('respuestas', (string) collect($e->errors())->flatten()->first());
            // Después de que el modal devuelva el foco a quien lo abrió (x-trap lo hace en un setTimeout 0).
            $this->js("setTimeout(() => document.getElementById('error-respuestas')?.focus(), 0)");

            return;
        } catch (AcompanamientoYaFinalizado) {
            $this->redirectRoute('acompanamientos.show', ['acompanamiento' => $this->acompanamientoId]);

            return;
        }

        $docente = $this->nombreDe(Funcionario::query()->with('persona')->find($acompanamiento->docente_id));
        session()->flash('estado', "Se finalizó el acompañamiento de {$docente}. El resultado ya está disponible para el docente.");
        $this->redirectRoute('acompanamientos.index');
    }

    public function render(): View
    {
        $acompanamiento = $this->acompanamiento($this->acompanamientoId);
        $acompanamiento->load(['docente.persona', 'curso.grado']);
        $indicadores = $acompanamiento->pauta_snapshot['indicadores'] ?? [];

        // El snapshot guarda los indicadores en el orden de la pauta: los de un mismo ámbito son contiguos.
        $ambitos = [];
        foreach ($indicadores as $indicador) {
            $nombre = $indicador['ambito'] ?? null;
            $nombre = $nombre === null || $nombre === '' ? 'Indicadores sin ámbito' : $nombre;
            $ultimo = array_key_last($ambitos);

            if ($ultimo === null || $ambitos[$ultimo]['nombre'] !== $nombre) {
                $ambitos[] = ['nombre' => $nombre, 'indicadores' => []];
                $ultimo = array_key_last($ambitos);
            }

            $ambitos[$ultimo]['indicadores'][] = [
                'id' => (int) $indicador['id'],
                'numero' => (int) $indicador['numero'],
                'nombre' => (string) $indicador['nombre'],
                'niveles' => array_map(fn (NivelLogro $nivel): array => [
                    'valor' => $nivel->value,
                    'etiqueta' => $nivel->label(),
                    'descriptor' => (string) ($indicador['niveles'][$nivel->value] ?? ''),
                ], NivelLogro::cases()),
            ];
        }

        $docente = $this->nombreDe($acompanamiento->docente);

        return view('livewire.acompanamientos.observar-acompanamiento', [
            'docente' => $docente,
            'pauta' => (string) ($acompanamiento->pauta_snapshot['nombre'] ?? ''),
            'curso' => $acompanamiento->curso?->nombreCompleto() ?? 'Curso no disponible',
            'asignatura' => $acompanamiento->nombreAsignatura() ?? 'Asignatura no disponible',
            'iniciadoEn' => FormatoDelPanel::fechaYHora($acompanamiento->iniciado_en),
            'significados' => NivelLogro::tablaSignificados(),
            'ambitos' => $ambitos,
            'total' => count($indicadores),
            'conNivel' => RespuestaAcompanamiento::query()->where('acompanamiento_id', $this->acompanamientoId)->count(),
            'maximo' => GuardarRespuesta::RETROALIMENTACION_MAXIMA,
        ])->layout('components.layouts.app', ['titulo' => "Acompañamiento de {$docente}"]);
    }

    /**
     * Guarda el nivel y la retroalimentación del indicador. Un error se muestra junto al campo y el nivel vuelve al
     * último guardado. Si se finalizó entretanto (otra pestaña), lleva a su resultado.
     */
    private function guardar(int $indicadorId, string $exito): void
    {
        $this->resetErrorBag(["niveles.{$indicadorId}", "retroalimentaciones.{$indicadorId}", 'respuestas']);
        $numero = $this->numeroDe($indicadorId);

        try {
            app(GuardarRespuesta::class)->guardar(
                $this->usuario(),
                $this->acompanamientoId,
                $indicadorId,
                (string) ($this->niveles[$indicadorId] ?? ''),
                $this->retroalimentaciones[$indicadorId] ?? null,
            );
        } catch (ValidationException $e) {
            foreach ($e->errors() as $campo => $errores) {
                $clave = $campo === 'retroalimentacion_personal' ? 'retroalimentaciones' : 'niveles';
                $this->addError("{$clave}.{$indicadorId}", (string) ($errores[0] ?? ''));
            }

            $guardado = RespuestaAcompanamiento::query()
                ->where('acompanamiento_id', $this->acompanamientoId)
                ->where('indicador_id', $indicadorId)
                ->first();
            $this->niveles[$indicadorId] = $guardado?->nivel->value ?? '';
            $this->estadoDeGuardado = "No se guardó el indicador {$numero}.";

            return;
        } catch (AcompanamientoYaFinalizado|AuthorizationException $e) {
            if (! $this->acompanamiento($this->acompanamientoId)->estaFinalizado()) {
                throw $e;
            }

            $this->redirectRoute('acompanamientos.show', ['acompanamiento' => $this->acompanamientoId]);

            return;
        }

        $this->estadoDeGuardado = $exito;
    }

    /**
     * Niveles y retroalimentaciones llegan del navegador: si el del indicador no es texto, vuelven al último guardado y
     * no se guarda nada.
     */
    private function sonTextos(int $indicadorId): bool
    {
        $nivel = $this->niveles[$indicadorId] ?? '';
        $retroalimentacion = $this->retroalimentaciones[$indicadorId] ?? '';

        if (is_string($nivel) && is_string($retroalimentacion)) {
            return true;
        }

        $guardado = RespuestaAcompanamiento::query()
            ->where('acompanamiento_id', $this->acompanamientoId)
            ->where('indicador_id', $indicadorId)
            ->first();
        $this->niveles[$indicadorId] = $guardado?->nivel->value ?? '';
        $this->retroalimentaciones[$indicadorId] = (string) $guardado?->retroalimentacion_personal;
        $this->estadoDeGuardado = "No se guardó el indicador {$this->numeroDe($indicadorId)}.";

        return false;
    }

    private function numeroDe(int $indicadorId): string
    {
        $indicador = $this->acompanamiento($this->acompanamientoId)->indicadorDelSnapshot($indicadorId);

        return $indicador === null ? (string) $indicadorId : (string) $indicador['numero'];
    }

    /** Acompañamiento del establecimiento activo, o 404. */
    private function acompanamiento(mixed $id): Acompanamiento
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
