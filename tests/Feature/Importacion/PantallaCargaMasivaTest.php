<?php

use App\Enums\EstadoImportacion;
use App\Jobs\ProcesarImportacion;
use App\Livewire\Importaciones\CargaMasiva;
use App\Models\ImportacionUsuario;
use App\Support\Importacion\LectorXlsx;
use App\Support\Importacion\ValidadorDePlanilla;
use Database\Seeders\FuncionesGenericasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

require_once __DIR__.'/../Invitaciones/helpers.php';
require_once __DIR__.'/helpers.php';

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(FuncionesGenericasSeeder::class);
    Storage::fake('local');
    Storage::fake('tmp-for-tests');
    Queue::fake();
    $this->a = newEstablecimiento('1000', 'Colegio Ficticio A');
    $this->b = newEstablecimiento('2000', 'Colegio Ficticio B');
    $this->temporal = sys_get_temp_dir().'/pantalla-importaciones-'.bin2hex(random_bytes(6));
    mkdir($this->temporal);
});

afterEach(function () {
    array_map('unlink', glob($this->temporal.'/*') ?: []);
    rmdir($this->temporal);
    Carbon::setTestNow();
});

/** Fake upload whose content is a real .xlsx with the expected header and one fictitious row. */
function planillaParaPantalla(string $directorio, string $nombre = 'funcionarios.xlsx'): UploadedFile
{
    $ruta = $directorio.'/'.bin2hex(random_bytes(4)).'.xlsx';
    $writer = new Writer;
    $writer->openToFile($ruta);
    $writer->addRow(Row::fromValues(ValidadorDePlanilla::ENCABEZADO));
    $writer->addRow(Row::fromValues([rutDeInvitado(12345678)->formateado(), 'Nombre Ficticio', 'Apellido', null, 'ficticio@example.test', null, 'Docente']));
    $writer->close();

    return UploadedFile::fake()->createWithContent($nombre, (string) file_get_contents($ruta));
}

/** @return list<string> */
function archivosTemporalesDeLivewire(): array
{
    return Storage::disk('tmp-for-tests')->allFiles();
}

it('shows the Administrador the upload form with the expected columns, accepted functions and the template link', function () {
    administradorDe($this->a);

    $respuesta = $this->get(route('importaciones.index'));

    $respuesta->assertOk()
        ->assertSee('<title>Carga masiva - VINKURED</title>', false)
        ->assertSeeInOrder(ValidadorDePlanilla::ENCABEZADO)
        ->assertSee('docente, directivo, asistente o profesional de apoyo')
        ->assertSee(route('importaciones.plantilla'), false)
        ->assertSee(route('invitaciones.index'), false)
        ->assertSee('accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"', false)
        ->assertSee('aria-describedby="archivo-ayuda"', false)
        ->assertSee("Archivo Excel (.xlsx) de hasta 2\u{00A0}MB");
    expect(substr_count($respuesta->getContent(), '<h1'))->toBe(1)
        ->and($respuesta->getContent())->toMatch('/<p id="archivo-carga" role="status"[^>]*>\s*<span wire:loading wire:target="archivo">Cargando archivo…<\/span>/');
});

it('announces in the upload status region that the chosen file is ready, once the upload ends', function () {
    administradorDe($this->a);

    $html = Livewire::test(CargaMasiva::class)
        ->set('archivo', planillaParaPantalla($this->temporal, 'Planilla de prueba.xlsx'))
        ->html();

    expect($html)->toMatch('/<p id="archivo-carga" role="status"[^>]*>.*Archivo listo para subir: Planilla de prueba\.xlsx\..*<\/p>/s')
        ->and(substr_count($html, 'role="status"'))->toBe(1);
});

it('links the bulk upload to the pending invitations', function () {
    administradorDe($this->a);

    $this->get(route('importaciones.index'))->assertOk()->assertSee(route('invitaciones.index'), false);
});

it('is forbidden to members who are not the Administrador', function () {
    [$usuario] = usuarioConRoles($this->a);
    activar($usuario, $this->a);

    $this->get(route('importaciones.index'))->assertForbidden();
    $this->get(route('importaciones.plantilla'))->assertForbidden();
    Livewire::test(CargaMasiva::class)->assertForbidden();
});

it('sends a guest to the login', function () {
    $this->get(route('importaciones.index'))->assertRedirect(route('login'));
});

it('starts the import from a valid .xlsx: pending record, job queued, redirect to the result, no temporary copy left', function () {
    $admin = administradorDe($this->a);

    $componente = Livewire::test(CargaMasiva::class)
        ->set('archivo', planillaParaPantalla($this->temporal, 'Planilla de prueba.xlsx'))
        ->call('subir')
        ->assertHasNoErrors();

    $importacion = ImportacionUsuario::query()->sole();
    expect($importacion->estado)->toBe(EstadoImportacion::Pendiente)
        ->and($importacion->archivo)->toBe('Planilla de prueba.xlsx')
        ->and($importacion->membresia_id)->toBe($admin->id)
        ->and(Storage::disk('local')->files('importaciones'))->toHaveCount(1)
        ->and(archivosTemporalesDeLivewire())->toBe([]);
    $componente->assertRedirect(route('importaciones.show', $importacion->id));
    Queue::assertPushed(ProcesarImportacion::class, fn (ProcesarImportacion $trabajo): bool => $trabajo->importacionId === $importacion->id);
});

it('rejects a file that is not an .xlsx and deletes the temporary copy', function () {
    administradorDe($this->a);

    Livewire::test(CargaMasiva::class)
        ->set('archivo', UploadedFile::fake()->createWithContent('funcionarios.csv', "RUT;Nombres\n"))
        ->call('subir')
        ->assertHasErrors(['archivo'])
        ->assertSee('El archivo debe ser una planilla Excel (.xlsx).')
        ->assertSee('aria-invalid="true"', false);

    expect(ImportacionUsuario::sinEstablecimiento()->count())->toBe(0)
        ->and(archivosTemporalesDeLivewire())->toBe([]);
    Queue::assertNothingPushed();
});

it('accepts a real .xlsx even when the server detects it as a plain ZIP', function (string $mime) {
    administradorDe($this->a);

    Livewire::test(CargaMasiva::class)
        ->set('archivo', planillaParaPantalla($this->temporal)->mimeType($mime))
        ->call('subir')
        ->assertHasNoErrors();

    expect(ImportacionUsuario::query()->count())->toBe(1);
})->with(['application/zip', 'application/octet-stream']);

it('rejects a renamed text file even if it claims the spreadsheet MIME type', function () {
    administradorDe($this->a);

    Livewire::test(CargaMasiva::class)
        ->set('archivo', UploadedFile::fake()->createWithContent('funcionarios.xlsx', 'texto plano, no una planilla')
            ->mimeType('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'))
        ->call('subir')
        ->assertHasErrors(['archivo'])
        ->assertSee('El archivo debe ser una planilla Excel (.xlsx).');

    expect(ImportacionUsuario::sinEstablecimiento()->count())->toBe(0);
});

it('rejects a renamed file whose content is not a spreadsheet', function () {
    administradorDe($this->a);

    Livewire::test(CargaMasiva::class)
        // Livewire's test uploads report the fake's MIME type: a renamed text file reports text/plain, as finfo would.
        ->set('archivo', UploadedFile::fake()->createWithContent('funcionarios.xlsx', 'texto plano, no una planilla')->mimeType('text/plain'))
        ->call('subir')
        ->assertHasErrors(['archivo'])
        ->assertSee('El archivo debe ser una planilla Excel (.xlsx).');

    expect(ImportacionUsuario::sinEstablecimiento()->count())->toBe(0);
});

it('rejects a file larger than the configured maximum', function () {
    administradorDe($this->a);

    Livewire::test(CargaMasiva::class)
        ->set('archivo', UploadedFile::fake()->create('grande.xlsx', 2049))
        ->call('subir')
        ->assertHasErrors(['archivo'])
        ->assertSee("La planilla no puede superar los 2\u{00A0}MB.");

    expect(ImportacionUsuario::sinEstablecimiento()->count())->toBe(0)
        ->and(archivosTemporalesDeLivewire())->toBe([]);
});

it('asks for a file when none was chosen', function () {
    administradorDe($this->a);

    Livewire::test(CargaMasiva::class)
        ->call('subir')
        ->assertHasErrors(['archivo'])
        ->assertSee('Elegir la planilla .xlsx que se va a subir.');
});

it('re-checks the Administrador when uploading', function () {
    $admin = administradorDe($this->a);
    $componente = Livewire::test(CargaMasiva::class)->set('archivo', planillaParaPantalla($this->temporal));
    $admin->forceFill(['es_administrador' => false])->save();

    $componente->call('subir')->assertForbidden();

    expect(ImportacionUsuario::sinEstablecimiento()->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('lists the recent imports of this establecimiento only, newest first, with date, state and a link to the result', function () {
    $admin = administradorDe($this->a);
    importacionDePrueba(administradorDe($this->b), ['archivo' => 'ajena.xlsx']);
    activar($admin->usuario, $this->a);
    $vieja = importacionDePrueba($admin, ['archivo' => 'vieja.xlsx', 'estado' => 'completada', 'total' => 3, 'creados' => 2, 'con_error' => 1, 'created_at' => '2026-10-01 09:00:00']);
    $nueva = importacionDePrueba($admin, ['archivo' => 'nueva.xlsx', 'estado' => 'fallida', 'created_at' => '2026-10-20 15:30:00']);

    $respuesta = $this->get(route('importaciones.index'));

    $respuesta->assertOk()
        ->assertSeeInOrder(['Archivo', 'Subida el', 'Estado', 'Resultado'])
        ->assertSeeInOrder(['nueva.xlsx', '20-10-2026 15:30', 'Fallida', 'No se completó', 'vieja.xlsx', '01-10-2026 09:00', 'Completada', '2 de 3 invitadas'])
        ->assertSee(route('importaciones.show', $nueva), false)
        ->assertSee(route('importaciones.show', $vieja), false)
        ->assertSee('Cargas masivas recientes (2)')
        ->assertDontSee('ajena.xlsx');
});

it('shows each state as a badge with the decided variant', function (string $estado, string $texto, string $fondo) {
    importacionDePrueba(administradorDe($this->a), ['estado' => $estado]);

    $html = $this->get(route('importaciones.index'))->assertOk()->getContent();

    expect($html)->toMatch('/<span class="[^"]*\\b'.$fondo.'\\b[^"]*">'.$texto.'<\/span>/');
})->with([
    ['pendiente', 'Pendiente', 'bg-neutro-fondo'],
    ['procesando', 'Procesando', 'bg-informacion-fondo'],
    ['completada', 'Completada', 'bg-exito-fondo'],
    ['fallida', 'Fallida', 'bg-aviso-fondo'],
]);

it('says there is no result yet for an unfinished import and points to its result page for progress', function (string $estado) {
    importacionDePrueba(administradorDe($this->a), ['estado' => $estado]);

    $this->get(route('importaciones.index'))->assertOk()
        ->assertSeeInOrder(['Sin resultado aún', 'Abrir el resultado para ver el avance.'])
        ->assertDontSee('No se completó');
})->with(['pendiente', 'procesando']);

it('does not show the progress hint for finished imports', function () {
    importacionDePrueba(administradorDe($this->a), ['estado' => 'completada', 'total' => 1, 'creados' => 1]);

    $this->get(route('importaciones.index'))->assertOk()
        ->assertSee('1 de 1 invitadas')
        ->assertDontSee('Abrir el resultado para ver el avance.');
});

it('shows 20 imports per page with the host pagination', function () {
    $admin = administradorDe($this->a);
    foreach (range(1, 23) as $n) {
        importacionDePrueba($admin, ['archivo' => 'planilla-'.str_pad((string) $n, 2, '0', STR_PAD_LEFT).'.xlsx', 'created_at' => Carbon::parse('2026-10-01 10:00:00')->addMinutes($n)]);
    }

    Livewire::test(CargaMasiva::class)
        ->assertSee('Cargas masivas recientes (23)')
        ->assertSee('Página 1 de 2')
        ->assertSee('planilla-23.xlsx')
        ->assertSee('planilla-04.xlsx')
        ->assertDontSee('planilla-03.xlsx')
        ->call('nextPage')
        ->assertSee('Página 2 de 2')
        ->assertSee('planilla-03.xlsx')
        ->assertSee('planilla-01.xlsx')
        ->assertDontSee('planilla-04.xlsx');
});

it('shows one sentence when there are no imports yet', function () {
    administradorDe($this->a);

    Livewire::test(CargaMasiva::class)
        ->assertSee('Aún no hay cargas masivas en este establecimiento.')
        ->assertDontSee('Página 1');
});

it('downloads a template .xlsx with only the expected header row', function () {
    administradorDe($this->a);

    $respuesta = $this->get(route('importaciones.plantilla'));

    $respuesta->assertOk()
        ->assertDownload('plantilla-carga-masiva.xlsx')
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $ruta = $this->temporal.'/plantilla.xlsx';
    file_put_contents($ruta, $respuesta->streamedContent());
    $filas = iterator_to_array((new LectorXlsx)->filas($ruta), false);
    expect($filas)->toBe([ValidadorDePlanilla::ENCABEZADO]);
});
