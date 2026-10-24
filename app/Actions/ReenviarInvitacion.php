<?php

namespace App\Actions;

use App\Exceptions\InvitacionNoEncolada;
use App\Exceptions\InvitacionNoPendiente;
use App\Jobs\EnviarInvitacion;
use App\Models\Membresia;
use App\Support\Invitaciones\AdministradorDelEstablecimiento;
use App\Support\Invitaciones\TokenDeInvitacion;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Reenvía la invitación de una membresía `invitada` del establecimiento activo (también una anulada). En una
 * transacción con la fila bloqueada deja inservible el enlace anterior y renueva el vencimiento; confirmada, encola
 * EnviarInvitacion con el sello del hash nuevo, que genera el token justo antes de enviar. Un envío anterior aún
 * pendiente queda reemplazado (su sello ya no coincide) y no envía; uno en curso tiene la fila bloqueada, así que el
 * reenvío lo espera y su enlace queda cortado: el último correo enviado lleva siempre el enlace vigente.
 */
class ReenviarInvitacion
{
    public function __construct(private readonly AdministradorDelEstablecimiento $administradores) {}

    /**
     * @throws AuthorizationException
     * @throws ModelNotFoundException si la membresía no es de este establecimiento
     * @throws InvitacionNoPendiente si la membresía ya no está `invitada`
     * @throws InvitacionNoEncolada si el envío no se pudo encolar (el enlace anterior ya quedó cortado)
     */
    public function reenviar(Membresia $administrador, int $membresiaId): void
    {
        $establecimientoId = $this->administradores->exigir($administrador);

        $membresia = DB::transaction(function () use ($establecimientoId, $membresiaId): Membresia {
            $membresia = Membresia::query()
                ->whereKey($membresiaId)
                ->where('establecimiento_id', $establecimientoId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($membresia->estado !== 'invitada') {
                throw InvitacionNoPendiente::enEsteEstablecimiento();
            }

            $membresia->forceFill([
                'invitacion_hash' => TokenDeInvitacion::hashSinEnlace(),
                'invitacion_expira_en' => TokenDeInvitacion::vencimientoDesdeAhora(),
            ])->save();

            return $membresia;
        });

        EnviarInvitacion::encolar($establecimientoId, $membresia);
    }
}
