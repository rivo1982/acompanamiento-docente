<?php

namespace App\Actions;

use App\Exceptions\InvitacionInvalida;
use App\Exceptions\InvitacionRequiereSesion;
use App\Models\Membresia;
use App\Models\Usuario;
use App\Support\Invitaciones\ReglasDeContrasena;
use App\Support\Invitaciones\TokenDeInvitacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Acepta una invitación desde el enlace del correo (Q18, ADR-0005).
 *
 * En UNA transacción con la membresía bloqueada (FOR UPDATE): la busca por el SHA-256 del token, exige que siga
 * `invitada` y vigente, y la pasa a `activa` borrando hash y vencimiento, así que el enlace sirve una sola vez; de
 * dos aceptaciones simultáneas, la segunda ya no encuentra el hash. Todo enlace inservible (desconocido, vencido,
 * anulado, reemplazado o usado) da el mismo InvitacionInvalida, sin detalle.
 *
 * Si la cuenta ya tiene una credencial establecida (otra membresía activa, o es de soporte de VINKURED), su
 * contraseña NO se toca: hay que aceptar con la sesión iniciada en esa misma cuenta. Si no, se fija la contraseña
 * pedida, que debe cumplir ReglasDeContrasena; si no la cumple, no se consume el enlace.
 */
class AceptarInvitacion
{
    /**
     * @throws InvitacionInvalida
     * @throws InvitacionRequiereSesion
     * @throws ValidationException si la contraseña de una cuenta nueva no cumple las reglas
     */
    public function aceptar(string $token, ?string $contrasena, ?Usuario $autenticado): Membresia
    {
        if (! TokenDeInvitacion::tieneFormato($token)) {
            throw InvitacionInvalida::generica();
        }

        return DB::transaction(function () use ($token, $contrasena, $autenticado): Membresia {
            $membresia = Membresia::query()
                ->where('invitacion_hash', TokenDeInvitacion::hash($token))
                ->lockForUpdate()
                ->first();

            if ($membresia === null
                || $membresia->estado !== 'invitada'
                || $membresia->invitacion_expira_en === null
                || $membresia->invitacion_expira_en->isPast()) {
                throw InvitacionInvalida::generica();
            }

            $usuario = Usuario::query()->whereKey($membresia->usuario_id)->lockForUpdate()->firstOrFail();

            if ($this->tieneCredencialEstablecida($usuario, $membresia)) {
                if ($autenticado === null || (int) $autenticado->getKey() !== (int) $usuario->getKey()) {
                    throw InvitacionRequiereSesion::paraCuentaExistente();
                }
            } else {
                $usuario->forceFill([
                    'password' => ReglasDeContrasena::validar($contrasena),
                    'remember_token' => Str::random(60),
                    // Abrir el enlace del correo prueba que la dirección es suya.
                    'email_verified_at' => $usuario->email_verified_at ?? now(),
                ])->save();
            }

            $membresia->forceFill([
                'estado' => Membresia::ESTADO_ACTIVA,
                'activada_en' => now(),
                'invitacion_hash' => null,
                'invitacion_expira_en' => null,
            ])->save();

            return $membresia;
        });
    }

    private function tieneCredencialEstablecida(Usuario $usuario, Membresia $invitacion): bool
    {
        return $usuario->es_soporte_vinkured
            || $usuario->membresias()
                ->whereKeyNot($invitacion->getKey())
                ->where('estado', Membresia::ESTADO_ACTIVA)
                ->exists();
    }
}
