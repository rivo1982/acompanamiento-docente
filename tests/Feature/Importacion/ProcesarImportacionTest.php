<?php

use App\Actions\IniciarImportacion;
use App\Enums\EstadoImportacion;
use App\Exceptions\PlanillaIlegible;
use App\Jobs\EnviarInvitacion;
use App\Jobs\ProcesarImportacion;
use App\Models\ImportacionUsuario;
use App\Models\Membresia;
use App\Support\Importacion\LectorDePlanilla;
use App\Support\Importacion\ValidadorDePlanilla;
use Database\Seeders\FuncionesGenericasSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

require_once __DIR__.'/../Invitaciones/helpers.php';

uses(RefreshDatabase::class);

const ENCABEZADO_DE_IMPORTACION = ['RUT', 'Nombres', 'Primer apellido', 'Segundo apellido', 'Correo', 'Teléfono', 'Función'];

beforeEach(function () {
    $this->seed(FuncionesGenericasSeeder::class);
    Storage::fake('local');
    Queue::fake();
    Mail::fake();
    $this->a = newEstablecimiento('1000', 'Colegio A');
    $this->b = newEstablecimiento('2000', 'Colegio B');
    $this->temporal = sys_get_temp_dir().'/importaciones-'.bin2hex(random_bytes(6));
    mkdir($this->temporal);
});

afterEach(function () {
    array_map('unlink', glob($this->temporal.'/*') ?: []);
    rmdir($this->temporal);
});

/**
 * Uploaded .xlsx built at runtime with fake data; an empty array leaves a blank row.
 *
 * @param  list<array<int, string|int|null>>  $filas
 */
function planillaSubida(string $directorio, array $filas, string $nombre = 'funcionarios.xlsx'): UploadedFile
{
    $ruta = $directorio.'/'.bin2hex(random_bytes(4)).'.xlsx';
    $writer = new Writer;
    $writer->openToFile($ruta);
    foreach ($filas as $fila) {
        $writer->addRow(Row::fromValues($fila));
    }
    $writer->close();

    return new UploadedFile($ruta, $nombre, null, null, true);
}

/** @return list<string|null> */
function filaDeFuncionario(int $cuerpoDelRut, string $correo, string $funcion = 'Docente'): array
{
    return [rutDeInvitado($cuerpoDelRut)->formateado(), 'Nombre Ficticio', 'Apellido', null, $correo, null, $funcion];
}

function iniciarImportacion(UploadedFile|string $archivo, Membresia $administrador): ImportacionUsuario
{
    return app(IniciarImportacion::class)->iniciar($archivo, $administrador);
}

function trabajoDeImportacion(): ProcesarImportacion
{
    return Queue::pushed(ProcesarImportacion::class)->last();
}

function procesar(ProcesarImportacion $trabajo): void
{
    app()->call([$trabajo, 'handle']);
}

/** Stored spreadsheets left on the fake disk. */
function planillasGuardadas(): array
{
    return Storage::disk('local')->allFiles('importaciones');
}

it('guarda la planilla con un nombre aleatorio .xlsx, registra solo el nombre original y encola el proceso con ids', function () {
    $administrador = administradorDe($this->a);

    $importacion = iniciarImportacion(planillaSubida($this->temporal, [ENCABEZADO_DE_IMPORTACION], '../carpeta/Funcionarios marzo.xlsx'), $administrador);

    $guardadas = planillasGuardadas();
    $trabajo = trabajoDeImportacion();
    $datos = collect((new ReflectionClass($trabajo))->getConstructor()->getParameters())->map->getName()->all();
    expect($guardadas)->toHaveCount(1)
        ->and($guardadas[0])->toMatch('#^importaciones/[0-9a-f-]{36}\.xlsx$#')
        ->and($importacion->fresh())->toMatchArray([
            'establecimiento_id' => $this->a,
            'membresia_id' => $administrador->id,
            'archivo' => 'Funcionarios marzo.xlsx',
            'total' => 0, 'creados' => 0, 'con_error' => 0, 'errores' => null,
        ])
        ->and($importacion->fresh()->estado)->toBe(EstadoImportacion::Pendiente)
        ->and($datos)->toBe(['establecimientoId', 'importacionId', 'archivoId'])
        ->and($trabajo->establecimientoId)->toBe($this->a)
        ->and($trabajo->importacionId)->toBe($importacion->id)
        ->and('importaciones/'.$trabajo->archivoId.'.xlsx')->toBe($guardadas[0])
        ->and($trabajo)->toBeInstanceOf(ShouldQueueAfterCommit::class)
        ->and($trabajo)->toBeInstanceOf(ShouldBeUnique::class)
        ->and($trabajo->uniqueId())->toBe("{$this->a}:{$importacion->id}");
});

it('acepta la ruta de un archivo local y registra solo su nombre', function () {
    $ruta = planillaSubida($this->temporal, [ENCABEZADO_DE_IMPORTACION])->getPathname();

    $importacion = iniciarImportacion($ruta, administradorDe($this->a));

    expect($importacion->archivo)->toBe(basename($ruta))
        ->and(planillasGuardadas())->toHaveCount(1);
});

it('solo deja iniciar una carga al Administrador del establecimiento activo', function () {
    $usuario = usuarioConMembresia($this->a);
    activar($usuario, $this->a);
    $sinRol = Membresia::query()->where('usuario_id', $usuario->id)->firstOrFail();

    expect(fn () => iniciarImportacion(planillaSubida($this->temporal, [ENCABEZADO_DE_IMPORTACION]), $sinRol))
        ->toThrow(AuthorizationException::class)
        ->and(ImportacionUsuario::sinEstablecimiento()->count())->toBe(0)
        ->and(planillasGuardadas())->toBe([]);
    Queue::assertNothingPushed();
});

it('deja la carga fallida y borra la planilla si su proceso no se pudo encolar', function () {
    $administrador = administradorDe($this->a);
    $subida = planillaSubida($this->temporal, [ENCABEZADO_DE_IMPORTACION]);
    Bus::shouldReceive('dispatch')->andThrow(new RuntimeException('cola caída'));

    $importacion = iniciarImportacion($subida, $administrador);

    expect($importacion->fresh())
        ->estado->toBe(EstadoImportacion::Fallida)
        ->errores->toBe([['fila' => null, 'mensajes' => ['No se pudo programar el proceso de la planilla. Súbala nuevamente.']]])
        ->and(planillasGuardadas())->toBe([]);
});

it('procesa una planilla mixta: invita las filas válidas y resume los errores por fila', function () {
    $administrador = administradorDe($this->a);
    newMembresia($this->a, newUsuario(['correo' => 'activa@example.com'])->id, newPersona($this->a, rutDeInvitado(30000001)->paraAlmacenar()), ['estado' => 'activa']);
    newMembresia($this->a, newUsuario(['correo' => 'pendiente@example.com'])->id, newPersona($this->a, rutDeInvitado(30000002)->paraAlmacenar()), ['estado' => 'invitada']);
    $deOtroColegio = newUsuario(['correo' => 'otro.colegio@example.com']);
    newMembresia($this->b, $deOtroColegio->id, null, ['estado' => 'activa']);

    iniciarImportacion(planillaSubida($this->temporal, [
        ENCABEZADO_DE_IMPORTACION,
        filaDeFuncionario(30000010, 'nuevo.uno@example.com'),
        ['12.345.678-0', 'Nombre Ficticio', 'Apellido', null, 'rut.malo@example.com', null, 'Docente'],
        filaDeFuncionario(30000010, 'repetido@example.com'),
        filaDeFuncionario(30000001, 'activa@example.com'),
        filaDeFuncionario(30000002, 'pendiente@example.com'),
        filaDeFuncionario(30000011, 'otro.colegio@example.com', 'Asistente'),
        [],
        filaDeFuncionario(30000012, 'nuevo.dos@example.com', 'Directivo'),
    ]), $administrador);
    Queue::assertPushed(EnviarInvitacion::class, 0);

    procesar(trabajoDeImportacion());

    $importacion = ImportacionUsuario::query()->sole();
    expect($importacion->estado)->toBe(EstadoImportacion::Completada)
        ->and($importacion->total)->toBe(7)
        ->and($importacion->creados)->toBe(3)
        ->and($importacion->con_error)->toBe(4)
        ->and($importacion->errores)->toBe([
            ['fila' => 3, 'mensajes' => ['El dígito verificador del RUT no corresponde.']],
            ['fila' => 4, 'mensajes' => ['El RUT ya aparece en la fila 2.']],
            ['fila' => 5, 'mensajes' => ['Esta persona ya tiene acceso activo en este establecimiento.']],
            ['fila' => 6, 'mensajes' => ['Esta persona ya tiene una invitación pendiente en este establecimiento. Puede reenviarla o anularla.']],
        ]);

    $invitadas = Membresia::query()->where('establecimiento_id', $this->a)->where('estado', 'invitada')
        ->whereHas('usuario', fn ($consulta) => $consulta->whereIn('correo', ['nuevo.uno@example.com', 'otro.colegio@example.com', 'nuevo.dos@example.com']))
        ->pluck('id')->sort()->values()->all();
    expect($invitadas)->toHaveCount(3)
        ->and(Queue::pushed(EnviarInvitacion::class)->map->membresiaId->sort()->values()->all())->toBe($invitadas)
        ->and(Membresia::query()->where('usuario_id', $deOtroColegio->id)->where('establecimiento_id', $this->a)->exists())->toBeTrue()
        ->and(planillasGuardadas())->toBe([]);
    Mail::assertNothingSent();
});

it('cuenta igual un correo con cuenta en otro establecimiento que uno nuevo', function () {
    $administrador = administradorDe($this->a);
    newMembresia($this->b, newUsuario(['correo' => 'existe@example.com'])->id, null, ['estado' => 'activa']);
    $resumen = function (string $correo, int $cuerpoDelRut) use ($administrador): array {
        iniciarImportacion(planillaSubida($this->temporal, [ENCABEZADO_DE_IMPORTACION, filaDeFuncionario($cuerpoDelRut, $correo)]), $administrador);
        procesar(trabajoDeImportacion());

        return ImportacionUsuario::query()->latest('id')->firstOrFail()
            ->only(['estado', 'total', 'creados', 'con_error', 'errores']);
    };

    expect($resumen('existe@example.com', 30000020))->toBe($resumen('nadie@example.com', 30000021))
        ->and($resumen('otro.nadie@example.com', 30000022))->toMatchArray(['creados' => 1, 'con_error' => 0, 'errores' => []]);
});

it('falla la carga completa si el encabezado no corresponde, sin invitar a nadie y borrando la planilla', function () {
    $administrador = administradorDe($this->a);
    $antes = Membresia::query()->count();
    iniciarImportacion(planillaSubida($this->temporal, [
        ['Rut', 'Nombre', 'Correo'],
        filaDeFuncionario(30000030, 'nuevo@example.com'),
    ]), $administrador);

    procesar(trabajoDeImportacion());

    $importacion = ImportacionUsuario::query()->sole();
    expect($importacion->estado)->toBe(EstadoImportacion::Fallida)
        ->and($importacion->only(['total', 'creados', 'con_error']))->toBe(['total' => 0, 'creados' => 0, 'con_error' => 0])
        ->and($importacion->errores)->toBe([['fila' => null, 'mensajes' => [ValidadorDePlanilla::mensajeDeEncabezado()]]])
        ->and(Membresia::query()->count())->toBe($antes)
        ->and(planillasGuardadas())->toBe([]);
    Queue::assertPushed(EnviarInvitacion::class, 0);
});

it('falla la carga si el archivo no es una planilla legible y la borra igual', function () {
    $falso = UploadedFile::fake()->createWithContent('funcionarios.xlsx', 'esto no es una planilla');
    iniciarImportacion($falso, administradorDe($this->a));

    procesar(trabajoDeImportacion());

    expect(ImportacionUsuario::query()->sole())
        ->estado->toBe(EstadoImportacion::Fallida)
        ->errores->toBe([['fila' => null, 'mensajes' => ['No se pudo leer la planilla. Guárdela de nuevo como .xlsx desde Excel e inténtelo otra vez.']]])
        ->and(planillasGuardadas())->toBe([]);
});

it('una excepción inesperada en una fila deja un error genérico, registra solo ids y sigue con las demás', function () {
    Log::spy();
    $administrador = administradorDe($this->a);
    iniciarImportacion(planillaSubida($this->temporal, [
        ENCABEZADO_DE_IMPORTACION,
        filaDeFuncionario(30000040, 'roto@example.com', 'Directivo'),
        filaDeFuncionario(30000041, 'sano@example.com'),
    ]), $administrador);
    DB::table('funciones')->where('clave', 'directivo')->delete();
    $trabajo = trabajoDeImportacion();

    procesar($trabajo);

    $importacion = ImportacionUsuario::query()->sole();
    expect($importacion->estado)->toBe(EstadoImportacion::Completada)
        ->and($importacion->only(['total', 'creados', 'con_error']))->toBe(['total' => 2, 'creados' => 1, 'con_error' => 1])
        ->and($importacion->errores)->toBe([['fila' => 2, 'mensajes' => ['No se pudo procesar esta fila. Inténtelo nuevamente más tarde.']]])
        ->and(planillasGuardadas())->toBe([]);
    Log::shouldHaveReceived('error')->once()->with('Una fila de una carga masiva falló de forma inesperada.', [
        'establecimiento_id' => $this->a,
        'importacion_id' => $importacion->id,
        'fila' => 2,
        'excepcion' => LogicException::class,
    ]);
});

it('cuenta como error la fila cuya invitación quedó registrada pero no se pudo encolar', function () {
    $administrador = administradorDe($this->a);
    iniciarImportacion(planillaSubida($this->temporal, [ENCABEZADO_DE_IMPORTACION, filaDeFuncionario(30000050, 'cola@example.com')]), $administrador);
    $trabajo = trabajoDeImportacion();
    Bus::shouldReceive('dispatch')->andThrow(new RuntimeException('cola caída'));

    procesar($trabajo);

    expect(ImportacionUsuario::query()->sole())
        ->creados->toBe(0)
        ->con_error->toBe(1)
        ->errores->toBe([['fila' => 2, 'mensajes' => ['La invitación quedó registrada, pero su correo no pudo programarse. Reenvíela más tarde.']]]);
});

it('volver a procesar una carga terminada no hace nada', function () {
    $administrador = administradorDe($this->a);
    iniciarImportacion(planillaSubida($this->temporal, [ENCABEZADO_DE_IMPORTACION, filaDeFuncionario(30000060, 'una.vez@example.com')]), $administrador);
    $trabajo = trabajoDeImportacion();
    procesar($trabajo);
    $despues = ImportacionUsuario::query()->sole()->getAttributes();

    procesar($trabajo);

    expect(ImportacionUsuario::query()->sole()->getAttributes())->toBe($despues);
    Queue::assertPushed(EnviarInvitacion::class, 1);
});

it('una carga fallida tampoco se vuelve a procesar', function () {
    iniciarImportacion(planillaSubida($this->temporal, [['Otra cosa']]), administradorDe($this->a));
    $trabajo = trabajoDeImportacion();
    procesar($trabajo);
    $despues = ImportacionUsuario::query()->sole()->getAttributes();

    procesar($trabajo);

    expect(ImportacionUsuario::query()->sole()->getAttributes())->toBe($despues);
});

it('procesa en el establecimiento de la carga aunque la sesión tenga otro activo', function () {
    iniciarImportacion(planillaSubida($this->temporal, [ENCABEZADO_DE_IMPORTACION, filaDeFuncionario(30000070, 'en.a@example.com')]), administradorDe($this->a));
    $trabajo = trabajoDeImportacion();
    administradorDe($this->b);

    procesar($trabajo);

    expect(DB::table('membresias')->join('usuarios', 'usuarios.id', '=', 'membresias.usuario_id')
        ->where('usuarios.correo', 'en.a@example.com')->pluck('membresias.establecimiento_id')->all())->toBe([$this->a]);
});

it('un trabajo con el id de una carga de otro establecimiento no la toca', function () {
    iniciarImportacion(planillaSubida($this->temporal, [ENCABEZADO_DE_IMPORTACION, filaDeFuncionario(30000080, 'ajeno@example.com')]), administradorDe($this->a));
    $deA = trabajoDeImportacion();
    $antes = Membresia::query()->count();

    procesar(new ProcesarImportacion($this->b, $deA->importacionId, $deA->archivoId));

    expect(ImportacionUsuario::sinEstablecimiento()->sole()->estado)->toBe(EstadoImportacion::Pendiente)
        ->and(Membresia::query()->count())->toBe($antes)
        ->and(planillasGuardadas())->toHaveCount(1);
});

it('falla la carga si quien la subió dejó de ser Administrador antes del proceso', function () {
    $administrador = administradorDe($this->a);
    iniciarImportacion(planillaSubida($this->temporal, [ENCABEZADO_DE_IMPORTACION, filaDeFuncionario(30000090, 'tarde@example.com')]), $administrador);
    DB::table('membresias')->where('id', $administrador->id)->update(['es_administrador' => false]);

    procesar(trabajoDeImportacion());

    expect(ImportacionUsuario::query()->sole())
        ->estado->toBe(EstadoImportacion::Fallida)
        ->errores->toBe([['fila' => null, 'mensajes' => ['Quien subió la planilla ya no es Administrador de este establecimiento.']]])
        ->and(planillasGuardadas())->toBe([]);
    Queue::assertPushed(EnviarInvitacion::class, 0);
});

it('el fallo definitivo del trabajo marca la carga como fallida, borra la planilla y registra solo ids', function () {
    Log::spy();
    iniciarImportacion(planillaSubida($this->temporal, [ENCABEZADO_DE_IMPORTACION]), administradorDe($this->a));
    $trabajo = trabajoDeImportacion();

    $trabajo->failed(new RuntimeException('detalle con nuevo@example.com'));

    expect(ImportacionUsuario::query()->sole())
        ->estado->toBe(EstadoImportacion::Fallida)
        ->errores->toBe([['fila' => null, 'mensajes' => ['No se pudo terminar de procesar la planilla. Súbala nuevamente.']]])
        ->and(planillasGuardadas())->toBe([]);
    Log::shouldHaveReceived('error')->once()->with('Una carga masiva de funcionarios falló definitivamente.', [
        'establecimiento_id' => $this->a,
        'importacion_id' => $trabajo->importacionId,
        'excepcion' => RuntimeException::class,
    ]);
});

it('no pisa con completada una carga que otro intento ya dejó fallida mientras procesaba', function () {
    iniciarImportacion(planillaSubida($this->temporal, [ENCABEZADO_DE_IMPORTACION, filaDeFuncionario(30000100, 'tardio@example.com')]), administradorDe($this->a));
    $trabajo = trabajoDeImportacion();
    $mensajeDelOtroIntento = [['fila' => null, 'mensajes' => ['No se pudo terminar de procesar la planilla. Súbala nuevamente.']]];
    Membresia::created(function () use ($trabajo, $mensajeDelOtroIntento): void {
        DB::table('importaciones_usuarios')->where('id', $trabajo->importacionId)->update([
            'estado' => EstadoImportacion::Fallida->value,
            'errores' => json_encode($mensajeDelOtroIntento),
        ]);
    });

    procesar($trabajo);

    expect(ImportacionUsuario::query()->sole())
        ->estado->toBe(EstadoImportacion::Fallida)
        ->errores->toBe($mensajeDelOtroIntento)
        ->creados->toBe(0)
        ->and(planillasGuardadas())->toBe([]);
});

it('no pisa con fallida una carga que otro intento ya dejó completada mientras procesaba', function () {
    iniciarImportacion(planillaSubida($this->temporal, [ENCABEZADO_DE_IMPORTACION]), administradorDe($this->a));
    $trabajo = trabajoDeImportacion();
    app()->instance(LectorDePlanilla::class, new class($trabajo->importacionId) implements LectorDePlanilla
    {
        public function __construct(private int $importacionId) {}

        public function filas(string $ruta): iterable
        {
            DB::table('importaciones_usuarios')->where('id', $this->importacionId)->update([
                'estado' => EstadoImportacion::Completada->value,
                'total' => 3,
                'creados' => 3,
            ]);

            throw PlanillaIlegible::noSePuedeLeer();
        }
    });

    procesar($trabajo);

    expect(ImportacionUsuario::query()->sole())
        ->estado->toBe(EstadoImportacion::Completada)
        ->creados->toBe(3)
        ->errores->toBeNull();
});
