<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * La invitación choca con lo que YA hay en este establecimiento. Los mensajes solo hablan de este establecimiento:
 * nunca dicen si el correo tiene cuenta en otro (ADR-0005). Se pueden mostrar tal cual al Administrador.
 */
final class InvitacionEnConflicto extends RuntimeException
{
    public static function yaTieneAcceso(): self
    {
        return new self('Esta persona ya tiene acceso activo en este establecimiento.');
    }

    public static function yaInvitada(): self
    {
        return new self('Esta persona ya tiene una invitación en este establecimiento. Puede reenviarla o anularla.');
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
}
