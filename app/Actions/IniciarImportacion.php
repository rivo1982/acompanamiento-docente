<?php

namespace App\Actions;

use App\Enums\EstadoImportacion;
use App\Jobs\ProcesarImportacion;
use App\Models\ImportacionUsuario;
use App\Models\Membresia;
use App\Support\Invitaciones\AdministradorDelEstablecimiento;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Recibe la planilla de una carga masiva (Q18): la guarda con un nombre aleatorio `.xlsx` (LectorXlsx exige la
 * extensión; el nombre del cliente nunca forma parte de la ruta), registra la importación `pendiente` con solo el
 * nombre original del archivo y, confirmada, encola ProcesarImportacion con ids (si la cola falla, la carga queda
 * `fallida`). La planilla tiene datos personales:
 * ProcesarImportacion la borra al terminar, y aquí se borra si no se alcanzó a registrar.
 */
class IniciarImportacion
{
    private const LARGO_DEL_NOMBRE = 255;

    public function __construct(private readonly AdministradorDelEstablecimiento $administradores) {}

    /**
     * @param  UploadedFile|string  $archivo  la planilla subida, o la ruta de un archivo local (consola)
     *
     * @throws AuthorizationException si $administrador no es Administrador vigente del establecimiento activo
     */
    public function iniciar(UploadedFile|string $archivo, Membresia $administrador): ImportacionUsuario
    {
        $establecimientoId = $this->administradores->exigir($administrador);

        $archivoId = (string) Str::uuid();
        $disco = Storage::disk((string) config('importaciones.disco'));
        $ruta = ProcesarImportacion::rutaDe($archivoId);
        $fuente = $archivo instanceof UploadedFile ? $archivo : new File($archivo);

        if ($disco->putFileAs(dirname($ruta), $fuente, basename($ruta)) === false) {
            throw new RuntimeException('No se pudo guardar la planilla de la carga masiva.');
        }

        try {
            $importacion = DB::transaction(function () use ($archivo, $administrador): ImportacionUsuario {
                $importacion = new ImportacionUsuario;
                $importacion->forceFill([
                    'membresia_id' => $administrador->getKey(),
                    'archivo' => $this->nombreOriginal($archivo),
                    'estado' => EstadoImportacion::Pendiente,
                ])->save();

                return $importacion;
            });
        } catch (Throwable $e) {
            $disco->delete($ruta);

            throw $e;
        }

        $this->encolar($importacion, $establecimientoId, $archivoId);

        return $importacion;
    }

    /**
     * Si la cola falla, la carga queda `fallida` con un mensaje para el Administrador y la planilla se borra: no
     * queda una carga pendiente que nadie procesará.
     */
    private function encolar(ImportacionUsuario $importacion, int $establecimientoId, string $archivoId): void
    {
        try {
            ProcesarImportacion::dispatch($establecimientoId, (int) $importacion->getKey(), $archivoId);
        } catch (Throwable $e) {
            Log::error('No se pudo encolar una carga masiva de funcionarios.', [
                'establecimiento_id' => $establecimientoId,
                'importacion_id' => $importacion->getKey(),
                'excepcion' => $e::class,
            ]);

            $importacion->forceFill([
                'estado' => EstadoImportacion::Fallida,
                'errores' => [['fila' => null, 'mensajes' => ['No se pudo programar el proceso de la planilla. Volver a subirla.']]],
            ])->save();

            Storage::disk((string) config('importaciones.disco'))->delete(ProcesarImportacion::rutaDe($archivoId));
        }
    }

    /** Solo el nombre que traía el archivo, sin carpetas, recortado al largo de la columna. */
    private function nombreOriginal(UploadedFile|string $archivo): string
    {
        $nombre = $archivo instanceof UploadedFile ? $archivo->getClientOriginalName() : $archivo;
        $nombre = basename(str_replace('\\', '/', $nombre));

        return mb_substr($nombre !== '' ? $nombre : 'planilla.xlsx', 0, self::LARGO_DEL_NOMBRE);
    }
}
