<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Correo de texto plano con el enlace para aceptar una invitación a VINKURED (Q18). Español de Chile, tratamiento de
 * "usted". Es el mismo tenga o no la persona una cuenta en otro establecimiento (ADR-0005): al abrir el enlace se le
 * pide crear su contraseña o, si ya tiene cuenta, iniciar sesión.
 *
 * Solo recibe cadenas ya armadas; el enlace lleva el token en texto plano, nunca su hash. No se encola por sí mismo:
 * lo envía el trabajo EnviarInvitacion.
 */
class InvitacionAVinkured extends Mailable
{
    public function __construct(
        public readonly string $establecimiento,
        public readonly string $venceEl,
        public readonly string $enlace,
    ) {}

    public function envelope(): Envelope
    {
        // Sin saltos de línea en el asunto, venga lo que venga en el nombre del establecimiento.
        $nombre = trim((string) preg_replace('/\s+/u', ' ', $this->establecimiento));

        return new Envelope(subject: "Invitación a VINKURED de {$nombre}");
    }

    public function content(): Content
    {
        return new Content(text: 'mail.invitacion');
    }
}
