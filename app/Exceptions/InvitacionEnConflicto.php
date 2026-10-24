<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * La invitación choca con lo que YA hay en este establecimiento. Los mensajes solo hablan de este establecimiento:
 * nunca dicen si el correo tiene cuenta en otro (ADR-0005). Se pueden mostrar tal cual al Administrador: están en
 * infinitivo o impersonal, sin usted (regla del brief para la interfaz interna).
 */
final class InvitacionEnConflicto extends RuntimeException
{
    public static function yaTieneAcceso(): self
    {
        return new self('Esta persona ya tiene acceso activo en este establecimiento.');
    }

    public static function yaInvitada(): self
    {
        return new self('Esta persona ya tiene una invitación pendiente en este establecimiento. Se puede reenviar o anular desde las invitaciones pendientes.');
    }

    public static function suspendida(): self
    {
        return new self('Esta persona tiene su acceso suspendido en este establecimiento.');
    }

    public static function rutConOtroCorreo(): self
    {
        return new self('El RUT pertenece a una persona de este establecimiento que usa otro correo.');
    }

    public static function correoDeOtraPersona(): self
    {
        return new self('El correo ya está asociado a otra persona de este establecimiento.');
    }

    /**
     * Otra operación escribió la misma persona o cuenta al mismo tiempo (violación de unicidad). No dice dónde ni
     * quién: puede venir de otro establecimiento.
     */
    public static function simultanea(): self
    {
        return new self('Los datos de esta persona cambiaron en este establecimiento mientras se registraba la invitación. Volver a intentarlo.');
    }
}
