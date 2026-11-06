<?php

namespace App\Livewire\Acompanamientos;

use App\Models\Acompanamiento;
use App\Models\Funcionario;
use App\Support\Acompanamientos\ResultadoDeAcompanamiento;
use App\Support\ApoyoPedagogico\Orientaciones;
use App\Support\EstablecimientoActual;
use App\Support\FormatoDelPanel;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Retroalimentación de un acompañamiento (T7; ruta `acompanamientos.show`), lista para imprimir o guardar como PDF
 * con el diálogo de impresión del navegador, como la pauta. Clase nueva del módulo para entregar a Vinkured. La abre el
 * enlace del aviso al docente (AcompanamientoFinalizado).
 *
 * AcompanamientoPolicy::ver al abrir y en cada render: su observador siempre, su docente solo una vez finalizado y el
 * administrador del módulo. Un acompañamiento de otro establecimiento da 404.
 *
 * Muestra un resumen por nivel, los indicadores agrupados por ResultadoDeAcompanamiento (nivel, descriptor,
 * retroalimentación automática y personal, ficha de apoyo pedagógico bajo Destacado y nota de protocolo) y las
 * orientaciones del anexo: ciclo sugerido, nota de uso y la plantilla "Plan de mejora acordado".
 */
class VerAcompanamiento extends Component
{
    #[Locked]
    public int $acompanamientoId;

    public function mount(mixed $acompanamiento): void
    {
        $modelo = $this->acompanamiento($acompanamiento);
        Gate::authorize('ver', $modelo);

        $this->acompanamientoId = (int) $modelo->getKey();
    }

    public function render(EstablecimientoActual $actual): View
    {
        $acompanamiento = $this->acompanamiento($this->acompanamientoId);
        Gate::authorize('ver', $acompanamiento);
        $acompanamiento->load(['docente.persona', 'observador.persona', 'curso.grado']);

        $orientaciones = Orientaciones::delAnexo();
        $resultado = ResultadoDeAcompanamiento::de($acompanamiento, $orientaciones);

        return view('livewire.acompanamientos.ver-acompanamiento', [
            'finalizado' => $acompanamiento->estaFinalizado(),
            'docente' => $this->nombreDe($acompanamiento->docente),
            'observador' => $this->nombreDe($acompanamiento->observador),
            'pauta' => (string) ($acompanamiento->pauta_snapshot['nombre'] ?? ''),
            'curso' => $acompanamiento->curso?->nombreCompleto() ?? 'Curso no disponible',
            'asignatura' => $acompanamiento->nombreAsignatura() ?? 'Asignatura no disponible',
            'fecha' => FormatoDelPanel::fechaYHora($acompanamiento->finalizado_en ?? $acompanamiento->iniciado_en),
            'secciones' => $resultado->secciones,
            'resumen' => $resultado->resumen,
            'orientaciones' => $orientaciones,
            'establecimiento' => $actual->establecimiento()?->nombre,
            'puedeContinuar' => Gate::allows('actualizar', $acompanamiento),
        ])->layout('components.layouts.app', ['titulo' => 'Retroalimentación del acompañamiento']);
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
}
