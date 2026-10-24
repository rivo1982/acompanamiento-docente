<?php

use App\Exceptions\PlanillaIlegible;
use App\Support\Importacion\LectorDePlanilla;
use App\Support\Importacion\LectorXlsx;
use App\Support\Importacion\ValidadorDePlanilla;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

beforeEach(function () {
    $this->directorio = sys_get_temp_dir().'/planillas-'.bin2hex(random_bytes(6));
    mkdir($this->directorio);
});

afterEach(function () {
    array_map('unlink', glob($this->directorio.'/*') ?: []);
    rmdir($this->directorio);
});

/**
 * Escribe una planilla .xlsx con las filas dadas en la primera hoja; un arreglo vacío deja la fila en blanco.
 *
 * @param  list<array<int, string|int|float|null>>  $filas
 */
function escribirXlsx(string $ruta, array $filas): string
{
    $writer = new Writer;
    $writer->openToFile($ruta);
    foreach ($filas as $fila) {
        $writer->addRow(Row::fromValues($fila));
    }
    $writer->close();

    return $ruta;
}

const ENCABEZADO_XLSX = ['RUT', 'Nombres', 'Primer apellido', 'Segundo apellido', 'Correo', 'Teléfono', 'Función'];

it('resuelve el lector de planillas desde el contenedor', function () {
    expect(app(LectorDePlanilla::class))->toBeInstanceOf(LectorXlsx::class);
});

it('lee y valida una planilla de punta a punta', function () {
    $ruta = escribirXlsx($this->directorio.'/funcionarios.xlsx', [
        ENCABEZADO_XLSX,
        [123456785, 'Ana', 'Pérez', null, 'ana@ejemplo.cl', null, 'Docente'],
        [],
        ['11.111.111-1', 'Luis', 'Rojas', 'Díaz', 'luis@ejemplo.cl', '+56 2 2345 6789', 'Profesional de apoyo'],
        ['12345678-5', 'Eva', 'Mora', null, 'ANA@ejemplo.cl', null, 'Asistente'],
        ['7.654.321-6', 'Sol', 'Vera', null, 'sol@ejemplo.cl', null, 'portero'],
    ]);

    $resultado = (new ValidadorDePlanilla)->validar(app(LectorDePlanilla::class)->filas($ruta));

    expect($resultado->erroresDelArchivo)->toBe([])
        ->and(array_map(fn ($fila) => [$fila->numeroDeFila, $fila->rut->paraAlmacenar(), $fila->categoriaFuncion], $resultado->filasValidas))
        ->toBe([[2, '12345678-5', 'docente'], [4, '11111111-1', 'profesional_apoyo']])
        ->and($resultado->erroresPorFila)->toBe([
            5 => ['El RUT ya aparece en la fila 2.', 'El correo ya aparece en la fila 2.'],
            6 => ['La función debe ser docente, directivo, asistente o profesional de apoyo.'],
        ]);
});

it('entrega las filas numeradas como en Excel, incluidas las vacías', function () {
    $ruta = escribirXlsx($this->directorio.'/numeracion.xlsx', [ENCABEZADO_XLSX, [], ['a']]);

    $filas = iterator_to_array((new LectorXlsx)->filas($ruta));

    expect(array_keys($filas))->toBe([1, 2, 3])
        ->and($filas[3])->toBe(['a']);
});

it('rechaza una planilla con el encabezado equivocado', function () {
    $ruta = escribirXlsx($this->directorio.'/encabezado.xlsx', [
        ['Rut', 'Nombre completo', 'Correo'],
        ['12.345.678-5', 'Ana Pérez', 'ana@ejemplo.cl'],
    ]);

    $resultado = (new ValidadorDePlanilla)->validar((new LectorXlsx)->filas($ruta));

    expect($resultado->erroresDelArchivo)->toHaveCount(1)
        ->and($resultado->erroresDelArchivo[0])->toStartWith('El encabezado de la planilla no corresponde.');
});

it('rechaza un archivo que no es .xlsx', function () {
    $ruta = $this->directorio.'/funcionarios.csv';
    file_put_contents($ruta, "RUT,Nombres\n12.345.678-5,Ana\n");

    iterator_to_array((new LectorXlsx)->filas($ruta));
})->throws(PlanillaIlegible::class, 'El archivo debe ser una planilla Excel (.xlsx).');

it('rechaza un archivo con extensión .xlsx que no es una planilla', function () {
    $ruta = $this->directorio.'/falso.xlsx';
    file_put_contents($ruta, 'esto no es una planilla');

    iterator_to_array((new LectorXlsx)->filas($ruta));
})->throws(PlanillaIlegible::class, 'No se pudo leer la planilla. Guardarla de nuevo como .xlsx desde Excel y volver a subirla.');

it('rechaza un .xlsx dañado', function () {
    $ruta = $this->directorio.'/danado.xlsx';
    file_put_contents($ruta, "PK\x03\x04".str_repeat('x', 200));

    iterator_to_array((new LectorXlsx)->filas($ruta));
})->throws(PlanillaIlegible::class, 'No se pudo leer la planilla.');

it('rechaza una planilla protegida con contraseña', function () {
    $ruta = $this->directorio.'/protegida.xlsx';
    file_put_contents($ruta, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1".str_repeat("\0", 504));

    iterator_to_array((new LectorXlsx)->filas($ruta));
})->throws(PlanillaIlegible::class, 'La planilla está protegida con contraseña o en un formato antiguo de Excel. Quitar la contraseña y guardarla como .xlsx.');

it('rechaza un archivo que no existe', function () {
    iterator_to_array((new LectorXlsx)->filas($this->directorio.'/no-existe.xlsx'));
})->throws(PlanillaIlegible::class, 'No se pudo leer la planilla.');
