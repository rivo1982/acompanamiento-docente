<?php

use App\Livewire\Importaciones\ResultadoDeImportacion;
use Database\Seeders\FuncionesGenericasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

require_once __DIR__.'/../Invitaciones/helpers.php';
require_once __DIR__.'/helpers.php';

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(FuncionesGenericasSeeder::class);
    $this->a = newEstablecimiento('1000', 'Colegio Ficticio A');
    $this->b = newEstablecimiento('2000', 'Colegio Ficticio B');
});

afterEach(function () {
    Carbon::setTestNow();
});

function resultadoDe(int $id): Testable
{
    return Livewire::test(ResultadoDeImportacion::class, ['importacion' => $id]);
}

it('shows a pending import with polling on and a single status region', function (string $estado) {
    $id = importacionDePrueba(administradorDe($this->a), ['archivo' => 'Planilla ficticia.xlsx', 'estado' => $estado]);

    $respuesta = $this->get(route('importaciones.show', $id));

    $respuesta->assertOk()
        ->assertSee('<title>Resultado de la carga masiva - VINKURED</title>', false)
        ->assertSee('Planilla ficticia.xlsx')
        ->assertSee('wire:poll.5s', false)
        ->assertSee('Procesando la carga masiva. Esta página se actualiza sola.')
        ->assertDontSee('Filas con error')
        ->assertDontSee('aria-live', false);
    expect(substr_count($respuesta->getContent(), '<h1'))->toBe(1)
        ->and(substr_count(resultadoDe($id)->html(), 'role="status"'))->toBe(1);
})->with(['pendiente', 'procesando']);

it('stops polling and shows the counters and row errors once the import is complete', function () {
    $admin = administradorDe($this->a);
    $id = importacionDePrueba($admin);
    $componente = resultadoDe($id)->assertSee('wire:poll.5s', false);
    DB::table('importaciones_usuarios')->where('id', $id)->update([
        'estado' => 'completada', 'total' => 1250, 'creados' => 1247, 'con_error' => 3,
        'errores' => json_encode([
            ['fila' => 3, 'mensajes' => ['El RUT no es válido.', 'El correo es obligatorio.']],
            ['fila' => 7, 'mensajes' => ['La función es obligatoria.']],
            ['fila' => 9, 'mensajes' => ['No se pudo procesar esta fila. Volver a intentarlo más tarde.']],
        ]),
    ]);

    $html = $componente->call('$refresh')->html();

    // On the rendered HTML: after an update, Livewire's assertSee* also read the JSON payload, where "á" is escaped.
    expect($html)->not->toContain('wire:poll')
        ->toContain('Carga masiva terminada. Personas invitadas: 1.247. Filas con error: 3.')
        ->toContain('Completada')
        ->toContain(route('invitaciones.index'));
    textosEnOrden($html, ['Filas en la planilla', '1.250', 'Personas invitadas', '1.247', 'Filas con error', '3']);
    textosEnOrden($html, ['>Fila<', '>Mensajes<', '>3<', 'El RUT no es válido.', 'El correo es obligatorio.', '>7<', 'La función es obligatoria.', '>9<']);
});

it('does not offer the pending invitations link when nobody was invited', function () {
    $id = importacionDePrueba(administradorDe($this->a), [
        'estado' => 'completada', 'total' => 1, 'creados' => 0, 'con_error' => 1,
        'errores' => [['fila' => 2, 'mensajes' => ['El correo no es válido.']]],
    ]);

    resultadoDe($id)->assertDontSee(route('invitaciones.index'), false)->assertSee('El correo no es válido.');
});

it('shows a failed import in the status region as an Aviso with what happened, the next step and its file-level messages, and no polling', function () {
    $id = importacionDePrueba(administradorDe($this->a), [
        'estado' => 'fallida',
        'errores' => [['fila' => null, 'mensajes' => ['La planilla está vacía.']]],
    ]);

    $html = resultadoDe($id)
        ->assertDontSee('wire:poll', false)
        ->assertSee('Fallida')
        ->assertDontSee('Filas con error')
        ->html();

    expect($html)->toMatch('/<div\s+id="estado-importacion"\s+role="status"[^>]*\\bbg-aviso-fondo\\b[^>]*>.*La carga masiva no se pudo completar\\. Revisar el archivo y volver a cargarlo\\..*La planilla está vacía\\..*<\/div>/s')
        ->and(substr_count($html, 'role="status"'))->toBe(1)
        ->and(substr_count($html, 'bg-aviso-fondo'))->toBe(2) // the region and the Fallida badge
        ->and($html)->not->toContain('bg-peligro-fondo');
});

it('shows a failed import without file-level messages with the same status text', function () {
    $id = importacionDePrueba(administradorDe($this->a), ['estado' => 'fallida']);

    resultadoDe($id)->assertSee('La carga masiva no se pudo completar. Revisar el archivo y volver a cargarlo.');
});

it('shows the state as a badge with the decided variant', function (string $estado, string $texto, string $fondo) {
    $id = importacionDePrueba(administradorDe($this->a), ['estado' => $estado]);

    expect(resultadoDe($id)->html())->toMatch('/<span class="[^"]*\\b'.$fondo.'\\b[^"]*">'.$texto.'<\/span>/');
})->with([
    ['pendiente', 'Pendiente', 'bg-neutro-fondo'],
    ['procesando', 'Procesando', 'bg-informacion-fondo'],
    ['completada', 'Completada', 'bg-exito-fondo'],
    ['fallida', 'Fallida', 'bg-aviso-fondo'],
]);

it('draws the counter tiles as panels (8 px radius)', function () {
    $id = importacionDePrueba(administradorDe($this->a), ['estado' => 'completada', 'total' => 1, 'creados' => 1]);

    $html = resultadoDe($id)->html();

    expect(substr_count($html, 'rounded-tarjeta border border-borde p-3'))->toBe(3)
        ->and($html)->not->toContain('rounded-control border border-borde p-3');
});

it('stops polling after the configured time and tells what happened and what to do', function (string $estado) {
    config(['importaciones.minutos_de_seguimiento' => 10]);
    Carbon::setTestNow('2026-10-24 10:00:00');
    $id = importacionDePrueba(administradorDe($this->a), ['estado' => $estado, 'created_at' => '2026-10-24 10:00:00']);

    Carbon::setTestNow('2026-10-24 10:09:59');
    $componente = resultadoDe($id)->assertSee('wire:poll.5s', false)->assertDontSee('VINKURED');

    Carbon::setTestNow('2026-10-24 10:10:00');
    $html = $componente->call('$refresh')->html();

    expect($html)->not->toContain('wire:poll')
        ->toMatch('/<div\s+id="estado-importacion"\s+role="status"[^>]*\\bbg-aviso-fondo\\b[^>]*>.*La carga masiva está demorando más de lo esperado y esta página dejó de actualizarse\\. Recargar la página más tarde o, si sigue sin terminar, contactar a soporte de VINKURED\\./s')
        ->and(substr_count($html, 'role="status"'))->toBe(1);
})->with(['pendiente', 'procesando']);

it('reads the polling limit from the configuration', function () {
    config(['importaciones.minutos_de_seguimiento' => 30]);
    Carbon::setTestNow('2026-10-24 10:20:00');
    $id = importacionDePrueba(administradorDe($this->a), ['created_at' => '2026-10-24 10:00:00']);

    resultadoDe($id)->assertSee('wire:poll.5s', false)->assertSee('Procesando la carga masiva. Esta página se actualiza sola.');
});

it('shows the result normally when a delayed import finishes later', function () {
    Carbon::setTestNow('2026-10-24 12:00:00');
    $id = importacionDePrueba(administradorDe($this->a), ['estado' => 'completada', 'total' => 1, 'creados' => 1, 'created_at' => '2026-10-24 10:00:00']);

    resultadoDe($id)->assertDontSee('demorando')->assertSee('Carga masiva terminada.');
});

it('paginates the row errors 20 per page with the host pagination', function () {
    $errores = array_map(fn (int $fila): array => ['fila' => $fila, 'mensajes' => ['Mensaje de la fila '.$fila.'.']], range(2, 24));
    $id = importacionDePrueba(administradorDe($this->a), ['estado' => 'completada', 'total' => 23, 'creados' => 0, 'con_error' => 23, 'errores' => $errores]);

    $componente = resultadoDe($id);
    $html = $componente->html();
    expect($html)->toContain('Mensaje de la fila 21.')
        ->not->toContain('Mensaje de la fila 22.')
        ->toContain('Página 1 de 2')
        ->toContain('data-foco="titulo-errores"')
        ->toContain('<caption class="sr-only">Filas con error</caption>')
        ->toContain('<th scope="col">Fila</th>');

    $html = $componente->call('nextPage')->html();
    expect($html)->toContain('Mensaje de la fila 22.')
        ->toContain('Mensaje de la fila 24.')
        ->not->toContain('Mensaje de la fila 21.')
        ->toContain('Página 2 de 2');
});

it('does not paginate up to 20 row errors', function () {
    $id = importacionDePrueba(administradorDe($this->a), [
        'estado' => 'completada', 'total' => 1, 'creados' => 0, 'con_error' => 1,
        'errores' => [['fila' => 2, 'mensajes' => ['El correo no es válido.']]],
    ]);

    resultadoDe($id)->assertDontSee('Página 1 de');
});

it('is forbidden to members who are not the Administrador', function () {
    $id = importacionDePrueba(administradorDe($this->a));
    [$usuario] = usuarioConRoles($this->a);
    activar($usuario, $this->a);

    $this->get(route('importaciones.show', $id))->assertForbidden();
});

it('hides an import of another establecimiento behind a 404', function () {
    $ajena = importacionDePrueba(administradorDe($this->b), ['archivo' => 'ajena.xlsx']);
    administradorDe($this->a);

    $this->get(route('importaciones.show', $ajena))->assertNotFound()->assertDontSee('ajena.xlsx');
});

it('returns 404 for an unknown or malformed id', function () {
    administradorDe($this->a);

    $this->get(route('importaciones.show', 999999))->assertNotFound();
    $this->get('/usuarios/importaciones/abc')->assertNotFound();
});

it('re-checks the Administrador while polling', function () {
    $admin = administradorDe($this->a);
    $componente = resultadoDe(importacionDePrueba($admin));
    $admin->forceFill(['es_administrador' => false])->save();
    // Each poll is a new HTTP request: the request-scoped roles cache starts empty.
    app()->forgetScopedInstances();

    $componente->call('$refresh')->assertForbidden();
});
