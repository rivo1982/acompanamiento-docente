<?php

use App\Enums\NivelLogro;

it('tiene etiquetas en español', function () {
    expect(NivelLogro::EnInicio->label())->toBe('En inicio')
        ->and(NivelLogro::EnDesarrollo->label())->toBe('En desarrollo')
        ->and(NivelLogro::Logrado->label())->toBe('Logrado')
        ->and(NivelLogro::Destacado->label())->toBe('Destacado');
});

it('tiene los textos oficiales de significado', function () {
    expect(NivelLogro::EnInicio->significado())->toBe('La práctica aparece de forma aislada o con efecto limitado en los estudiantes. El descriptor no aplica a la clase y no es posible observar.')
        ->and(NivelLogro::EnDesarrollo->significado())->toBe('Aparece en parte de la clase o con parte del grupo, con vacíos que limitan su efecto.')
        ->and(NivelLogro::Logrado->significado())->toBe('Se da de manera consistente y los estudiantes responden como se espera. Felicitaciones.')
        ->and(NivelLogro::Destacado->significado())->toBe('Además de lo logrado, la práctica se adapta a la diversidad del grupo y los estudiantes la ejercen con autonomía. Felicitaciones por su labor y su exitoso desarrollo profesional.');
});

it('felicita solo en logrado y destacado', function () {
    expect(NivelLogro::EnInicio->mensajeFelicitacion())->toBeNull()
        ->and(NivelLogro::EnDesarrollo->mensajeFelicitacion())->toBeNull()
        ->and(NivelLogro::Logrado->mensajeFelicitacion())->toBe('Felicitaciones')
        ->and(NivelLogro::Destacado->mensajeFelicitacion())->toBe('Felicitaciones por su labor y su exitoso desarrollo profesional.');
});

it('lista la tabla de significados en orden ascendente', function () {
    $tabla = NivelLogro::tablaSignificados();

    expect(array_column($tabla, 'nivel'))->toBe(['en_inicio', 'en_desarrollo', 'logrado', 'destacado'])
        ->and($tabla[2])->toBe([
            'nivel' => 'logrado',
            'etiqueta' => 'Logrado',
            'significado' => NivelLogro::Logrado->significado(),
        ]);
});
