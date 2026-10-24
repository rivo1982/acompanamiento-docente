<?php

namespace App\Jobs;

use App\Exceptions\EnvioDeInvitacionFallido;
use App\Exceptions\InvitacionImposible;
use App\Mail\InvitacionAVinkured;
use App\Models\Membresia;
use App\Models\Usuario;
use App\Support\EstablecimientoActual;
use App\Support\Invitaciones\EnlaceDeInvitacion;
use App\Support\Invitaciones\TokenDeInvitacion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
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
 * - Lleva únicamente ids (establecimiento y membresía): ni correo, ni nombres, ni token en el payload.
 * - Rota el token JUSTO ANTES de enviar: genera uno nuevo, guarda solo su SHA-256 y renueva el vencimiento, bajo
 *   bloqueo de la fila; el texto plano vive solo en memoria y en el correo. Cada intento (o reenvío) deja inservible
 *   el enlace anterior. Si el correo falla tras rotar, el reintento vuelve a rotar: nadie quedó con un enlace útil.
 * - No envía si la invitación ya no está pendiente (aceptada, suspendida o anulada: sin vencimiento), así que una
 *   anulación previa al envío gana.
 * - Corre dentro de `EstablecimientoActual::paraTrabajo`: una membresía de otro establecimiento no existe para él.
 * - Único por membresía (ShouldBeUnique). Sin registro: InvitacionImposible, falla sin reintentos. Si el correo falla:
 *   EnvioDeInvitacionFallido (sin encadenar el original, que suele traer la dirección), 3 intentos con espera.
 * - `failed()` registra solo ids y la clase de la excepción.
 */
class EnviarInvitacion implements ShouldBeUnique, ShouldQueueAfterCommit
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    /** Segundos que dura la unicidad si el trabajo muere sin liberarla. */
    public int $uniqueFor = 900;

    public function __construct(
        public readonly int $establecimientoId,
        public readonly int $membresiaId,
    ) {}

    /**
     * Encola el envío. Si la cola falla, la invitación ya está guardada: se registra (solo ids) y se puede reenviar.
     */
    public static function encolar(int $establecimientoId, int $membresiaId): void
    {
        try {
            dispatch(new self($establecimientoId, $membresiaId));
        } catch (Throwable $e) {
            Log::error('No se pudo encolar el envío de una invitación.', [
                'establecimiento_id' => $establecimientoId,
                'membresia_id' => $membresiaId,
                'excepcion' => $e::class,
            ]);
        }
    }

    /** Segundos que se espera antes de cada reintento. */
    public function backoff(): array
    {
        return [60, 300];
    }

    /** Un solo trabajo pendiente por membresía (y establecimiento). */
    public function uniqueId(): string
    {
        return $this->establecimientoId.':'.$this->membresiaId;
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
        $rotada = $this->rotarToken();

        if ($rotada === null) {
            return;
        }

        [$token, $membresia] = $rotada;

        $correo = Usuario::query()->whereKey($membresia->usuario_id)->value('correo')
            ?? throw InvitacionImposible::sinRegistro();

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
    }

    /**
     * @return array{0: string, 1: Membresia}|null token en texto plano y la membresía ya rotada, o null si la
     *                                             invitación ya no está pendiente
     */
    private function rotarToken(): ?array
    {
        return DB::transaction(function (): ?array {
            $membresia = Membresia::query()
                ->whereKey($this->membresiaId)
                ->where('establecimiento_id', $this->establecimientoId)
                ->lockForUpdate()
                ->first()
                ?? throw InvitacionImposible::sinRegistro();

            if ($membresia->estado !== 'invitada' || $membresia->invitacion_expira_en === null) {
                return null;
            }

            $token = TokenDeInvitacion::generar();
            $membresia->forceFill([
                'invitacion_hash' => TokenDeInvitacion::hash($token),
                'invitacion_expira_en' => TokenDeInvitacion::vencimientoDesdeAhora(),
            ])->save();

            return [$token, $membresia];
        });
    }
}
