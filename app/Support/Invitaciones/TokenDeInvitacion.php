<?php

namespace App\Support\Invitaciones;

use Illuminate\Support\Carbon;

/**
 * Token de un enlace de invitación (Q18, ADR-0005). 32 bytes aleatorios en base64url (43 caracteres): sirve tal cual
 * en una URL. En la base solo se guarda su SHA-256 (`membresias.invitacion_hash`); el texto plano existe solo en
 * memoria mientras se arma el correo (EnviarInvitacion) y en el correo mismo.
 */
final class TokenDeInvitacion
{
    private const BYTES = 32;

    private const FORMATO = '/^[A-Za-z0-9_-]{43}$/';

    public static function generar(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(self::BYTES)), '+/', '-_'), '=');
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /** Descarta de inmediato lo que no puede ser un token, sin consultar la base. */
    public static function tieneFormato(string $token): bool
    {
        return preg_match(self::FORMATO, $token) === 1;
    }

    /**
     * Hash de un token que nadie conoce: deja la invitación vigente pero sin un enlace utilizable hasta que el
     * trabajo de envío rote el token. También invalida de inmediato el enlace anterior al reenviar.
     */
    public static function hashSinEnlace(): string
    {
        return self::hash(self::generar());
    }

    /** Vencimiento de un enlace emitido ahora (config/invitaciones.php). */
    public static function vencimientoDesdeAhora(): Carbon
    {
        return now()->addDays((int) config('invitaciones.vigencia_dias'));
    }
}
