<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * La invitación (o su reenvío) quedó guardada, pero el envío de su correo no se pudo encolar. Ningún enlace útil salió:
 * el Administrador debe reenviarla. Lleva solo el id de la membresía de este establecimiento, para que quien llama
 * (la pantalla o la carga masiva) lo registre; no encadena la excepción de la cola.
 */
final class InvitacionNoEncolada extends RuntimeException
{
    public function __construct(public readonly int $membresiaId)
    {
        parent::__construct('La invitación quedó registrada, pero su correo no pudo programarse. Reenviarla más tarde desde las invitaciones pendientes.');
    }
}
