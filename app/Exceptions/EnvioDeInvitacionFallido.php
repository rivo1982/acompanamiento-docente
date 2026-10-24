<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * El correo de invitación no salió. SÍ se reintenta. Sustituye a la excepción del transporte sin encadenarla: su
 * mensaje suele traer la dirección y `failed_jobs` guarda mensaje y traza. Solo conserva el nombre de la clase
 * original. Mismo patrón que EnvioDeCorreoFallido de Vinkured.
 */
final class EnvioDeInvitacionFallido extends RuntimeException
{
    public static function tras(string $claseDeLaExcepcion): self
    {
        return new self("El correo de invitación no pudo enviarse ({$claseDeLaExcepcion}).");
    }
}
