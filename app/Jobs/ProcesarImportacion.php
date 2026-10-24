<?php

namespace App\Jobs;

use App\Actions\InvitarFuncionario;
use App\Enums\EstadoImportacion;
use App\Exceptions\InvitacionEnConflicto;
use App\Exceptions\InvitacionNoEncolada;
use App\Exceptions\PlanillaIlegible;
use App\Models\ImportacionUsuario;
use App\Models\Membresia;
use App\Support\EstablecimientoActual;
use App\Support\Importacion\FilaDePlanilla;
use App\Support\Importacion\LectorDePlanilla;
use App\Support\Importacion\ValidadorDePlanilla;
use App\Support\Invitaciones\AdministradorDelEstablecimiento;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Procesa una carga masiva de funcionarios (Q18). Clase nueva del módulo, pensada para entregarse a Vinkured.
 *
 * - Lleva solo ids: establecimiento, importación y el uuid del archivo guardado (opaco, sin datos personales).
 * - Corre dentro de `EstablecimientoActual::paraTrabajo`: una importación de otro establecimiento no existe para él.
 * - Toma la importación pasando de `pendiente` a `procesando` en una sola sentencia condicional: si ya no está
 *   pendiente (terminada, o tomada por otro trabajo), no hace nada. Volver a correrlo es inocuo.
 * - Errores del archivo (encabezado, vacía, ilegible) o un Administrador que ya no lo es: `fallida` con
 *   [{fila: null, mensajes}]. Si no, invita cada fila válida con InvitarFuncionario, cada una en su propia
 *   transacción (una fila mala no revierte las demás), y deja `completada` con los contadores y los errores por fila.
 *   Los mensajes son los de este establecimiento: una cuenta en otro colegio se cuenta como creada, igual que una
 *   nueva (ADR-0005). Una persona con invitación pendiente aquí se informa, no se reenvía.
 * - Una excepción inesperada en una fila deja un error genérico, se registra solo con ids y el proceso sigue.
 * - SIEMPRE borra la planilla al terminar (minimización de datos personales), con éxito o no; también `failed()`.
 * - Un solo intento: reintentar a medias volvería a invitar filas ya procesadas. Si el trabajo muere, `failed()` deja
 *   la carga `fallida` y el Administrador la vuelve a subir (las filas ya invitadas aparecerán como pendientes).
 */
class ProcesarImportacion implements ShouldBeUnique, ShouldQueueAfterCommit
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    /** Segundos que dura la unicidad si el trabajo muere sin liberarla. */
    public int $uniqueFor = 3600;

    public function __construct(
        public readonly int $establecimientoId,
        public readonly int $importacionId,
        public readonly string $archivoId,
    ) {}

    /** Ruta de la planilla guardada, dentro del disco de config/importaciones.php. */
    public static function rutaDe(string $archivoId): string
    {
        if (! Str::isUuid($archivoId)) {
            throw new InvalidArgumentException('El archivo de una carga masiva se identifica con un uuid.');
        }

        return config('importaciones.directorio').'/'.$archivoId.'.xlsx';
    }

    /** Un solo trabajo pendiente por importación (y establecimiento). */
    public function uniqueId(): string
    {
        return $this->establecimientoId.':'.$this->importacionId;
    }

    public function handle(
        EstablecimientoActual $actual,
        LectorDePlanilla $lector,
        ValidadorDePlanilla $validador,
        InvitarFuncionario $invitaciones,
        AdministradorDelEstablecimiento $administradores,
    ): void {
        $actual->paraTrabajo($this->establecimientoId, function () use ($lector, $validador, $invitaciones, $administradores): void {
            $importacion = $this->tomar();

            if ($importacion === null) {
                return;
            }

            try {
                $this->procesar($importacion, $lector, $validador, $invitaciones, $administradores);
            } finally {
                $this->borrarPlanilla();
            }
        });
    }

    public function failed(Throwable $e): void
    {
        Log::error('Una carga masiva de funcionarios falló definitivamente.', [
            'establecimiento_id' => $this->establecimientoId,
            'importacion_id' => $this->importacionId,
            'excepcion' => $e::class,
        ]);

        app(EstablecimientoActual::class)->paraTrabajo($this->establecimientoId, function (): void {
            ImportacionUsuario::query()
                ->whereKey($this->importacionId)
                ->whereIn('estado', [EstadoImportacion::Pendiente->value, EstadoImportacion::Procesando->value])
                ->update([
                    'estado' => EstadoImportacion::Fallida->value,
                    'errores' => json_encode([['fila' => null, 'mensajes' => ['No se pudo terminar de procesar la planilla. Súbala nuevamente.']]]),
                    'updated_at' => now(),
                ]);
        });

        $this->borrarPlanilla();
    }

    /** Pasa la importación de `pendiente` a `procesando` de forma atómica; null si no estaba pendiente. */
    private function tomar(): ?ImportacionUsuario
    {
        $tomadas = ImportacionUsuario::query()
            ->whereKey($this->importacionId)
            ->where('estado', EstadoImportacion::Pendiente->value)
            ->update(['estado' => EstadoImportacion::Procesando->value, 'updated_at' => now()]);

        return $tomadas === 1 ? ImportacionUsuario::query()->find($this->importacionId) : null;
    }

    private function procesar(
        ImportacionUsuario $importacion,
        LectorDePlanilla $lector,
        ValidadorDePlanilla $validador,
        InvitarFuncionario $invitaciones,
        AdministradorDelEstablecimiento $administradores,
    ): void {
        $administrador = Membresia::query()->find($importacion->membresia_id) ?? new Membresia;

        try {
            $administradores->exigir($administrador);
        } catch (AuthorizationException) {
            $this->fallar($importacion, self::mensajeSinAdministrador());

            return;
        }

        try {
            $resultado = $validador->validar($lector->filas(Storage::disk((string) config('importaciones.disco'))->path(self::rutaDe($this->archivoId))));
        } catch (PlanillaIlegible $e) {
            $this->fallar($importacion, $e->getMessage());

            return;
        }

        if ($resultado->erroresDelArchivo !== []) {
            $this->fallar($importacion, ...$resultado->erroresDelArchivo);

            return;
        }

        $erroresPorFila = $resultado->erroresPorFila;
        $creados = 0;

        foreach ($resultado->filasValidas as $fila) {
            $error = $this->invitar($importacion, $invitaciones, $administrador, $fila);

            if ($error === null) {
                $creados++;
            } else {
                $erroresPorFila[$fila->numeroDeFila] = [$error];
            }
        }

        ksort($erroresPorFila);

        $importacion->forceFill([
            'estado' => EstadoImportacion::Completada,
            'total' => count($resultado->filasValidas) + count($resultado->erroresPorFila),
            'creados' => $creados,
            'con_error' => count($erroresPorFila),
            'errores' => array_map(
                fn (int $numero, array $mensajes): array => ['fila' => $numero, 'mensajes' => $mensajes],
                array_keys($erroresPorFila),
                array_values($erroresPorFila),
            ),
        ])->save();
    }

    /** @return string|null el mensaje de error de la fila, o null si quedó invitada */
    private function invitar(ImportacionUsuario $importacion, InvitarFuncionario $invitaciones, Membresia $administrador, FilaDePlanilla $fila): ?string
    {
        try {
            $invitaciones->invitar($administrador, $fila);

            return null;
        } catch (InvitacionEnConflicto|InvitacionNoEncolada $e) {
            return $e->getMessage();
        } catch (AuthorizationException) {
            return self::mensajeSinAdministrador();
        } catch (Throwable $e) {
            // Solo ids: el mensaje de la base o del transporte puede traer el correo o el RUT.
            Log::error('Una fila de una carga masiva falló de forma inesperada.', [
                'establecimiento_id' => $this->establecimientoId,
                'importacion_id' => $importacion->getKey(),
                'fila' => $fila->numeroDeFila,
                'excepcion' => $e::class,
            ]);

            return 'No se pudo procesar esta fila. Inténtelo nuevamente más tarde.';
        }
    }

    private function fallar(ImportacionUsuario $importacion, string ...$mensajes): void
    {
        $importacion->forceFill([
            'estado' => EstadoImportacion::Fallida,
            'errores' => [['fila' => null, 'mensajes' => array_values($mensajes)]],
        ])->save();
    }

    private static function mensajeSinAdministrador(): string
    {
        return 'Quien subió la planilla ya no es Administrador de este establecimiento.';
    }

    private function borrarPlanilla(): void
    {
        Storage::disk((string) config('importaciones.disco'))->delete(self::rutaDe($this->archivoId));
    }
}
