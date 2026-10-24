<?php

use App\Actions\InvitarFuncionario;
use App\Mail\InvitacionAVinkured;
use App\Models\Membresia;
use App\Support\Importacion\FilaDePlanilla;
use App\Support\Rut;
use Illuminate\Support\Facades\Mail;

/*
 * Helpers shared by tests/Feature/Invitaciones (required with require_once, so they are defined once).
 */

/** Valid RUT for the given body (mod 11 check digit). */
function rutDeInvitado(int $cuerpo): Rut
{
    $suma = 0;
    $factor = 2;
    foreach (array_reverse(str_split((string) $cuerpo)) as $digito) {
        $suma += (int) $digito * $factor;
        $factor = $factor === 7 ? 2 : $factor + 1;
    }

    $dv = match ($resto = 11 - ($suma % 11)) {
        11 => '0',
        10 => 'K',
        default => (string) $resto,
    };

    return Rut::desde("{$cuerpo}-{$dv}");
}

/** A fresh, unused valid RUT. */
function otroRut(): Rut
{
    return rutDeInvitado(10_000_000 + (int) nextDocumento());
}

/**
 * @param  array<string, mixed>  $cambios  named arguments of FilaDePlanilla to override
 */
function filaParaInvitar(array $cambios = []): FilaDePlanilla
{
    return new FilaDePlanilla(...[
        'numeroDeFila' => 2,
        'rut' => rutDeInvitado(12345678),
        'nombres' => 'Ana María',
        'primerApellido' => 'Pérez',
        'segundoApellido' => 'Soto',
        'correo' => 'ana@ejemplo.cl',
        'telefono' => '+56 9 1234 5678',
        'categoriaFuncion' => 'docente',
        ...$cambios,
    ]);
}

/** Active administrador membresia of $estId, made the active establecimiento. */
function administradorDe(int $estId): Membresia
{
    [$usuario] = usuarioConRoles($estId, administrador: true);
    activar($usuario, $estId);

    return Membresia::query()->where('usuario_id', $usuario->id)->where('establecimiento_id', $estId)->firstOrFail();
}

function invitar(Membresia $administrador, ?FilaDePlanilla $fila = null): Membresia
{
    $resultado = app(InvitarFuncionario::class)->invitar($administrador, $fila ?? filaParaInvitar());

    return Membresia::query()->findOrFail($resultado->membresiaId);
}

/** Last invitation mail captured by Mail::fake(). */
function ultimaInvitacion(): InvitacionAVinkured
{
    $enviados = Mail::sent(InvitacionAVinkured::class);
    expect($enviados)->not->toBeEmpty();

    return $enviados->last();
}

/** Plaintext token of the last invitation mail (the only place it exists). */
function tokenDeLaUltimaInvitacion(): string
{
    return basename((string) parse_url(ultimaInvitacion()->enlace, PHP_URL_PATH));
}
