<?php

namespace App\Support\Invitaciones;

/**
 * Resultado de invitar a un funcionario. Es el mismo tenga o no el correo una cuenta en otro establecimiento
 * (ADR-0005): solo el id de la membresía de ESTE establecimiento, nada sobre la cuenta.
 */
final readonly class InvitacionRegistrada
{
    public function __construct(public int $membresiaId) {}
}
