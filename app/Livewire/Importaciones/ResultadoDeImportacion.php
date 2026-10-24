<?php

namespace App\Livewire\Importaciones;

use App\Enums\EstadoImportacion;
use App\Models\ImportacionUsuario;
use App\Support\FormatoDelPanel;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Pantalla "Resultado de la importación" (Q18; ruta `importaciones.show`). Clase nueva del módulo para entregar a
 * Vinkured. Mientras la carga está pendiente o procesando, la vista consulta cada 5 segundos (`wire:poll`); al
 * terminar deja de hacerlo y su única región de estado cambia de texto una sola vez, así que se anuncia una vez.
 *
 * Solo para el Administrador (gate `invitar-funcionarios`, revisada al abrir y en cada consulta). ImportacionUsuario
 * lleva el filtro por establecimiento activo: el id de una carga de otro establecimiento da 404. Los mensajes vienen
 * del dominio (ProcesarImportacion), que nunca menciona cuentas de otros establecimientos (ADR-0005).
 */
#[Layout('components.layouts.app', ['titulo' => 'Resultado de la importación'])]
class ResultadoDeImportacion extends Component
{
    #[Locked]
    public int $importacionId;

    public function mount(mixed $importacion): void
    {
        Gate::authorize('invitar-funcionarios');

        $id = filter_var($importacion, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        abort_if($id === false, 404);

        $this->importacionId = $id;
        $this->importacion();
    }

    public function render(): View
    {
        Gate::authorize('invitar-funcionarios');

        $importacion = $this->importacion();
        $errores = collect($importacion->errores ?? []);

        return view('livewire.importaciones.resultado-de-importacion', [
            'importacion' => $importacion,
            'terminada' => $importacion->terminada(),
            'estado' => CargaMasiva::ESTADOS[$importacion->estado->value],
            'situacion' => $this->situacion($importacion),
            'erroresDelArchivo' => $errores->whereNull('fila')->pluck('mensajes')->flatten()->values()->all(),
            'erroresPorFila' => $errores->whereNotNull('fila')->values()->all(),
        ]);
    }

    private function importacion(): ImportacionUsuario
    {
        return ImportacionUsuario::query()->find($this->importacionId) ?? abort(404);
    }

    /** Texto de la región de estado: cambia una sola vez, al terminar la carga. */
    private function situacion(ImportacionUsuario $importacion): string
    {
        return match ($importacion->estado) {
            EstadoImportacion::Completada => 'Importación terminada. Personas invitadas: '.FormatoDelPanel::entero($importacion->creados)
                .'. Filas con error: '.FormatoDelPanel::entero($importacion->con_error).'.',
            EstadoImportacion::Fallida => 'La importación no se pudo completar.',
            default => 'Procesando la planilla. Esta página se actualiza sola.',
        };
    }
}
