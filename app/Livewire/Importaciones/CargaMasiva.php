<?php

namespace App\Livewire\Importaciones;

use App\Actions\IniciarImportacion;
use App\Enums\EstadoImportacion;
use App\Models\ImportacionUsuario;
use App\Support\EstablecimientoActual;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Pantalla "Carga masiva" (Q18; ruta `importaciones.index`). Clase nueva del módulo para entregar a Vinkured.
 * Sube la planilla .xlsx (IniciarImportacion la guarda y encola su proceso) y lista las cargas recientes del
 * establecimiento activo. Solo para el Administrador (gate `invitar-funcionarios`, revisada al abrir y al subir; la
 * acción además relee su membresía de la base).
 *
 * La copia temporal de Livewire (`livewire-tmp`) tiene datos personales: se borra apenas IniciarImportacion guardó la
 * suya, y también cuando el archivo no pasa la validación. Un archivo elegido y nunca subido queda allí hasta la
 * limpieza de Livewire (24 horas).
 */
#[Layout('components.layouts.app', ['titulo' => 'Carga masiva'])]
class CargaMasiva extends Component
{
    use WithFileUploads;
    use WithPagination;

    public const POR_PAGINA = 20;

    /** Texto de cada estado de la carga. */
    public const ESTADOS = [
        'pendiente' => 'Pendiente',
        'procesando' => 'Procesando',
        'completada' => 'Completada',
        'fallida' => 'Fallida',
    ];

    /** Valores aceptados en la columna Función, como se escriben en la planilla. */
    public const FUNCIONES = 'docente, directivo, asistente o profesional de apoyo';

    /** @var TemporaryUploadedFile|mixed|null */
    public $archivo = null;

    public function mount(): void
    {
        Gate::authorize('invitar-funcionarios');
    }

    public function subir(IniciarImportacion $importaciones, EstablecimientoActual $actual): void
    {
        Gate::authorize('invitar-funcionarios');
        $this->resetErrorBag();

        $validacion = Validator::make(['archivo' => $this->archivo], [
            'archivo' => ['bail', 'required', 'file', 'extensions:xlsx', 'max:'.self::tamanoMaximoKb(), 'mimes:xlsx'],
        ], [
            'archivo.required' => 'Elegir la planilla .xlsx que se va a subir.',
            'archivo.file' => 'El archivo no se pudo subir. Volver a elegirlo.',
            'archivo.extensions' => 'El archivo debe ser una planilla Excel (.xlsx).',
            'archivo.mimes' => 'El archivo debe ser una planilla Excel (.xlsx).',
            'archivo.max' => 'La planilla no puede superar los '.self::tamanoLegible().'.',
        ]);

        if ($validacion->fails()) {
            $this->descartarArchivo();
            $this->addError('archivo', $validacion->errors()->first('archivo'));
            $this->js("document.getElementById('archivo')?.focus()");

            return;
        }

        try {
            $importacion = $importaciones->iniciar($this->archivo, $actual->membresia() ?? abort(403));
        } finally {
            $this->descartarArchivo();
        }

        $this->redirectRoute('importaciones.show', ['importacion' => $importacion->id]);
    }

    public function render(): View
    {
        $paginador = $this->paginar(ImportacionUsuario::query()->latest()->latest('id'));

        return view('livewire.importaciones.carga-masiva', [
            'paginador' => $paginador,
            'cargas' => $paginador->getCollection(),
            'tamanoMaximo' => self::tamanoLegible(),
        ]);
    }

    /** "2 MB" a partir de config/importaciones.php. */
    public static function tamanoLegible(): string
    {
        $kb = self::tamanoMaximoKb();

        return $kb % 1024 === 0 ? ($kb / 1024).' MB' : $kb.' KB';
    }

    /** "2 de 3 invitadas" para una carga completada; vacío en las demás. */
    public static function resumen(ImportacionUsuario $carga): string
    {
        if ($carga->estado !== EstadoImportacion::Completada) {
            return '';
        }

        return number_format($carga->creados, 0, ',', '.').' de '.number_format($carga->total, 0, ',', '.').' invitadas';
    }

    private static function tamanoMaximoKb(): int
    {
        return max(1, (int) config('importaciones.tamano_maximo_kb'));
    }

    /**
     * Borra la copia temporal de Livewire y su archivo de metadatos (`<nombre>.json`, que guarda el nombre original y
     * que TemporaryUploadedFile::delete() no borra), y olvida el archivo.
     */
    private function descartarArchivo(): void
    {
        if ($this->archivo instanceof TemporaryUploadedFile) {
            $nombre = $this->archivo->getFilename();
            FileUploadConfiguration::storage()->delete([
                FileUploadConfiguration::path($nombre),
                FileUploadConfiguration::path($nombre.'.json'),
            ]);
        }

        $this->reset('archivo');
    }

    /**
     * Pagina con la página acotada a [1, última], como InvitacionesPendientes (y FiltraPorEstado::paginar de Vinkured).
     * ImportacionUsuario lleva el filtro por establecimiento activo.
     *
     * @param  Builder<ImportacionUsuario>  $consulta
     * @return LengthAwarePaginator<int, ImportacionUsuario>
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

        $filas = $total > 0
            ? (clone $consulta)->forPage($pagina, self::POR_PAGINA)->get(['id', 'archivo', 'estado', 'total', 'creados', 'created_at'])
            : new Collection;

        return new LengthAwarePaginator($filas, $total, self::POR_PAGINA, $pagina, [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => 'page',
        ]);
    }
}
