<?php

namespace App\Actions;

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
 * EnviarInvitacion, que genera el token nuevo justo antes de enviar. Si ya hay un envío pendiente de esa membresía,
 * el trabajo único no se duplica: ese envío pendiente rota el token y manda el enlace vigente.
 */
class ReenviarInvitacion
{
    public function __construct(private readonly AdministradorDelEstablecimiento $administradores) {}

    /**
     * @throws AuthorizationException
     * @throws ModelNotFoundException si la membresía no es de este establecimiento
     * @throws InvitacionNoPendiente si la membresía ya no está `invitada`
     */
    public function reenviar(Membresia $administrador, int $membresiaId): void
    {
        $establecimientoId = $this->administradores->exigir($administrador);

        DB::transaction(function () use ($establecimientoId, $membresiaId): void {
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
        });

        EnviarInvitacion::encolar($establecimientoId, $membresiaId);
    }
}
