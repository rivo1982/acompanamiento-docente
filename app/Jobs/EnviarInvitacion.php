<?php

namespace App\Jobs;

use App\Exceptions\EnvioDeInvitacionFallido;
use App\Exceptions\InvitacionImposible;
use App\Exceptions\InvitacionNoEncolada;
use App\Mail\InvitacionAVinkured;
use App\Models\Membresia;
use App\Models\Usuario;
use App\Support\EstablecimientoActual;
use App\Support\Invitaciones\EnlaceDeInvitacion;
use App\Support\Invitaciones\TokenDeInvitacion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\FailOnException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Envía el correo de invitación de una membresía `invitada` (Q18). Mismo patrón que NotificarRespuestaAlApoderado
 * de Vinkured.
 *
 * - Lleva ids (establecimiento y membresía) y un sello: el SHA-256 del `invitacion_hash` que la invitación o el
 *   reenvío acababa de guardar (el hash de un token descartado, que nadie conoce). No hay correo, nombres, token ni
 *   el hash mismo en el payload.
 * - Convergencia (C2.1): bajo el bloqueo de la fila, el trabajo solo envía si el sello sigue coincidiendo con el hash
 *   guardado. Un reenvío posterior cambia el hash, así que un trabajo anterior que aún no corrió queda reemplazado y no
 *   envía; un trabajo repetido después de enviar (reentrega de la cola) tampoco, porque el envío ya rotó el hash.
 * - Rota el token y manda el correo DENTRO de la misma transacción, con la fila bloqueada hasta que el correo salió:
 *   un reenvío que llega mientras tanto espera el bloqueo y, después, corta ese enlace y encola su propio envío. Así
 *   el último enlace enviado es siempre el guardado. Si el correo falla, la transacción se revierte (el hash vuelve al
 *   sello) y el reintento lo envía. El costo: la fila queda bloqueada lo que tarde el transporte del correo.
 * - No envía si la invitación ya no está pendiente (aceptada, suspendida o anulada: sin vencimiento), así que una
 *   anulación previa al envío gana.
 * - Corre dentro de `EstablecimientoActual::paraTrabajo`: una membresía de otro establecimiento no existe para él.
 * - Sin registro: InvitacionImposible, falla sin reintentos. Si el correo falla: EnvioDeInvitacionFallido (sin
 *   encadenar el original, que suele traer la dirección), 3 intentos con espera.
 * - `failed()` registra solo ids y la clase de la excepción.
 */
class EnviarInvitacion implements ShouldQueueAfterCommit
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $establecimientoId,
        public readonly int $membresiaId,
        public readonly string $sello,
    ) {}

    /** Sello de la versión de una invitación: no permite recuperar el hash guardado. */
    public static function selloDe(string $invitacionHash): string
    {
        return hash('sha256', 'envio-de-invitacion:'.$invitacionHash);
    }

    /**
     * Encola el envío de la versión que la membresía tiene guardada. Llamar fuera de una transacción (o después de
     * confirmarla): con ShouldQueueAfterCommit, dentro de una transacción el encolado ocurre al confirmar.
     *
     * @throws InvitacionNoEncolada si la cola falla; la invitación ya está guardada y se puede reenviar
     */
    public static function encolar(int $establecimientoId, Membresia $membresia): void
    {
        $membresiaId = (int) $membresia->getKey();

        try {
            dispatch(new self($establecimientoId, $membresiaId, self::selloDe((string) $membresia->invitacion_hash)));
        } catch (Throwable $e) {
            Log::error('No se pudo encolar el envío de una invitación.', [
                'establecimiento_id' => $establecimientoId,
                'membresia_id' => $membresiaId,
                'excepcion' => $e::class,
            ]);

            throw new InvitacionNoEncolada($membresiaId);
        }
    }

    /** Segundos que se espera antes de cada reintento. */
    public function backoff(): array
    {
        return [60, 300];
    }

    /**
     * Lo que reintentar no arregla falla de inmediato, sin gastar los 3 intentos.
     *
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new FailOnException([InvitacionImposible::class])];
    }

    public function handle(EstablecimientoActual $actual, EnlaceDeInvitacion $enlaces): void
    {
        $actual->paraTrabajo($this->establecimientoId, fn () => $this->enviar($actual, $enlaces));
    }

    public function failed(Throwable $e): void
    {
        Log::error('El envío de una invitación falló definitivamente.', [
            'establecimiento_id' => $this->establecimientoId,
            'membresia_id' => $this->membresiaId,
            'excepcion' => $e::class,
        ]);
    }

    private function enviar(EstablecimientoActual $actual, EnlaceDeInvitacion $enlaces): void
    {
        DB::transaction(function () use ($actual, $enlaces): void {
            $membresia = Membresia::query()
                ->whereKey($this->membresiaId)
                ->where('establecimiento_id', $this->establecimientoId)
                ->lockForUpdate()
                ->first()
                ?? throw InvitacionImposible::sinRegistro();

            if (! $this->sigueVigente($membresia)) {
                return;
            }

            $correo = Usuario::query()->whereKey($membresia->usuario_id)->value('correo')
                ?? throw InvitacionImposible::sinRegistro();

            $token = TokenDeInvitacion::generar();
            $membresia->forceFill([
                'invitacion_hash' => TokenDeInvitacion::hash($token),
                'invitacion_expira_en' => TokenDeInvitacion::vencimientoDesdeAhora(),
            ])->save();

            $invitacion = new InvitacionAVinkured(
                establecimiento: (string) $actual->establecimiento()?->nombre,
                venceEl: $membresia->invitacion_expira_en->copy()->setTimezone((string) config('app.timezone'))->format('d-m-Y H:i'),
                enlace: $enlaces->para($token),
            );

            try {
                Mail::to($correo)->send($invitacion);
            } catch (Throwable $e) {
                // Sin encadenar $e: el mensaje del transporte suele traer la dirección.
                throw EnvioDeInvitacionFallido::tras($e::class);
            }
        });
    }

    /** Pendiente, no anulada, y sin un reenvío ni un envío posterior al que encoló este trabajo. */
    private function sigueVigente(Membresia $membresia): bool
    {
        return $membresia->estado === 'invitada'
            && $membresia->invitacion_expira_en !== null
            && $membresia->invitacion_hash !== null
            && hash_equals(self::selloDe($membresia->invitacion_hash), $this->sello);
    }
}
