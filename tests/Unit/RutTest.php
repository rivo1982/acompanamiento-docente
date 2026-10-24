<?php

use App\Exceptions\RutInvalido;
use App\Support\Rut;

it('acepta el RUT escrito de las formas habituales', function (string $entrada) {
    $rut = Rut::desde($entrada);

    expect($rut->cuerpo())->toBe(12345678)
        ->and($rut->dv())->toBe('5')
        ->and($rut->paraAlmacenar())->toBe('12345678-5')
        ->and($rut->formateado())->toBe('12.345.678-5');
})->with([
    'con puntos y guion' => '12.345.678-5',
    'con guion' => '12345678-5',
    'sin guion' => '123456785',
    'con espacios' => ' 12 345 678 - 5 ',
    'con ceros a la izquierda' => '0012345678-5',
]);

it('acepta el dígito verificador K en minúscula o mayúscula y lo guarda en mayúscula', function (string $entrada) {
    $rut = Rut::desde($entrada);

    expect($rut->dv())->toBe('K')
        ->and($rut->paraAlmacenar())->toBe('10000013-K')
        ->and($rut->formateado())->toBe('10.000.013-K');
})->with(['10.000.013-k', '10000013K', '10000013-K']);

it('acepta el dígito verificador 0', function () {
    expect(Rut::desde('11.111.117-0')->paraAlmacenar())->toBe('11111117-0');
});

it('guarda los RUT cortos igual que Vinkured', function () {
    expect(Rut::desde('1-9')->paraAlmacenar())->toBe('1-9')
        ->and(Rut::desde('1-9')->formateado())->toBe('1-9');
});

it('rechaza un dígito verificador que no corresponde', function () {
    Rut::desde('12.345.678-9');
})->throws(RutInvalido::class, 'El dígito verificador del RUT no corresponde.');

it('rechaza un RUT vacío', function (string $entrada) {
    Rut::desde($entrada);
})->with(['', '   ', '.-'])->throws(RutInvalido::class, 'El RUT es obligatorio.');

it('rechaza un RUT con formato inválido', function (string $entrada) {
    Rut::desde($entrada);
})->with([
    'solo un carácter' => '5',
    'cuerpo demasiado largo' => '123456789-5',
    'letras en el cuerpo' => '12A45678-5',
    'dígito verificador inválido' => '12345678-X',
    'dos guiones' => '1234-5678-5',
    'cuerpo cero' => '0-0',
])->throws(RutInvalido::class, 'El RUT no tiene un formato válido.');

it('indica si un texto es un RUT válido', function () {
    expect(Rut::esValido('12.345.678-5'))->toBeTrue()
        ->and(Rut::esValido('12.345.678-4'))->toBeFalse()
        ->and(Rut::esValido('abc'))->toBeFalse();
});
