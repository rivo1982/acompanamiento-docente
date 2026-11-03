<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Aviso de texto plano al docente cuando su observador finaliza un acompañamiento de aula. Español de Chile,
 * tratamiento de "usted". No lleva resultados: solo quién observó, la fecha y el enlace para verlo en el módulo
 * (que exige iniciar sesión).
 *
 * Solo recibe cadenas ya armadas. Lo encola FinalizarAcompanamiento después de confirmar la transacción.
 */
class AcompanamientoFinalizado extends Mailable
{
    use Queueable;

    /**
     * Ruta del resultado del acompañamiento a la que lleva el enlace.
     */
    public const RUTA = 'acompanamientos.show';

    public function __construct(
        public readonly string $observador,
        public readonly string $fecha,
        public readonly string $enlace,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Nuevo acompañamiento de aula finalizado');
    }

    public function content(): Content
    {
        return new Content(text: 'mail.acompanamiento-finalizado');
    }
}
