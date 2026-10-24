<?php

use App\Actions\InvitarFuncionario;
use App\Exceptions\InvitacionEnConflicto;
use App\Jobs\EnviarInvitacion;
use App\Mail\InvitacionAVinkured;
use App\Models\Membresia;
use App\Models\Usuario;
use App\Support\Invitaciones\InvitacionRegistrada;
use Database\Seeders\FuncionesGenericasSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

require_once __DIR__.'/helpers.php';

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(FuncionesGenericasSeeder::class);
    $this->a = newEstablecimiento('1000', 'Colegio A');
    $this->b = newEstablecimiento('2000', 'Colegio B');
});

/** Rows of the host tables an invitation writes. */
function filasDeInvitacion(): array
{
    return collect(['usuarios', 'personas', 'funcionarios', 'funcionario_funciones', 'membresias'])
        ->mapWithKeys(fn (string $tabla): array => [$tabla => DB::table($tabla)->count()])
        ->all();
}

it('invita a una persona nueva: usuario, persona, funcionario con su función y membresía invitada', function () {
    Mail::fake();
    $this->freezeSecond();
    $administrador = administradorDe($this->a);

    $membresia = invitar($administrador);

    $usuario = Usuario::query()->findOrFail($membresia->usuario_id);
    $persona = DB::table('personas')->find($membresia->persona_id);
    $funcionario = DB::table('funcionarios')->where('persona_id', $persona->id)->first();
    $clavesDeFunciones = DB::table('funcionario_funciones')
        ->join('funciones', 'funciones.id', '=', 'funcionario_funciones.funcion_id')
        ->where('funcionario_id', $funcionario->id)->pluck('funciones.clave')->all();

    expect($usuario->correo)->toBe('ana@ejemplo.cl')
        ->and(Hash::check('', $usuario->password))->toBeFalse()
        ->and($persona)->toMatchObject([
            'establecimiento_id' => $this->a, 'tipo_documento' => 'rut', 'numero_documento' => '12345678-5',
            'nombres' => 'Ana María', 'primer_apellido' => 'Pérez', 'segundo_apellido' => 'Soto',
            'correo' => 'ana@ejemplo.cl', 'telefono' => '+56 9 1234 5678',
        ])
        ->and($funcionario->fecha_ingreso)->toBeNull()
        ->and($funcionario->establecimiento_id)->toBe($this->a)
        ->and($clavesDeFunciones)->toBe(['docente'])
        ->and($membresia->establecimiento_id)->toBe($this->a)
        ->and($membresia->estado)->toBe('invitada')
        ->and($membresia->es_administrador)->toBeFalse()
        ->and($membresia->invitada_en->equalTo(now()))->toBeTrue()
        ->and($membresia->invitacion_expira_en->equalTo(now()->addDays(7)))->toBeTrue()
        ->and($membresia->invitacion_hash)->toMatch('/^[0-9a-f]{64}$/')
        ->and($membresia->activada_en)->toBeNull();

    Mail::assertSent(InvitacionAVinkured::class, fn (InvitacionAVinkured $correo): bool => $correo->hasTo('ana@ejemplo.cl'));
    Mail::assertSentCount(1);
});

it('asigna la función genérica de la categoría de la planilla', function (string $categoria) {
    Mail::fake();
    $membresia = invitar(administradorDe($this->a), filaParaInvitar(['categoriaFuncion' => $categoria]));

    $clave = DB::table('funcionario_funciones')
        ->join('funcionarios', 'funcionarios.id', '=', 'funcionario_funciones.funcionario_id')
        ->join('funciones', 'funciones.id', '=', 'funcionario_funciones.funcion_id')
        ->where('funcionarios.persona_id', $membresia->persona_id)
        ->value('funciones.clave');

    expect($clave)->toBe($categoria);
})->with(['docente', 'directivo', 'asistente', 'profesional_apoyo']);

it('reutiliza la persona del establecimiento sin sobrescribir sus datos', function () {
    Mail::fake();
    $personaId = newPersona($this->a, '12345678-5');
    $funcionarioId = DB::table('funcionarios')->insertGetId(['establecimiento_id' => $this->a, 'persona_id' => $personaId]);
    $docente = DB::table('funciones')->where('clave', 'docente')->value('id');
    asignarFuncion($this->a, $funcionarioId, $docente);
    $antes = DB::table('personas')->find($personaId);

    $membresia = invitar(administradorDe($this->a), filaParaInvitar(['nombres' => 'Otro Nombre', 'correo' => 'nuevo@ejemplo.cl']));

    expect($membresia->persona_id)->toBe($personaId)
        ->and(DB::table('personas')->find($personaId))->toEqual($antes)
        ->and(DB::table('funcionarios')->where('persona_id', $personaId)->count())->toBe(1)
        ->and(DB::table('funcionario_funciones')->where('funcionario_id', $funcionarioId)->count())->toBe(1);
});

it('agrega la función genérica al funcionario existente que no la tiene', function () {
    Mail::fake();
    $personaId = newPersona($this->a, '12345678-5');
    $funcionarioId = DB::table('funcionarios')->insertGetId(['establecimiento_id' => $this->a, 'persona_id' => $personaId]);
    asignarFuncion($this->a, $funcionarioId, DB::table('funciones')->where('clave', 'directivo')->value('id'));

    invitar(administradorDe($this->a));

    expect(DB::table('funcionario_funciones')->where('funcionario_id', $funcionarioId)->count())->toBe(2);
});

it('crea otra persona si el RUT solo existe en otro establecimiento', function () {
    Mail::fake();
    $personaDeB = newPersona($this->b, '12345678-5');

    $membresia = invitar(administradorDe($this->a));

    expect($membresia->persona_id)->not->toBe($personaDeB)
        ->and(DB::table('personas')->where('numero_documento', '12345678-5')->pluck('establecimiento_id')->sort()->values()->all())
        ->toBe([$this->a, $this->b]);
});

it('responde igual si el correo ya tiene cuenta en otro establecimiento', function () {
    Queue::fake();
    $existente = usuarioConMembresia($this->b, 'activa', ['correo' => 'existe@ejemplo.cl']);
    $administrador = administradorDe($this->a);

    $conCuenta = app(InvitarFuncionario::class)->invitar($administrador, filaParaInvitar(['correo' => 'existe@ejemplo.cl']));
    $sinCuenta = app(InvitarFuncionario::class)->invitar($administrador, filaParaInvitar(['rut' => otroRut(), 'correo' => 'nuevo@ejemplo.cl']));

    $forma = fn (object $objeto): array => array_map(gettype(...), get_object_vars($objeto));
    $trabajos = Queue::pushed(EnviarInvitacion::class);

    expect($conCuenta)->toBeInstanceOf(InvitacionRegistrada::class)
        ->and($sinCuenta)->toBeInstanceOf(InvitacionRegistrada::class)
        ->and($forma($conCuenta))->toBe($forma($sinCuenta))
        ->and($trabajos)->toHaveCount(2)
        ->and($forma($trabajos[0]))->toBe($forma($trabajos[1]))
        ->and(Membresia::query()->findOrFail($conCuenta->membresiaId)->only(['estado', 'es_administrador', 'activada_en']))
        ->toBe(Membresia::query()->findOrFail($sinCuenta->membresiaId)->only(['estado', 'es_administrador', 'activada_en']))
        ->and(Usuario::query()->where('correo', 'existe@ejemplo.cl')->count())->toBe(1)
        ->and(Membresia::query()->findOrFail($conCuenta->membresiaId)->usuario_id)->toBe($existente->id)
        ->and(Hash::check('password', $existente->fresh()->password))->toBeTrue();
});

it('envía el mismo correo tenga o no cuenta en otro establecimiento', function () {
    Mail::fake();
    usuarioConMembresia($this->b, 'activa', ['correo' => 'existe@ejemplo.cl']);
    $administrador = administradorDe($this->a);
    $texto = function (): string {
        $correo = ultimaInvitacion();

        return str_replace($correo->enlace, '{enlace}', $correo->render()).'|'.$correo->envelope()->subject;
    };

    invitar($administrador, filaParaInvitar(['correo' => 'existe@ejemplo.cl']));
    $conCuenta = $texto();
    invitar($administrador, filaParaInvitar(['rut' => otroRut(), 'correo' => 'nuevo@ejemplo.cl']));

    expect($texto())->toBe($conCuenta);
});

it('reutiliza la cuenta aunque el correo guardado tenga mayúsculas', function () {
    Mail::fake();
    $existente = usuarioConMembresia($this->b, 'activa', ['correo' => 'Ana@Ejemplo.CL']);

    $membresia = invitar(administradorDe($this->a));

    expect($membresia->usuario_id)->toBe($existente->id)
        ->and(Usuario::query()->whereRaw('lower(correo) = ?', ['ana@ejemplo.cl'])->count())->toBe(1);
});

it('rechaza conflictos dentro del establecimiento sin escribir nada', function (Closure $preparar, string $mensaje) {
    Mail::fake();
    $preparar($this->a);
    $administrador = administradorDe($this->a);
    $antes = filasDeInvitacion();

    expect(fn () => invitar($administrador))->toThrow(InvitacionEnConflicto::class, $mensaje)
        ->and(filasDeInvitacion())->toBe($antes);
    Mail::assertNothingSent();
})->with([
    'ya tiene acceso activo' => [function (int $est): void {
        newMembresia($est, newUsuario(['correo' => 'ana@ejemplo.cl'])->id, newPersona($est, '12345678-5'), ['estado' => 'activa']);
    }, 'Esta persona ya tiene acceso activo en este establecimiento.'],
    'ya está invitada' => [function (int $est): void {
        newMembresia($est, newUsuario(['correo' => 'ana@ejemplo.cl'])->id, newPersona($est, '12345678-5'), ['estado' => 'invitada']);
    }, 'Esta persona ya tiene una invitación en este establecimiento. Puede reenviarla o anularla.'],
    'está suspendida' => [function (int $est): void {
        newMembresia($est, newUsuario(['correo' => 'ana@ejemplo.cl'])->id, newPersona($est, '12345678-5'), ['estado' => 'suspendida']);
    }, 'Esta persona tiene su acceso suspendido en este establecimiento.'],
    'el RUT está ligado a otro correo' => [function (int $est): void {
        newMembresia($est, newUsuario(['correo' => 'otra@ejemplo.cl'])->id, newPersona($est, '12345678-5'), ['estado' => 'activa']);
    }, 'El RUT pertenece a una persona de este establecimiento que usa otro correo.'],
    'el correo está ligado a otra persona' => [function (int $est): void {
        newMembresia($est, newUsuario(['correo' => 'ana@ejemplo.cl'])->id, newPersona($est, '11111111-1'), ['estado' => 'activa']);
    }, 'El correo ya está asociado a otra persona de este establecimiento.'],
]);

it('no considera las membresías de otro establecimiento como conflicto', function () {
    Mail::fake();
    $usuario = newUsuario(['correo' => 'ana@ejemplo.cl']);
    newMembresia($this->b, $usuario->id, newPersona($this->b, '12345678-5'), ['estado' => 'activa']);

    $membresia = invitar(administradorDe($this->a));

    expect($membresia->usuario_id)->toBe($usuario->id)
        ->and($membresia->establecimiento_id)->toBe($this->a);
});

it('solo deja invitar al Administrador del establecimiento activo', function (Closure $membresia) {
    Mail::fake();
    $actuante = $membresia($this->a, $this->b);
    $antes = filasDeInvitacion();

    expect(fn () => invitar($actuante))->toThrow(AuthorizationException::class)
        ->and(filasDeInvitacion())->toBe($antes);
    Mail::assertNothingSent();
})->with([
    'sin rol de Administrador' => [function (int $a): Membresia {
        $usuario = usuarioConMembresia($a);
        activar($usuario, $a);

        return Membresia::query()->where('usuario_id', $usuario->id)->firstOrFail();
    }],
    'Administrador de otro establecimiento' => [function (int $a, int $b): Membresia {
        $deB = administradorDe($b);
        activar(usuarioConMembresia($a), $a);

        return $deB;
    }],
    'Administrador suspendido' => [function (int $a): Membresia {
        $administrador = administradorDe($a);
        DB::table('membresias')->where('id', $administrador->id)->update(['estado' => 'suspendida']);

        return $administrador;
    }],
]);

it('siembra las funciones genéricas de forma idempotente', function () {
    $this->seed(FuncionesGenericasSeeder::class);

    expect(DB::table('funciones')->orderBy('clave')->get(['clave', 'nombre', 'categoria'])->map(fn ($f) => (array) $f)->all())->toBe([
        ['clave' => 'asistente', 'nombre' => 'Asistente de la educación', 'categoria' => 'asistente'],
        ['clave' => 'directivo', 'nombre' => 'Directivo', 'categoria' => 'directivo'],
        ['clave' => 'docente', 'nombre' => 'Docente', 'categoria' => 'docente'],
        ['clave' => 'profesional_apoyo', 'nombre' => 'Profesional de apoyo', 'categoria' => 'profesional_apoyo'],
    ]);
});
