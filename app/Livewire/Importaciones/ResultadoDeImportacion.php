<?php

namespace App\Livewire\Importaciones;

use App\Enums\EstadoImportacion;
use App\Models\ImportacionUsuario;
use App\Support\FormatoDelPanel;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Pantalla "Resultado de la carga masiva" (Q18; ruta `importaciones.show`). Clase nueva del módulo para entregar a
 * Vinkured. Mientras la carga está pendiente o procesando, la vista consulta cada 5 segundos (`wire:poll`); al
 * terminar deja de hacerlo y su única región de estado cambia de texto una sola vez, así que se anuncia una vez.
 * Si la carga no termina dentro de `importaciones.minutos_de_seguimiento` desde que se subió, la vista también deja
 * de consultar y la región de estado pasa a un Aviso (demora: recargar más tarde o contactar a soporte).
 *
 * Solo para el Administrador (gate `invitar-funcionarios`, revisada al abrir y en cada consulta). ImportacionUsuario
 * lleva el filtro por establecimiento activo: el id de una carga de otro establecimiento da 404. Los mensajes vienen
 * del dominio (ProcesarImportacion), que nunca menciona cuentas de otros establecimientos (ADR-0005).
 */
#[Layout('components.layouts.app', ['titulo' => 'Resultado de la carga masiva'])]
class ResultadoDeImportacion extends Component
{
    use WithPagination;

    public const ERRORES_POR_PAGINA = 20;

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
        $demorada = $this->demorada($importacion);
        $erroresDelArchivo = $errores->whereNull('fila')->pluck('mensajes')->flatten()->values()->all();
        $paginador = $this->paginarErrores($errores->whereNotNull('fila')->values()->all());

        return view('livewire.importaciones.resultado-de-importacion', [
            'importacion' => $importacion,
            'consultando' => ! $importacion->terminada() && ! $demorada,
            'estado' => CargaMasiva::ESTADOS[$importacion->estado->value],
            'variante' => CargaMasiva::VARIANTES[$importacion->estado->value],
            'situacion' => $this->situacion($importacion, $demorada),
            'comoAviso' => $demorada || $importacion->estado === EstadoImportacion::Fallida || $erroresDelArchivo !== [],
            'erroresDelArchivo' => $erroresDelArchivo,
            'paginador' => $paginador,
            'erroresPorFila' => $paginador->items(),
        ]);
    }

    private function importacion(): ImportacionUsuario
    {
        return ImportacionUsuario::query()->find($this->importacionId) ?? abort(404);
    }

    /** Sin terminar y pasado el límite de seguimiento (config/importaciones.php) desde que se subió. */
    private function demorada(ImportacionUsuario $importacion): bool
    {
        $minutos = max(1, (int) config('importaciones.minutos_de_seguimiento'));

        return ! $importacion->terminada() && now()->greaterThanOrEqualTo($importacion->created_at->copy()->addMinutes($minutos));
    }

    /** Texto de la región de estado: cambia una sola vez, al terminar la carga o al dejar de consultar. */
    private function situacion(ImportacionUsuario $importacion, bool $demorada): string
    {
        if ($demorada) {
            return 'La carga masiva está demorando más de lo esperado y esta página dejó de actualizarse. Recargar la página más tarde o, si sigue sin terminar, contactar a soporte de VINKURED.';
        }

        return match ($importacion->estado) {
            EstadoImportacion::Completada => 'Carga masiva terminada. Personas invitadas: '.FormatoDelPanel::entero($importacion->creados)
                .'. Filas con error: '.FormatoDelPanel::entero($importacion->con_error).'.',
            EstadoImportacion::Fallida => 'La carga masiva no se pudo completar. Revisar el archivo y volver a cargarlo.',
            default => 'Procesando la carga masiva. Esta página se actualiza sola.',
        };
    }

    /**
     * Pagina en memoria las filas con error (20 por página), con la página acotada a [1, última] como CargaMasiva.
     *
     * @param  list<array{fila: int, mensajes: list<string>}>  $filas
     * @return LengthAwarePaginator<int, array{fila: int, mensajes: list<string>}>
     */
    private function paginarErrores(array $filas): LengthAwarePaginator
    {
        $total = count($filas);
        $ultima = max(1, (int) ceil($total / self::ERRORES_POR_PAGINA));
        $solicitada = Paginator::resolveCurrentPage('page');
        $pagina = min(max(1, $solicitada), $ultima);

        if ($pagina !== $solicitada) {
            $this->setPage($pagina);
        }

        return new LengthAwarePaginator(
            array_slice($filas, ($pagina - 1) * self::ERRORES_POR_PAGINA, self::ERRORES_POR_PAGINA),
            $total,
            self::ERRORES_POR_PAGINA,
            $pagina,
            ['path' => Paginator::resolveCurrentPath(), 'pageName' => 'page'],
        );
    }
}
