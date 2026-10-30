<?php

use App\Support\RolesDelModulo;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/helpers.php';

uses(RefreshDatabase::class);

beforeEach(function () {
    escenarioDeObservadores($this);
});

it('habilita a un funcionario vigente del establecimiento activo como observador', function () {
    $observador = habilitar($this->administrador, $this->docenteId);

    expect((int) $observador->funcionario_id)->toBe($this->docenteId)
        ->and((int) $observador->establecimiento_id)->toBe($this->a)
        ->and(app(RolesDelModulo::class)->puedeObservar($this->docenteId))->toBeTrue();
});

it('es idempotente: habilitar dos veces deja una sola fila', function () {
    $primera = habilitar($this->administrador, $this->docenteId);
    $segunda = habilitar($this->administrador, $this->docenteId);

    expect($segunda->id)->toBe($primera->id)
        ->and(DB::table('observadores')->where('funcionario_id', $this->docenteId)->count())->toBe(1);
});

it('niega habilitar a quien no es administrador del módulo', function () {
    [$observador] = usuarioDelModulo($this->a, 'observador');

    foreach ([$this->usuarioDocente, $observador] as $usuario) {
        app()->forgetScopedInstances();
        activar($usuario, $this->a);

        expect(fn () => habilitar($usuario, $this->otroDocenteId))->toThrow(AuthorizationException::class);
    }

    expect(esObservadorHabilitado($this->otroDocenteId))->toBeFalse();
});

it('niega habilitar por un usuario distinto del autenticado', function () {
    activar($this->usuarioDocente, $this->a);

    expect(fn () => habilitar($this->administrador, $this->otroDocenteId))->toThrow(AuthorizationException::class);
});

it('rechaza un funcionario de otro establecimiento', function () {
    expect(fn () => habilitar($this->administrador, $this->docenteAjenoId))->toThrow(ModelNotFoundException::class)
        ->and(esObservadorHabilitado($this->docenteAjenoId))->toBeFalse();
});

it('rechaza un funcionario que ya no está vigente', function () {
    DB::table('funcionarios')->where('id', $this->otroDocenteId)->update(['fecha_termino' => now()->subDay()->toDateString()]);

    expect(fn () => habilitar($this->administrador, $this->otroDocenteId))->toThrow(ModelNotFoundException::class)
        ->and(esObservadorHabilitado($this->otroDocenteId))->toBeFalse();
});

it('rechaza a un observador automático (directivo)', function (string $quien) {
    $id = $quien === 'otro directivo' ? $this->directivoId : $this->administradorId;

    expect(fn () => habilitar($this->administrador, $id))->toThrow(ValidationException::class)
        ->and(esObservadorHabilitado($id))->toBeFalse();
})->with(['otro directivo', 'el propio administrador']);
