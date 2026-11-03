<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->establecimientoId = newEstablecimiento('1000', 'Colegio A');
});

it('sends the site root to the provisional home instead of the framework welcome page', function () {
    $this->get('/')->assertRedirect(route('panel'));
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

it('links a teacher only to the rubrics screen', function () {
    [$docente] = usuarioDelModulo($this->establecimientoId, 'docente');
    activar($docente, $this->establecimientoId);

    $this->get(route('panel'))
        ->assertOk()
        ->assertSee(route('pautas.index'), escape: false)
        ->assertDontSee(route('observadores.index'), escape: false)
        ->assertDontSee(route('invitaciones.index'), escape: false)
        ->assertDontSee(route('importaciones.index'), escape: false);
});

it('links an observer to the observations screen and hides it from a teacher who does not observe', function () {
    [$observador] = usuarioDelModulo($this->establecimientoId, 'observador');
    activar($observador, $this->establecimientoId);

    $this->get(route('panel'))
        ->assertOk()
        ->assertSee(route('acompanamientos.index'), escape: false);

    [$docente] = usuarioDelModulo($this->establecimientoId, 'docente');
    app()->forgetScopedInstances();
    activar($docente, $this->establecimientoId);

    $this->get(route('panel'))
        ->assertOk()
        ->assertDontSee(route('acompanamientos.index'), escape: false);
});
