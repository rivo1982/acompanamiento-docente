<?php

namespace App\Actions;

use App\Exceptions\InvitacionNoPendiente;
use App\Models\Membresia;
use App\Support\Invitaciones\AdministradorDelEstablecimiento;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Anula la invitación de una membresía `invitada` del establecimiento activo: borra hash y vencimiento, así que el
 * enlace deja de servir y un envío aún pendiente ya no sale. La membresía sigue `invitada` (se puede reenviar).
 * Idempotente: anular una invitación ya anulada no hace nada.
 */
class AnularInvitacion
{
    public function __construct(private readonly AdministradorDelEstablecimiento $administradores) {}

    /**
     * @throws AuthorizationException
     * @throws ModelNotFoundException si la membresía no es de este establecimiento
     * @throws InvitacionNoPendiente si la membresía ya no está `invitada`
     */
    public function anular(Membresia $administrador, int $membresiaId): void
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

            $membresia->forceFill(['invitacion_hash' => null, 'invitacion_expira_en' => null])->save();
        });
    }
}
