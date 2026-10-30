<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->establecimientoId = newEstablecimiento('1000', 'Colegio A');
});

it('links the module administrator to the observers screen from the provisional home', function () {
    [$administrador] = usuarioDelModulo($this->establecimientoId, 'administrador');
    activar($administrador, $this->establecimientoId);

    $this->get(route('panel'))
        ->assertOk()
        ->assertSee(route('observadores.index'), escape: false);
});

it('links the account administrator to the invitation and bulk import screens', function () {
    [$administrador] = usuarioConRoles($this->establecimientoId, administrador: true);
    activar($administrador, $this->establecimientoId);

    $this->get(route('panel'))
        ->assertOk()
        ->assertSee(route('invitaciones.index'), escape: false)
        ->assertSee(route('invitaciones.create'), escape: false)
        ->assertSee(route('importaciones.index'), escape: false);
});

it('shows no screen links to a teacher', function () {
    [$docente] = usuarioDelModulo($this->establecimientoId, 'docente');
    activar($docente, $this->establecimientoId);

    $this->get(route('panel'))
        ->assertOk()
        ->assertDontSee(route('observadores.index'), escape: false)
        ->assertDontSee(route('invitaciones.index'), escape: false)
        ->assertDontSee(route('importaciones.index'), escape: false);
});
