<?php

use App\Support\Importacion\FilaDePlanilla;
use App\Support\Importacion\ValidadorDePlanilla;

const ENCABEZADO_VALIDO = ['RUT', 'Nombres', 'Primer apellido', 'Segundo apellido', 'Correo', 'Teléfono', 'Función'];

/**
 * @param  array<int, mixed>  $cambios  valores que reemplazan a los de la fila válida, por posición
 * @return list<string|int|float|null>
 */
function filaValida(array $cambios = []): array
{
    return array_replace(['12.345.678-5', 'Ana María', 'Pérez', 'Soto', 'ana@ejemplo.cl', '+56 9 1234 5678', 'Docente'], $cambios);
}

/**
 * @param  list<array<int, mixed>>  $filasDeDatos
 * @return list<array<int, mixed>>
 */
function conEncabezado(array $filasDeDatos): array
{
    return [ENCABEZADO_VALIDO, ...$filasDeDatos];
}

it('convierte una fila válida en una fila de planilla normalizada', function () {
    $resultado = (new ValidadorDePlanilla)->validar(conEncabezado([
        filaValida([4 => '  Ana@Ejemplo.CL ', 6 => 'docente']),
    ]));

    expect($resultado->esValido())->toBeTrue()
        ->and($resultado->erroresDelArchivo)->toBe([])
        ->and($resultado->erroresPorFila)->toBe([])
        ->and($resultado->filasValidas)->toHaveCount(1);

    $fila = $resultado->filasValidas[0];
    expect($fila)->toBeInstanceOf(FilaDePlanilla::class)
        ->and($fila->numeroDeFila)->toBe(2)
        ->and($fila->rut->paraAlmacenar())->toBe('12345678-5')
        ->and($fila->nombres)->toBe('Ana María')
        ->and($fila->primerApellido)->toBe('Pérez')
        ->and($fila->segundoApellido)->toBe('Soto')
        ->and($fila->correo)->toBe('ana@ejemplo.cl')
        ->and($fila->telefono)->toBe('+56 9 1234 5678')
        ->and($fila->categoriaFuncion)->toBe('docente');
});

it('deja nulos el segundo apellido y el teléfono vacíos', function () {
    $resultado = (new ValidadorDePlanilla)->validar(conEncabezado([filaValida([3 => '  ', 5 => null])]));

    expect($resultado->filasValidas[0]->segundoApellido)->toBeNull()
        ->and($resultado->filasValidas[0]->telefono)->toBeNull();
});

it('lee el RUT que Excel entrega como número', function (int|float $rut) {
    $resultado = (new ValidadorDePlanilla)->validar(conEncabezado([filaValida([0 => $rut])]));

    expect($resultado->esValido())->toBeTrue()
        ->and($resultado->filasValidas[0]->rut->paraAlmacenar())->toBe('12345678-5');
})->with(['entero' => 123456785, 'decimal' => 123456785.0]);

it('mapea la función a la categoría de Vinkured sin importar mayúsculas ni tildes', function (string $funcion, string $categoria) {
    $resultado = (new ValidadorDePlanilla)->validar(conEncabezado([filaValida([6 => $funcion])]));

    expect($resultado->filasValidas[0]->categoriaFuncion)->toBe($categoria);
})->with([
    ['Docente', 'docente'],
    ['DIRECTIVO', 'directivo'],
    ['asistente', 'asistente'],
    ['Profesional de apoyo', 'profesional_apoyo'],
    ['  PROFESIONAL   DE  APÓYO ', 'profesional_apoyo'],
]);

it('acepta el encabezado sin importar mayúsculas, tildes ni espacios', function () {
    $resultado = (new ValidadorDePlanilla)->validar([
        [' rut ', 'NOMBRES', 'primer  apellido', 'Segundo Apellido', 'correo', 'TELEFONO', 'funcion', null],
        filaValida(),
    ]);

    expect($resultado->esValido())->toBeTrue();
});

it('rechaza el archivo con un encabezado distinto', function (array $encabezado) {
    $resultado = (new ValidadorDePlanilla)->validar([$encabezado, filaValida()]);

    expect($resultado->esValido())->toBeFalse()
        ->and($resultado->erroresDelArchivo)->toBe([
            'El encabezado de la planilla no corresponde. La fila 1 debe tener estas columnas, en este orden: RUT | Nombres | Primer apellido | Segundo apellido | Correo | Teléfono | Función.',
        ])
        ->and($resultado->filasValidas)->toBe([]);
})->with([
    'columnas en otro orden' => [['Nombres', 'RUT', 'Primer apellido', 'Segundo apellido', 'Correo', 'Teléfono', 'Función']],
    'falta una columna' => [['RUT', 'Nombres', 'Primer apellido', 'Segundo apellido', 'Correo', 'Función']],
    'columna de más' => [[...ENCABEZADO_VALIDO, 'Cargo']],
    'sin encabezado' => [filaValida()],
]);

it('rechaza un archivo vacío', function () {
    $resultado = (new ValidadorDePlanilla)->validar([[null, ''], []]);

    expect($resultado->erroresDelArchivo)->toBe(['La planilla está vacía.']);
});

it('rechaza un archivo sin filas de funcionarios', function () {
    $resultado = (new ValidadorDePlanilla)->validar([ENCABEZADO_VALIDO, [null, '  ']]);

    expect($resultado->erroresDelArchivo)->toBe(['La planilla no tiene funcionarios bajo el encabezado.']);
});

it('rechaza un archivo con más de 2000 funcionarios', function () {
    $filas = (function () {
        yield ENCABEZADO_VALIDO;
        for ($i = 0; $i < 2001; $i++) {
            yield filaValida();
        }
    })();

    $resultado = (new ValidadorDePlanilla)->validar($filas);

    expect($resultado->erroresDelArchivo)->toBe(['La planilla tiene más de 2.000 funcionarios. Divídala en archivos más pequeños.'])
        ->and($resultado->filasValidas)->toBe([])
        ->and($resultado->erroresPorFila)->toBe([]);
});

it('acepta exactamente 2000 funcionarios', function () {
    $filas = [ENCABEZADO_VALIDO];
    for ($i = 1; $i <= 2000; $i++) {
        $filas[] = filaValida([0 => (string) (1000000 + $i).'-'.rutDv(1000000 + $i), 4 => "persona{$i}@ejemplo.cl"]);
    }

    $resultado = (new ValidadorDePlanilla)->validar($filas);

    expect($resultado->esValido())->toBeTrue()
        ->and($resultado->filasValidas)->toHaveCount(2000);
});

it('omite las filas vacías sin cambiar la numeración de Excel', function () {
    $resultado = (new ValidadorDePlanilla)->validar([
        ENCABEZADO_VALIDO,
        [null, '', '   '],
        filaValida(),
        [],
        filaValida([0 => '11.111.111-1', 4 => 'otra@ejemplo.cl', 6 => 'sin función']),
    ]);

    expect($resultado->filasValidas)->toHaveCount(1)
        ->and($resultado->filasValidas[0]->numeroDeFila)->toBe(3)
        ->and($resultado->erroresPorFila)->toBe([5 => ['La función debe ser docente, directivo, asistente o profesional de apoyo.']]);
});

it('informa todos los errores de una fila', function () {
    $resultado = (new ValidadorDePlanilla)->validar(conEncabezado([
        ['12.345.678-9', '', ' ', 'Soto', 'no-es-correo', 'llamar al 22', ''],
    ]));

    expect($resultado->esValido())->toBeFalse()
        ->and($resultado->filasValidas)->toBe([])
        ->and($resultado->erroresPorFila)->toBe([2 => [
            'El dígito verificador del RUT no corresponde.',
            'Los nombres son obligatorios.',
            'El primer apellido es obligatorio.',
            'El correo no es válido.',
            'El teléfono solo puede tener números, espacios y los signos + - ( ).',
            'La función es obligatoria.',
        ]]);
});

it('exige RUT y correo', function () {
    $resultado = (new ValidadorDePlanilla)->validar(conEncabezado([filaValida([0 => '', 4 => '  '])]));

    expect($resultado->erroresPorFila)->toBe([2 => ['El RUT es obligatorio.', 'El correo es obligatorio.']]);
});

it('limita el largo del teléfono y de los nombres', function () {
    $resultado = (new ValidadorDePlanilla)->validar(conEncabezado([
        filaValida([1 => str_repeat('a', 101), 5 => str_repeat('9', 31)]),
    ]));

    expect($resultado->erroresPorFila)->toBe([2 => [
        'Los nombres no pueden superar los 100 caracteres.',
        'El teléfono no puede superar los 30 caracteres.',
    ]]);
});

it('marca los RUT y correos repetidos en las filas posteriores', function () {
    $resultado = (new ValidadorDePlanilla)->validar(conEncabezado([
        filaValida(),
        filaValida([0 => '123456785', 4 => 'otra@ejemplo.cl']),
        filaValida([0 => '11.111.111-1', 4 => 'ANA@ejemplo.cl']),
        filaValida([0 => '12345678-5', 4 => 'Ana@Ejemplo.cl']),
    ]));

    expect($resultado->filasValidas)->toHaveCount(1)
        ->and($resultado->filasValidas[0]->numeroDeFila)->toBe(2)
        ->and($resultado->erroresPorFila)->toBe([
            3 => ['El RUT ya aparece en la fila 2.'],
            4 => ['El correo ya aparece en la fila 2.'],
            5 => ['El RUT ya aparece en la fila 2.', 'El correo ya aparece en la fila 2.'],
        ]);
});

it('rechaza una fila con datos más allá de la columna 7', function (mixed $extra) {
    $resultado = (new ValidadorDePlanilla)->validar(conEncabezado([[...filaValida(), null, $extra]]));

    expect($resultado->filasValidas)->toBe([])
        ->and($resultado->erroresPorFila)->toBe([2 => ['La fila tiene datos fuera de las 7 columnas de la planilla.']]);
})->with(['texto' => 'Jefe UTP', 'número' => 0]);

it('acepta celdas vacías más allá de la columna 7', function () {
    $resultado = (new ValidadorDePlanilla)->validar(conEncabezado([[...filaValida(), '', null, '  ']]));

    expect($resultado->esValido())->toBeTrue()
        ->and($resultado->filasValidas)->toHaveCount(1);
});

it('no menciona otros establecimientos ni cuentas en los errores', function () {
    $resultado = (new ValidadorDePlanilla)->validar(conEncabezado([
        filaValida(),
        filaValida([4 => 'x']),
    ]));

    $mensajes = implode(' ', array_merge(...array_values($resultado->erroresPorFila)));

    expect($mensajes)->not->toContain('establecimiento')
        ->and($mensajes)->not->toContain('cuenta')
        ->and($mensajes)->not->toContain('registrad');
});

function rutDv(int $cuerpo): string
{
    $suma = 0;
    $factor = 2;
    foreach (array_reverse(str_split((string) $cuerpo)) as $digito) {
        $suma += (int) $digito * $factor;
        $factor = $factor === 7 ? 2 : $factor + 1;
    }
    $resto = 11 - $suma % 11;

    return match ($resto) {
        11 => '0',
        10 => 'K',
        default => (string) $resto,
    };
}

it('valida un funcionario suelto con las reglas de la planilla y deja los errores por campo', function () {
    $validacion = (new ValidadorDePlanilla)->validarFuncionario(['12.345.678-9', '', 'Pérez', null, 'no-es-correo', '9 abc', 'Bibliotecario']);

    expect($validacion->fila)->toBeNull()
        ->and(array_keys($validacion->erroresPorCampo))->toBe(['rut', 'nombres', 'correo', 'telefono', 'funcion'])
        ->and($validacion->erroresPorCampo['nombres'])->toBe(['Los nombres son obligatorios.'])
        ->and($validacion->erroresPorCampo['correo'])->toBe(['El correo no es válido.'])
        ->and($validacion->erroresPorCampo['funcion'])->toBe(['La función debe ser docente, directivo, asistente o profesional de apoyo.'])
        ->and($validacion->mensajes())->toHaveCount(5);
});

it('normaliza un funcionario suelto válido igual que una fila de la planilla', function () {
    $validacion = (new ValidadorDePlanilla)->validarFuncionario([' 12.345.678-5 ', 'Ana María', 'Pérez', '', 'Ana@Ejemplo.CL', '', 'Profesional de apoyo']);

    expect($validacion->erroresPorCampo)->toBe([])
        ->and($validacion->fila->rut->paraAlmacenar())->toBe('12345678-5')
        ->and($validacion->fila->correo)->toBe('ana@ejemplo.cl')
        ->and($validacion->fila->segundoApellido)->toBeNull()
        ->and($validacion->fila->telefono)->toBeNull()
        ->and($validacion->fila->categoriaFuncion)->toBe('profesional_apoyo');
});
