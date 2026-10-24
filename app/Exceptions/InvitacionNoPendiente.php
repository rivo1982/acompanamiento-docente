<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Se pidió reenviar o anular la invitación de una membresía que ya no está `invitada` (activa o suspendida).
 */
final class InvitacionNoPendiente extends RuntimeException
{
    public static function enEsteEstablecimiento(): self
    {
        return new self('Esta persona ya no tiene una invitación pendiente en este establecimiento.');
    }
}
