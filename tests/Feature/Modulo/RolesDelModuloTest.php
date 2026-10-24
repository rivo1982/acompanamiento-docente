<?php

/*
 * A3: module roles derived from Vinkured's funciones, membresias and the module's observadores
 * table, always within the active establecimiento.
 */

use App\Support\RolesDelModulo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->establecimiento = newEstablecimiento('1000', 'Colegio A');
});

function rolesDe($usuario, int $estId): RolesDelModulo
{
    app()->forgetScopedInstances();
    activar($usuario, $estId);

    return app(RolesDelModulo::class);
}

it('resuelve los roles de cada usuario del módulo', function (string $rol, bool $administrador, bool $observador, bool $docente) {
    [$usuario] = usuarioDelModulo($this->establecimiento, $rol);

    $roles = rolesDe($usuario, $this->establecimiento);

    expect($roles->esAdministrador($usuario))->toBe($administrador)
        ->and($roles->esObservador($usuario))->toBe($observador)
        ->and($roles->esDocente($usuario))->toBe($docente)
        ->and($roles->esFuncionario($usuario))->toBeTrue();
})->with([
    'directivo' => ['administrador', true, true, false],
    'observador habilitado' => ['observador', false, true, true],
    'docente' => ['docente', false, false, true],
    'asistente' => ['asistente', false, false, false],
]);

it('reconoce como docente a quien tiene una asignación docente aunque no tenga función docente', function () {
    [$usuario] = usuarioConRoles($this->establecimiento, jefatura: true);

    expect(rolesDe($usuario, $this->establecimiento)->esDocente($usuario))->toBeTrue();
});

it('no da roles del módulo al administrador de Vinkured sin función directiva', function () {
    [$usuario] = usuarioConRoles($this->establecimiento, administrador: true);

    $roles = rolesDe($usuario, $this->establecimiento);

    expect($roles->esAdministrador($usuario))->toBeFalse()
        ->and($roles->esObservador($usuario))->toBeFalse()
        ->and($roles->esFuncionario($usuario))->toBeFalse();
});

it('no da roles a un funcionario con la fecha de término vencida', function () {
    [$usuario, $funcionario] = usuarioConRoles($this->establecimiento, funciones: [newFuncion('directivo')], fechaTermino: now()->subDay()->toDateString());
    habilitarObservador($this->establecimiento, $funcionario);

    $roles = rolesDe($usuario, $this->establecimiento);

    expect($roles->esAdministrador($usuario))->toBeFalse()
        ->and($roles->esObservador($usuario))->toBeFalse()
        ->and($roles->funcionarios())->toBe([]);
});

it('pierde el rol de observador al quitarlo de la tabla de observadores', function () {
    [$usuario, $funcionario] = usuarioDelModulo($this->establecimiento, 'observador');

    DB::table('observadores')->where('funcionario_id', $funcionario)->delete();

    expect(rolesDe($usuario, $this->establecimiento)->esObservador($usuario))->toBeFalse();
});

it('no lleva los roles de un establecimiento a otro', function () {
    $otro = newEstablecimiento('2000', 'Colegio B');
    [$usuario] = usuarioDelModulo($this->establecimiento, 'administrador');
    usuarioDelModulo($otro, 'asistente', $usuario);

    $roles = rolesDe($usuario, $otro);

    expect($roles->esAdministrador($usuario))->toBeFalse()
        ->and($roles->esObservador($usuario))->toBeFalse()
        ->and(rolesDe($usuario, $this->establecimiento)->esAdministrador($usuario))->toBeTrue();
});

it('falla cerrado para un usuario distinto del autenticado y sin establecimiento activo', function () {
    [$directivo] = usuarioDelModulo($this->establecimiento, 'administrador');
    [$otro] = usuarioDelModulo($this->establecimiento, 'docente');

    $roles = rolesDe($otro, $this->establecimiento);
    expect($roles->esAdministrador($directivo))->toBeFalse()
        ->and($roles->esFuncionario($directivo))->toBeFalse();

    sinContexto($directivo);
    expect(app(RolesDelModulo::class)->esAdministrador($directivo))->toBeFalse();
});

it('evalúa la elegibilidad de cualquier funcionario del establecimiento activo', function () {
    activar(usuarioConMembresia($this->establecimiento), $this->establecimiento);
    $roles = app(RolesDelModulo::class);

    $directivo = funcionarioConFuncion($this->establecimiento, 'directivo');
    $habilitado = funcionarioConFuncion($this->establecimiento, 'asistente');
    habilitarObservador($this->establecimiento, $habilitado);
    $docente = funcionarioConFuncion($this->establecimiento, 'docente');
    $asistente = funcionarioConFuncion($this->establecimiento, 'asistente');
    $ajeno = funcionarioConFuncion(newEstablecimiento('2000', 'Colegio B'), 'directivo');

    expect($roles->puedeObservar($directivo))->toBeTrue()
        ->and($roles->puedeObservar($habilitado))->toBeTrue()
        ->and($roles->puedeObservar($docente))->toBeFalse()
        ->and($roles->puedeObservar($ajeno))->toBeFalse()
        ->and($roles->puedeSerObservado($docente))->toBeTrue()
        ->and($roles->puedeSerObservado($asistente))->toBeFalse()
        ->and($roles->puedeSerObservado($directivo))->toBeFalse();
});
