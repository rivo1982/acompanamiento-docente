<?php

use App\Enums\NivelLogro;
use App\Models\Indicador;
use App\Models\RespuestaAcompanamiento;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__.'/helpers.php';

uses(RefreshDatabase::class);

beforeEach(function () {
    escenarioDeAcompanamientos($this);
    $this->acompanamiento = iniciarAcompanamiento($this);
});

it('guarda el nivel y la retroalimentación personal recortada, y la reemplaza al volver a responder', function () {
    guardarRespuesta($this->observador, $this->acompanamiento, $this->ind[1001], 'en_desarrollo', '  Buen inicio.  ');
    guardarRespuesta($this->observador, $this->acompanamiento, $this->ind[1001], 'logrado', '   ');

    $respuestas = RespuestaAcompanamiento::query()->where('acompanamiento_id', $this->acompanamiento->id)->get();

    expect($respuestas)->toHaveCount(1)
        ->and($respuestas->first()->nivel)->toBe(NivelLogro::Logrado)
        ->and($respuestas->first()->retroalimentacion_personal)->toBeNull();

    guardarRespuesta($this->observador, $this->acompanamiento, $this->ind[1002], 'destacado', '  Muy bien.  ');

    expect(RespuestaAcompanamiento::query()->where('indicador_id', $this->ind[1002])->value('retroalimentacion_personal'))
        ->toBe('Muy bien.');
});

it('rechaza un nivel desconocido y una retroalimentación demasiado larga', function () {
    rechazaElCampo(fn () => guardarRespuesta($this->observador, $this->acompanamiento, $this->ind[1001], 'no_observado'), 'nivel');
    rechazaElCampo(fn () => guardarRespuesta($this->observador, $this->acompanamiento, $this->ind[1001], 'logrado', str_repeat('a', 2001)), 'retroalimentacion_personal');

    expect(RespuestaAcompanamiento::query()->count())->toBe(0);
});

it('rechaza un indicador que no está en el snapshot del acompañamiento', function () {
    $fuera = Indicador::factory()->create()->id;

    rechazaElCampo(fn () => guardarRespuesta($this->observador, $this->acompanamiento, $fuera, 'logrado'), 'indicador');
    expect(RespuestaAcompanamiento::query()->count())->toBe(0);
});

it('niega responder a otro observador y después de finalizar', function () {
    activar($this->otroObservador, $this->a);
    expect(fn () => guardarRespuesta($this->otroObservador, $this->acompanamiento, $this->ind[1001], 'logrado'))
        ->toThrow(AuthorizationException::class);

    activar($this->observador, $this->a);
    $this->acompanamiento->finalizar();

    expect(fn () => guardarRespuesta($this->observador, $this->acompanamiento, $this->ind[1001], 'logrado'))
        ->toThrow(AuthorizationException::class);
    expect(RespuestaAcompanamiento::query()->count())->toBe(0);
});
