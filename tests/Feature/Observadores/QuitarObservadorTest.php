<?php

use App\Models\Acompanamiento;
use App\Models\AsignacionAcompanamiento;
use App\Support\RolesDelModulo;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/helpers.php';

uses(RefreshDatabase::class);

beforeEach(function () {
    escenarioDeObservadores($this);
    habilitarObservador($this->a, $this->docenteId);
    AsignacionAcompanamiento::factory()->create(['observador_id' => $this->docenteId, 'docente_id' => $this->otroDocenteId]);
    AsignacionAcompanamiento::factory()->create(['observador_id' => $this->directivoId, 'docente_id' => $this->otroDocenteId]);
});

it('quita al observador habilitado y borra sus asignaciones, no las de otros', function () {
    quitar($this->administrador, $this->docenteId);

    expect(esObservadorHabilitado($this->docenteId))->toBeFalse()
        ->and(app(RolesDelModulo::class)->puedeObservar($this->docenteId))->toBeFalse()
        ->and(docentesDe($this->docenteId))->toBe([])
        ->and(docentesDe($this->directivoId))->toBe([$this->otroDocenteId]);
});

it('conserva los acompañamientos del ex observador como historial', function () {
    $acompanamiento = Acompanamiento::factory()->finalizado()->create([
        'observador_id' => $this->docenteId, 'docente_id' => $this->otroDocenteId,
    ]);

    quitar($this->administrador, $this->docenteId);

    expect(Acompanamiento::query()->whereKey($acompanamiento->id)->exists())->toBeTrue();
});

it('es idempotente: quitar a quien no es observador no hace nada', function () {
    quitar($this->administrador, $this->docenteId);
    quitar($this->administrador, $this->docenteId);
    quitar($this->administrador, $this->otroDocenteId);

    expect(esObservadorHabilitado($this->docenteId))->toBeFalse();
});

it('no quita a un observador automático ni toca sus asignaciones', function () {
    habilitarObservador($this->a, $this->directivoId);

    expect(fn () => quitar($this->administrador, $this->directivoId))->toThrow(ValidationException::class)
        ->and(esObservadorHabilitado($this->directivoId))->toBeTrue()
        ->and(docentesDe($this->directivoId))->toBe([$this->otroDocenteId]);
});

it('niega quitar a quien no es administrador del módulo', function () {
    activar($this->usuarioDocente, $this->a);

    expect(fn () => quitar($this->usuarioDocente, $this->docenteId))->toThrow(AuthorizationException::class)
        ->and(esObservadorHabilitado($this->docenteId))->toBeTrue()
        ->and(docentesDe($this->docenteId))->toBe([$this->otroDocenteId]);
});

it('no alcanza a un observador de otro establecimiento', function () {
    habilitarObservador($this->b, $this->docenteAjenoId);

    expect(fn () => quitar($this->administrador, $this->docenteAjenoId))->toThrow(ModelNotFoundException::class)
        ->and(esObservadorHabilitado($this->docenteAjenoId))->toBeTrue();
});
