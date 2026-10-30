<?php

use App\Exceptions\PautaNoDisponible;
use App\Models\Acompanamiento;
use App\Models\Pauta;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__.'/helpers.php';

uses(RefreshDatabase::class);

beforeEach(function () {
    escenarioDePautas($this);
    $this->pauta = crearPauta($this->administrador, 'Pauta', [$this->ind[1001], $this->ind[1002]]);
});

it('elimina la pauta de forma lógica', function () {
    eliminarPauta($this->administrador, $this->pauta->id);

    expect(Pauta::query()->find($this->pauta->id))->toBeNull()
        ->and(Pauta::query()->withTrashed()->findOrFail($this->pauta->id)->trashed())->toBeTrue();
});

it('elimina una pauta con acompañamientos: estos conservan su pauta y su snapshot', function () {
    $acompanamiento = Acompanamiento::factory()->create(['pauta_id' => $this->pauta->id]);

    eliminarPauta($this->administrador, $this->pauta->id);

    $releido = Acompanamiento::query()->findOrFail($acompanamiento->id);

    expect($releido->pauta->id)->toBe($this->pauta->id)
        ->and(collect($releido->pauta_snapshot['indicadores'])->pluck('id')->all())->toBe([$this->ind[1001], $this->ind[1002]])
        ->and(fn () => Acompanamiento::factory()->create(['pauta_id' => $this->pauta->id]))->toThrow(PautaNoDisponible::class);
});

it('niega eliminar a quien no es administrador del módulo', function () {
    app()->forgetScopedInstances();
    activar($this->usuarioDocente, $this->a);

    expect(fn () => eliminarPauta($this->usuarioDocente, $this->pauta->id))->toThrow(AuthorizationException::class)
        ->and(Pauta::query()->find($this->pauta->id))->not->toBeNull();
});

it('no encuentra la pauta de otro establecimiento ni una ya eliminada', function () {
    [$administradorB] = usuarioDelModulo($this->b, 'administrador');
    app()->forgetScopedInstances();
    activar($administradorB, $this->b);

    expect(fn () => eliminarPauta($administradorB, $this->pauta->id))->toThrow(ModelNotFoundException::class)
        ->and(Pauta::sinEstablecimiento()->findOrFail($this->pauta->id)->trashed())->toBeFalse();

    app()->forgetScopedInstances();
    activar($this->administrador, $this->a);
    eliminarPauta($this->administrador, $this->pauta->id);

    expect(fn () => eliminarPauta($this->administrador, $this->pauta->id))->toThrow(ModelNotFoundException::class);
});
