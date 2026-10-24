<?php

use App\Livewire\Importaciones\ResultadoDeImportacion;
use Database\Seeders\FuncionesGenericasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

function resultadoDe(int $id): Testable
{
    return Livewire::test(ResultadoDeImportacion::class, ['importacion' => $id]);
}

it('shows a pending import with polling on and a single status region', function (string $estado) {
    $id = importacionDePrueba(administradorDe($this->a), ['archivo' => 'Planilla ficticia.xlsx', 'estado' => $estado]);

    $respuesta = $this->get(route('importaciones.show', $id));

    $respuesta->assertOk()
        ->assertSee('<title>Resultado de la importación - VINKURED</title>', false)
        ->assertSee('Planilla ficticia.xlsx')
        ->assertSee('wire:poll.5s', false)
        ->assertSee('Procesando la planilla. Esta página se actualiza sola.')
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
        ->toContain('Importación terminada. Personas invitadas: 1.247. Filas con error: 3.')
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

it('shows a failed import with its file-level message as an Aviso and no polling', function () {
    $id = importacionDePrueba(administradorDe($this->a), [
        'estado' => 'fallida',
        'errores' => [['fila' => null, 'mensajes' => ['La planilla está vacía.']]],
    ]);

    $html = resultadoDe($id)
        ->assertDontSee('wire:poll', false)
        ->assertSee('La importación no se pudo completar.')
        ->assertSee('Fallida')
        ->assertDontSee('Filas con error')
        ->html();

    expect($html)->toMatch('/<div[^>]*\bbg-aviso-fondo\b[^>]*>.*La planilla está vacía\..*<\/div>/s')
        ->and($html)->not->toContain('bg-peligro-fondo');
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
