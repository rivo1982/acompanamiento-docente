<?php

namespace App\Livewire\Pautas;

use App\Enums\NivelLogro;
use App\Models\Indicador;
use App\Models\Pauta;
use App\Support\EstablecimientoActual;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Pantalla de una pauta (T5; ruta `pautas.show`), lista para imprimir o guardar como PDF con el diálogo de impresión
 * del navegador (decisión del usuario 2026-10-30: sin dependencia de PDF). Clase nueva del módulo para entregar a
 * Vinkured. Para cualquier funcionario del establecimiento activo (PautaPolicy::ver; el botón de imprimir, con
 * ::exportar; el de editar, con ::actualizar).
 *
 * Muestra la tabla de significados de los cuatro niveles (NivelLogro::tablaSignificados, en toda pauta) y cada
 * indicador con sus cuatro descriptores, agrupados por ámbito en el orden guardado (`posicion`, que sigue el catálogo).
 * La pauta se busca con el filtro del establecimiento activo: de otro establecimiento o eliminada, 404.
 */
class VerPauta extends Component
{
    #[Locked]
    public int $pautaId;

    public function mount(mixed $pauta): void
    {
        $this->pautaId = (int) $this->pauta($pauta)->getKey();
    }

    public function render(EstablecimientoActual $actual): View
    {
        $pauta = $this->pauta($this->pautaId);

        $indicadores = $pauta->indicadores()
            ->with(['ambito:id,pauta_base_id,nombre', 'ambito.pautaBase:id,nombre'])
            ->get();

        // `posicion` sigue el catálogo (pauta base → ámbito → número): los indicadores de un ámbito son contiguos.
        $ambitos = [];
        foreach ($indicadores as $indicador) {
            /** @var Indicador $indicador */
            $ultimo = array_key_last($ambitos);

            if ($ultimo === null || $ambitos[$ultimo]['id'] !== (int) $indicador->ambito_id) {
                $ambitos[] = [
                    'id' => (int) $indicador->ambito_id,
                    'nombre' => $indicador->ambito->nombre,
                    'pautaBase' => $indicador->ambito->pautaBase->nombre,
                    'indicadores' => [],
                ];
                $ultimo = array_key_last($ambitos);
            }

            $ambitos[$ultimo]['indicadores'][] = [
                'id' => (int) $indicador->id,
                'numero' => (int) $indicador->numero,
                'nombre' => $indicador->nombre,
                'niveles' => array_map(fn (NivelLogro $nivel): array => [
                    'etiqueta' => $nivel->label(),
                    'descriptor' => $indicador->descripcionNivel($nivel),
                ], NivelLogro::cases()),
            ];
        }

        return view('livewire.pautas.ver-pauta', [
            'pauta' => $pauta,
            'niveles' => NivelLogro::tablaSignificados(),
            'ambitos' => $ambitos,
            'totalIndicadores' => $indicadores->count(),
            'establecimiento' => $actual->establecimiento()?->nombre,
            'puedeEditar' => Gate::allows('actualizar', $pauta),
            'puedeExportar' => Gate::allows('exportar', $pauta),
        ])->layout('components.layouts.app', ['titulo' => $pauta->nombre]);
    }

    /** Pauta del establecimiento activo que el usuario puede ver, o 404 / 403. */
    private function pauta(mixed $id): Pauta
    {
        $id = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        abort_if($id === false, 404);

        $pauta = Pauta::query()->find($id) ?? abort(404);
        Gate::authorize('ver', $pauta);

        return $pauta;
    }
}
