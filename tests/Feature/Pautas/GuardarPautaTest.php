<?php

use App\Actions\GuardarPauta;
use App\Models\Acompanamiento;
use App\Models\Pauta;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/helpers.php';

uses(RefreshDatabase::class);

beforeEach(function () {
    escenarioDePautas($this);
});

it('crea una pauta del establecimiento activo con los indicadores en orden de catálogo', function () {
    $pauta = crearPauta($this->administrador, '  Pauta   de aula  ', [$this->ind[2001], $this->ind[1003], $this->ind[1001]]);

    expect($pauta->nombre)->toBe('Pauta de aula')
        ->and((int) $pauta->establecimiento_id)->toBe($this->a)
        ->and(indicadoresGuardados($pauta->id))->toBe([$this->ind[1001], $this->ind[1003], $this->ind[2001]])
        ->and(posicionesGuardadas($pauta->id))->toBe([1, 2, 3])
        ->and(DB::table('indicador_pauta')->where('pauta_id', $pauta->id)->distinct()->pluck('establecimiento_id')->all())->toBe([$this->a]);
});

it('ignora indicadores repetidos y acepta ids como texto', function () {
    $pauta = crearPauta($this->administrador, 'Pauta', [(string) $this->ind[1002], $this->ind[1002], $this->ind[1001]]);

    expect(indicadoresGuardados($pauta->id))->toBe([$this->ind[1001], $this->ind[1002]]);
});

it('rechaza un nombre vacío o demasiado largo', function (string $nombre) {
    rechazaCampo(fn () => crearPauta($this->administrador, $nombre, [$this->ind[1001]]), 'nombre');

    expect(Pauta::query()->count())->toBe(0);
})->with(['vacío' => '', 'solo espacios' => "   \t ", 'largo' => str_repeat('a', 121)]);

it('cuenta el largo del nombre en caracteres, no en bytes', function () {
    $justo = str_repeat('ñ', GuardarPauta::NOMBRE_MAXIMO);

    expect(crearPauta($this->administrador, $justo, [$this->ind[1001]])->nombre)->toBe($justo);
    rechazaCampo(fn () => crearPauta($this->administrador, str_repeat('á', GuardarPauta::NOMBRE_MAXIMO + 1), [$this->ind[1001]]), 'nombre');
    expect(Pauta::query()->count())->toBe(1);
});

it('rechaza un nombre repetido entre las pautas vigentes del establecimiento, sin distinguir mayúsculas', function () {
    crearPauta($this->administrador, 'Pauta de aula', [$this->ind[1001]]);

    rechazaCampo(fn () => crearPauta($this->administrador, ' PAUTA DE AULA ', [$this->ind[1002]]), 'nombre');

    expect(Pauta::query()->count())->toBe(1);
});

it('permite repetir el nombre de una pauta eliminada o de otro establecimiento', function () {
    $eliminada = crearPauta($this->administrador, 'Pauta de aula', [$this->ind[1001]]);
    eliminarPauta($this->administrador, $eliminada->id);

    [$administradorB] = usuarioDelModulo($this->b, 'administrador');
    app()->forgetScopedInstances();
    activar($administradorB, $this->b);
    crearPauta($administradorB, 'Pauta de aula B', [$this->ind[1001]]);

    app()->forgetScopedInstances();
    activar($this->administrador, $this->a);

    expect(crearPauta($this->administrador, 'Pauta de aula', [$this->ind[1002]])->exists)->toBeTrue()
        ->and(crearPauta($this->administrador, 'Pauta de aula B', [$this->ind[1002]])->exists)->toBeTrue();
});

// Closure-typed parameter: Pest passes the dataset closure unresolved, so it can read $this's indicators.
it('exige al menos un indicador del catálogo', function (Closure $ids) {
    rechazaCampo(fn () => crearPauta($this->administrador, 'Pauta', $ids($this)), 'indicadores');

    expect(Pauta::query()->count())->toBe(0);
})->with([
    'ninguno' => fn (): array => [],
    'inexistente' => fn (object $test): array => [$test->ind[1001], 999999999],
    'no numérico' => fn (object $test): array => [$test->ind[1001], 'abc'],
    'cero' => fn (): array => [0],
]);

it('niega crear a quien no es administrador del módulo', function () {
    app()->forgetScopedInstances();
    activar($this->usuarioDocente, $this->a);

    expect(fn () => crearPauta($this->usuarioDocente, 'Pauta', [$this->ind[1001]]))->toThrow(AuthorizationException::class)
        ->and(Pauta::query()->count())->toBe(0);
});

it('niega crear por un usuario distinto del autenticado', function () {
    app()->forgetScopedInstances();
    activar($this->usuarioDocente, $this->a);

    expect(fn () => crearPauta($this->administrador, 'Pauta', [$this->ind[1001]]))->toThrow(AuthorizationException::class);
});

it('actualiza nombre e indicadores manteniendo el orden de catálogo', function () {
    $pauta = crearPauta($this->administrador, 'Pauta', [$this->ind[1001], $this->ind[2002]]);

    $actualizada = actualizarPauta($this->administrador, $pauta->id, 'Pauta nueva', [$this->ind[2001], $this->ind[1002], $this->ind[2002]]);

    expect($actualizada->nombre)->toBe('Pauta nueva')
        ->and(Pauta::query()->findOrFail($pauta->id)->nombre)->toBe('Pauta nueva')
        ->and(indicadoresGuardados($pauta->id))->toBe([$this->ind[1002], $this->ind[2001], $this->ind[2002]])
        ->and(posicionesGuardadas($pauta->id))->toBe([1, 2, 3]);
});

it('deja conservar el propio nombre al actualizar pero no tomar el de otra pauta', function () {
    $pauta = crearPauta($this->administrador, 'Pauta uno', [$this->ind[1001]]);
    crearPauta($this->administrador, 'Pauta dos', [$this->ind[1001]]);

    expect(actualizarPauta($this->administrador, $pauta->id, 'PAUTA UNO', [$this->ind[1002]])->nombre)->toBe('PAUTA UNO');

    rechazaCampo(fn () => actualizarPauta($this->administrador, $pauta->id, 'pauta dos', [$this->ind[1001]]), 'nombre');
});

it('valida igual al actualizar y no cambia nada si falla', function () {
    $pauta = crearPauta($this->administrador, 'Pauta', [$this->ind[1001]]);

    rechazaCampo(fn () => actualizarPauta($this->administrador, $pauta->id, 'Otra', []), 'indicadores');
    rechazaCampo(fn () => actualizarPauta($this->administrador, $pauta->id, '', [$this->ind[1002]]), 'nombre');

    expect(Pauta::query()->findOrFail($pauta->id)->nombre)->toBe('Pauta')
        ->and(indicadoresGuardados($pauta->id))->toBe([$this->ind[1001]]);
});

it('niega actualizar a quien no es administrador del módulo', function () {
    $pauta = crearPauta($this->administrador, 'Pauta', [$this->ind[1001]]);
    app()->forgetScopedInstances();
    activar($this->usuarioDocente, $this->a);

    expect(fn () => actualizarPauta($this->usuarioDocente, $pauta->id, 'Otra', [$this->ind[1002]]))->toThrow(AuthorizationException::class)
        ->and(indicadoresGuardados($pauta->id))->toBe([$this->ind[1001]]);
});

it('no encuentra la pauta de otro establecimiento ni una eliminada', function () {
    $pautaA = crearPauta($this->administrador, 'Pauta A', [$this->ind[1001]]);
    $eliminada = crearPauta($this->administrador, 'Pauta eliminada', [$this->ind[1001]]);
    eliminarPauta($this->administrador, $eliminada->id);

    expect(fn () => actualizarPauta($this->administrador, $eliminada->id, 'Revivida', [$this->ind[1002]]))->toThrow(ModelNotFoundException::class);

    [$administradorB] = usuarioDelModulo($this->b, 'administrador');
    app()->forgetScopedInstances();
    activar($administradorB, $this->b);

    expect(fn () => actualizarPauta($administradorB, $pautaA->id, 'Robada', [$this->ind[1002]]))->toThrow(ModelNotFoundException::class)
        ->and(Pauta::sinEstablecimiento()->findOrFail($pautaA->id)->nombre)->toBe('Pauta A')
        ->and(indicadoresGuardados($pautaA->id))->toBe([$this->ind[1001]]);
});

it('permite cambiar los indicadores de una pauta con acompañamientos sin alterar su snapshot', function () {
    $pauta = crearPauta($this->administrador, 'Pauta', [$this->ind[1001], $this->ind[1002]]);
    $acompanamiento = Acompanamiento::factory()->create(['pauta_id' => $pauta->id]);

    actualizarPauta($this->administrador, $pauta->id, 'Pauta', [$this->ind[2001]]);

    $snapshot = Acompanamiento::query()->findOrFail($acompanamiento->id)->pauta_snapshot;

    expect(collect($snapshot['indicadores'])->pluck('id')->all())->toBe([$this->ind[1001], $this->ind[1002]])
        ->and(indicadoresGuardados($pauta->id))->toBe([$this->ind[2001]]);
});
