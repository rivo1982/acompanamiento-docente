<?php

use App\Models\Acompanamiento;
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

it('links an observer to the observations screen and the new observation form', function () {
    [$observador] = usuarioDelModulo($this->establecimientoId, 'observador');
    activar($observador, $this->establecimientoId);

    $this->get(route('panel'))
        ->assertOk()
        ->assertSee(route('acompanamientos.index'), escape: false)
        ->assertSee(route('acompanamientos.create'), escape: false);
});

it('links a teacher who does not observe to their results, without the new observation form', function () {
    [$docente] = usuarioDelModulo($this->establecimientoId, 'docente');
    activar($docente, $this->establecimientoId);

    $this->get(route('panel'))
        ->assertOk()
        ->assertSee('Mis resultados')
        ->assertSee(route('acompanamientos.index'), escape: false)
        ->assertDontSee(route('acompanamientos.create'), escape: false);
});

it('links a staff member who was already observed to their results even without a teaching role', function () {
    [$asistente, $asistenteId] = usuarioDelModulo($this->establecimientoId, 'asistente');
    [$observador, $observadorId] = usuarioDelModulo($this->establecimientoId, 'observador');
    activar($observador, $this->establecimientoId);
    Acompanamiento::factory()->finalizado()->create(['observador_id' => $observadorId, 'docente_id' => $asistenteId]);
    app()->forgetScopedInstances();
    activar($asistente, $this->establecimientoId);

    $this->get(route('panel'))
        ->assertOk()
        ->assertSee(route('acompanamientos.index'), escape: false);
});

it('does not link the observations screen to a staff member who neither observes nor teaches', function () {
    [$asistente] = usuarioDelModulo($this->establecimientoId, 'asistente');
    activar($asistente, $this->establecimientoId);

    $this->get(route('panel'))
        ->assertOk()
        ->assertDontSee('Mis resultados')
        ->assertDontSee(route('acompanamientos.index'), escape: false);
});
