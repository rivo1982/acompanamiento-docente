<?php

/*
 * Ported from Vinkured tests/Feature/Acceso/EstablecimientoActivoTest.php ("selects the only active membership
 * automatically in the selector page", "logs the user out with a message when no active membership remains",
 * "lets the user switch among their own establecimientos from the top bar", "rejects switching to an
 * establecimiento outside the user memberships from the top bar"), adapted to the module's copies of
 * SeleccionarEstablecimiento and SelectorDeEstablecimiento. Fictitious data only.
 */

use App\Livewire\SeleccionarEstablecimiento;
use App\Livewire\SelectorDeEstablecimiento;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->withoutVite());

/** @return array{0: Usuario, 1: int, 2: int, 3: int} user active in A and B, plus a third establecimiento of someone else */
function usuarioEnDosEstablecimientos(): array
{
    $a = newEstablecimiento('3001', 'Colegio Alfa');
    $b = newEstablecimiento('3002', 'Colegio Beta');
    $ajeno = newEstablecimiento('3003', 'Colegio Ajeno');
    $u = usuarioConMembresia($a);
    newMembresia($b, $u->id, null, ['estado' => 'activa']);
    usuarioConMembresia($ajeno);

    return [$u, $a, $b, $ajeno];
}

it('selects the only active membership automatically in the selector page', function () {
    $est = newEstablecimiento('3001');
    $u = usuarioConMembresia($est);

    Livewire::actingAs($u)->test(SeleccionarEstablecimiento::class)->assertRedirect(route('panel'));

    expect(session('establecimiento_id'))->toBe($est);
});

it('logs out a user without any active membership when the selector page mounts', function () {
    $u = usuarioConMembresia(newEstablecimiento('3001'), 'invitada');

    Livewire::actingAs($u)->test(SeleccionarEstablecimiento::class)->assertRedirect(route('login'));

    $this->assertGuest();
    expect(session()->has('establecimiento_id'))->toBeFalse();
});

it('lets the user switch among their own establecimientos from the top bar', function () {
    [$u, $a, $b] = usuarioEnDosEstablecimientos();
    $this->actingAs($u);
    session(['establecimiento_id' => $a]);

    Livewire::actingAs($u)->test(SelectorDeEstablecimiento::class)
        ->set('seleccionado', (string) $b)
        ->call('cambiar')
        ->assertRedirect(route('panel'));

    expect(session('establecimiento_id'))->toBe($b)
        ->and(session('estado'))->toBe('Establecimiento cambiado a Colegio Beta.');
});

it('rejects switching to an establecimiento outside the user memberships from the top bar', function (string $caso) {
    [$u, $a, , $ajeno] = usuarioEnDosEstablecimientos();
    $destino = match ($caso) {
        'ajeno' => $ajeno,
        'invitada' => tap(newEstablecimiento('3004', 'Colegio Invitado'), fn (int $id) => newMembresia($id, $u->id, null, ['estado' => 'invitada'])),
    };
    $this->actingAs($u);
    session(['establecimiento_id' => $a]);

    Livewire::actingAs($u)->test(SelectorDeEstablecimiento::class)
        ->set('seleccionado', (string) $destino)
        ->call('cambiar')
        ->assertForbidden();

    expect(session('establecimiento_id'))->toBe($a);
})->with(['ajeno', 'invitada']);
