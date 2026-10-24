<?php

namespace App\Support\Invitaciones;

/**
 * URL que va en el correo de invitación: la ruta `invitaciones.aceptar` (pantalla App\Livewire\Invitaciones\
 * AceptarInvitacion). Lleva el token en texto plano, nunca su hash.
 */
final class EnlaceDeInvitacion
{
    public function para(string $token): string
    {
        return route('invitaciones.aceptar', $token);
    }
}
