<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * La invitación es para una cuenta que ya tiene contraseña establecida: no se cambia su contraseña; hay que aceptar
 * con la sesión iniciada en esa misma cuenta. Solo lo ve quien tiene el enlace, es decir, el dueño del correo.
 */
final class InvitacionRequiereSesion extends RuntimeException
{
    public static function paraCuentaExistente(): self
    {
        return new self('Esta invitación es para una cuenta que ya existe. Inicie sesión con esa cuenta para aceptarla.');
    }
}
