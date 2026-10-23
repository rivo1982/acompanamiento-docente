<?php

/*
 * Ported from Vinkured tests/Feature/Modelos/AislamientoPorEstablecimientoTest.php, limited to
 * the host models copied into this repository. The bodies of the ported cases are unchanged;
 * only the dataset and the fixture (Vinkured's poblarEstablecimiento covers alerts and cases,
 * which this module does not copy) are reduced.
 */

use App\Exceptions\EstablecimientoAjeno;
use App\Exceptions\SinEstablecimientoActivo;
use App\Models\AnioEscolar;
use App\Models\AsignacionDocente;
use App\Models\Curso;
use App\Models\Funcion;
use App\Models\Funcionario;
use App\Models\FuncionarioFuncion;
use App\Models\Persona;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/** @return array<string, array{0: class-string<Model>, 1: string}> model => [class, table] */
function modelosHostDeInquilino(): array
{
    return [
        'Persona' => [Persona::class, 'personas'],
        'Funcionario' => [Funcionario::class, 'funcionarios'],
        'FuncionarioFuncion' => [FuncionarioFuncion::class, 'funcionario_funciones'],
        'Curso' => [Curso::class, 'cursos'],
        'AsignacionDocente' => [AsignacionDocente::class, 'asignaciones_docentes'],
        'AnioEscolar' => [AnioEscolar::class, 'anios_escolares'],
    ];
}

/**
 * One row in every copied tenant table for $estId. Returns [table => id].
 *
 * @return array<string, int>
 */
function poblarEstablecimientoHost(int $estId): array
{
    $funcionarioId = newFuncionario($estId);
    $cursoId = newCurso($estId);

    return [
        'personas' => newPersona($estId, nextDocumento()),
        'funcionarios' => $funcionarioId,
        'funcionario_funciones' => asignarFuncion($estId, $funcionarioId, newFuncion()),
        'cursos' => $cursoId,
        'asignaciones_docentes' => newAsignacion($estId, $funcionarioId, $cursoId),
        'anios_escolares' => (int) DB::table('cursos')->where('id', $cursoId)->value('anio_escolar_id'),
    ];
}

beforeEach(function () {
    $this->a = newEstablecimiento('2001', 'Colegio A');
    $this->b = newEstablecimiento('2002', 'Colegio B');
    $this->filasA = poblarEstablecimientoHost($this->a);
    $this->filasB = poblarEstablecimientoHost($this->b);

    [$this->usuario] = usuarioConRoles($this->a);
    usuarioConRoles($this->b, usuario: $this->usuario);
});

it('reads only the active establecimiento', function (string $clase, string $tabla) {
    activar($this->usuario, $this->a);

    expect($clase::pluck('id')->all())->toContain($this->filasA[$tabla])->not->toContain($this->filasB[$tabla])
        ->and($clase::find($this->filasB[$tabla]))->toBeNull();
})->with(modelosHostDeInquilino());

it('fails closed without an active establecimiento when reading', function (string $clase) {
    sinContexto($this->usuario);

    expect(fn () => $clase::count())->toThrow(SinEstablecimientoActivo::class)
        ->and(fn () => $clase::all())->toThrow(SinEstablecimientoActivo::class)
        ->and(fn () => $clase::find(1))->toThrow(SinEstablecimientoActivo::class)
        ->and(fn () => $clase::query()->first())->toThrow(SinEstablecimientoActivo::class)
        ->and(fn () => $clase::query()->exists())->toThrow(SinEstablecimientoActivo::class)
        ->and(fn () => $clase::query()->paginate())->toThrow(SinEstablecimientoActivo::class)
        ->and(fn () => $clase::query()->max('id'))->toThrow(SinEstablecimientoActivo::class)
        ->and(fn () => $clase::query()->pluck('id'))->toThrow(SinEstablecimientoActivo::class);
})->with(array_map(fn ($m) => $m[0], modelosHostDeInquilino()));

it('fails closed without an active establecimiento when creating', function () {
    sinContexto($this->usuario);

    expect(fn () => Persona::create(['tipo_documento' => 'rut', 'numero_documento' => '9-9', 'nombres' => 'Ana', 'primer_apellido' => 'Perez']))
        ->toThrow(SinEstablecimientoActivo::class)
        ->and(DB::table('personas')->where('numero_documento', '9-9')->exists())->toBeFalse();

    // Even through the escape hatch, creating still needs a context.
    expect(fn () => Persona::sinEstablecimiento()->create(['tipo_documento' => 'rut', 'numero_documento' => '9-8', 'nombres' => 'Ana', 'primer_apellido' => 'Perez']))
        ->toThrow(SinEstablecimientoActivo::class);
});

it('rejects an explicit establecimiento that is not the active one', function () {
    activar($this->usuario, $this->a);

    $persona = new Persona(['tipo_documento' => 'rut', 'numero_documento' => '5-5', 'nombres' => 'Ana', 'primer_apellido' => 'Perez']);
    $persona->establecimiento_id = $this->b;

    expect(fn () => $persona->save())->toThrow(EstablecimientoAjeno::class)
        ->and(DB::table('personas')->where('numero_documento', '5-5')->exists())->toBeFalse();

    $persona->establecimiento_id = $this->a;
    $persona->save();
    expect($persona->exists)->toBeTrue();
});

it('treats Funcion as a global catalog readable without a context', function () {
    $id = newFuncion('directivo', 'director');
    sinContexto();

    expect(Funcion::where('clave', 'director')->value('id'))->toBe($id)
        ->and(Funcion::count())->toBeGreaterThan(0);
});
