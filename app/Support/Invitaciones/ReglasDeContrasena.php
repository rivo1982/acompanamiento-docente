<?php

namespace App\Support\Invitaciones;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Contraseña de una cuenta nueva al aceptar una invitación: Password::defaults() (AppServiceProvider; Vinkured no
 * define una, así que el módulo propone mínimo 12 caracteres con letras y números). Mensajes en español, una sola
 * fuente para la acción y la pantalla (C4).
 */
final class ReglasDeContrasena
{
    public const CAMPO = 'contrasena';

    /** Largo máximo, como el login de Vinkured. */
    public const MAXIMO = 1024;

    /** @var array<string, string> */
    public const MENSAJES = [
        'contrasena.required' => 'La contraseña es obligatoria.',
        'contrasena.string' => 'La contraseña es obligatoria.',
        'contrasena.min' => 'La contraseña debe tener al menos :min caracteres.',
        'contrasena.max' => 'La contraseña no puede superar los :max caracteres.',
        'contrasena.password.letters' => 'La contraseña debe tener al menos una letra.',
        'contrasena.password.numbers' => 'La contraseña debe tener al menos un número.',
        'contrasena.password.mixed' => 'La contraseña debe tener mayúsculas y minúsculas.',
        'contrasena.password.symbols' => 'La contraseña debe tener al menos un símbolo.',
        'contrasena.password.uncompromised' => 'La contraseña apareció en una filtración de datos. Elija otra.',
    ];

    /**
     * @throws ValidationException
     */
    public static function validar(?string $contrasena): string
    {
        Validator::make(
            [self::CAMPO => $contrasena],
            [self::CAMPO => ['required', 'string', 'max:'.self::MAXIMO, Password::defaults()]],
            self::MENSAJES,
        )->validate();

        return (string) $contrasena;
    }
}
