<?php

use App\Models\Acompanamiento;
use App\Models\RespuestaAcompanamiento;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__.'/helpers.php';

uses(RefreshDatabase::class);

beforeEach(function () {
    escenarioDeAcompanamientos($this);
    $this->acompanamiento = iniciarAcompanamiento($this);
    guardarRespuesta($this->observador, $this->acompanamiento, $this->ind[1001], 'logrado');
});

it('descarta un acompañamiento en curso junto con sus respuestas', function () {
    descartarAcompanamiento($this->observador, $this->acompanamiento);

    expect(Acompanamiento::query()->whereKey($this->acompanamiento->id)->exists())->toBeFalse()
        ->and(RespuestaAcompanamiento::query()->count())->toBe(0);
});

it('niega descartar un acompañamiento finalizado', function () {
    guardarRespuesta($this->observador, $this->acompanamiento, $this->ind[1002], 'logrado');
    $this->acompanamiento->finalizar();

    expect(fn () => descartarAcompanamiento($this->observador, $this->acompanamiento))->toThrow(AuthorizationException::class);
    expect(Acompanamiento::query()->whereKey($this->acompanamiento->id)->exists())->toBeTrue()
        ->and(RespuestaAcompanamiento::query()->count())->toBe(2);
});

it('niega descartar el acompañamiento de otro observador', function () {
    activar($this->otroObservador, $this->a);

    expect(fn () => descartarAcompanamiento($this->otroObservador, $this->acompanamiento))->toThrow(AuthorizationException::class);
    expect(Acompanamiento::query()->whereKey($this->acompanamiento->id)->exists())->toBeTrue();
});
