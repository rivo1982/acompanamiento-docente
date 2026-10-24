<?php

namespace App\Support\Invitaciones;

/**
 * URL que va en el correo de invitación. La ruta con nombre `invitaciones.aceptar` llega con la interfaz (C4);
 * hasta entonces se arma con url() en la misma dirección, y este es el único lugar que cambia a route().
 * Lleva el token en texto plano, nunca su hash.
 */
final class EnlaceDeInvitacion
{
    public function para(string $token): string
    {
        return url('/invitaciones/'.$token);
    }
}
