<?php

use App\Models\AsignacionDocente;
use App\Models\Curso;
use App\Models\Establecimiento;
use App\Models\Funcionario;
use App\Models\Persona;
use App\Models\Usuario;
use App\Support\EstablecimientoActual;
use App\Support\RolesDelUsuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('creates host rows deterministically with the ported helpers', function () {
    $estId = newEstablecimiento('4001', 'Colegio Uno');
    $personaId = newPersona($estId, '11-1');
    $funcionarioId = newFuncionario($estId);
    $cursoId = newCurso($estId);
    $asignacionId = newAsignacion($estId, $funcionarioId, $cursoId);

    expect(Establecimiento::query()->find($estId)?->nombre)->toBe('Colegio Uno')
        ->and(DB::table('personas')->where('id', $personaId)->value('numero_documento'))->toBe('11-1')
        ->and(DB::table('asignaciones_docentes')->where('id', $asignacionId)->value('es_jefatura'))->toBeTrue()
        ->and(DB::table('cursos')->where('id', $cursoId)->value('establecimiento_id'))->toBe($estId);
});

it('reads host rows through the host models once an establecimiento is active', function () {
    $estId = newEstablecimiento('4002');
    [$usuario, $funcionarioId] = usuarioConRoles($estId, funciones: [newFuncion('directivo', 'director')], jefatura: true);

    activar($usuario, $estId);

    $funcionario = Funcionario::query()->findOrFail($funcionarioId);

    expect($usuario)->toBeInstanceOf(Usuario::class)
        ->and(app(EstablecimientoActual::class)->id())->toBe($estId)
        ->and($funcionario->persona)->toBeInstanceOf(Persona::class)
        ->and($funcionario->funcionesAsignadas()->with('funcion')->first()->funcion->clave)->toBe('director')
        ->and($funcionario->asignaciones)->toHaveCount(1)
        ->and(AsignacionDocente::query()->first()->curso)->toBeInstanceOf(Curso::class)
        ->and(Curso::query()->with('grado')->first()->nombreCompleto())->toBe('1 J');
});

it('derives roles from funciones and membresias like the host', function () {
    $estId = newEstablecimiento('4003');
    [$usuario] = usuarioConRoles($estId, administrador: true, funciones: [newFuncion('directivo', 'director')]);

    activar($usuario, $estId);
    $roles = new RolesDelUsuario(app(EstablecimientoActual::class));

    expect($roles->esDelUsuario($usuario))->toBeTrue()
        ->and($roles->esAdministrador())->toBeTrue()
        ->and($roles->esDirectivo())->toBeTrue()
        ->and($roles->esCoordinador())->toBeFalse()
        ->and($roles->esProfesorJefe())->toBeFalse();
});
