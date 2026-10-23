<?php

/*
 * Ported from tests/Feature/Models/RubricTest.php (commit b3397fc), plus the tenancy rules of
 * the establecimiento-owned pauta.
 */

use App\Enums\NivelLogro;
use App\Models\Ambito;
use App\Models\ApoyoPedagogico;
use App\Models\Indicador;
use App\Models\Pauta;
use App\Models\PautaBase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->establecimiento = newEstablecimiento('1000');
    activar(usuarioConMembresia($this->establecimiento), $this->establecimiento);
});

it('ordena los indicadores de la pauta por la posición del pivote', function () {
    $pauta = Pauta::factory()->create();
    [$primero, $segundo, $tercero] = Indicador::factory()->count(3)->create();

    $pauta->indicadores()->attach([
        $primero->id => ['posicion' => 3],
        $segundo->id => ['posicion' => 1],
        $tercero->id => ['posicion' => 2],
    ]);

    expect($pauta->indicadores->pluck('id')->all())->toBe([$segundo->id, $tercero->id, $primero->id]);
});

it('asigna el establecimiento activo a las filas del pivote', function () {
    $pauta = Pauta::factory()->create();
    $indicador = Indicador::factory()->create();

    $pauta->indicadores()->attach($indicador, ['posicion' => 1]);

    expect(DB::table('indicador_pauta')->where('pauta_id', $pauta->id)->value('establecimiento_id'))
        ->toBe($this->establecimiento);
});

it('expone ámbitos e indicadores de la pauta base en orden', function () {
    $pautaBase = PautaBase::factory()->create();
    $ambito = Ambito::factory()->for($pautaBase)->create();
    $posterior = Indicador::factory()->for($ambito)->create(['numero' => 12]);
    $anterior = Indicador::factory()->for($ambito)->create(['numero' => 11]);

    expect($ambito->indicadores->pluck('id')->all())->toBe([$anterior->id, $posterior->id])
        ->and($pautaBase->indicadores->pluck('id')->all())->toEqualCanonicalizing([$anterior->id, $posterior->id]);
});

it('devuelve el descriptor del indicador para cada nivel', function () {
    $indicador = Indicador::factory()->create([
        'nivel_en_inicio' => 'Inicio',
        'nivel_en_desarrollo' => 'Desarrollo',
        'nivel_logrado' => 'Logro',
        'nivel_destacado' => 'Destaque',
    ]);

    expect($indicador->descripcionNivel(NivelLogro::EnInicio))->toBe('Inicio')
        ->and($indicador->descripcionNivel(NivelLogro::EnDesarrollo))->toBe('Desarrollo')
        ->and($indicador->descripcionNivel(NivelLogro::Logrado))->toBe('Logro')
        ->and($indicador->descripcionNivel(NivelLogro::Destacado))->toBe('Destaque');
});

it('ordena los apoyos pedagógicos del indicador por posición', function () {
    $indicador = Indicador::factory()->create();
    $segundo = ApoyoPedagogico::factory()->for($indicador)->create(['posicion' => 2]);
    $primero = ApoyoPedagogico::factory()->for($indicador)->create(['posicion' => 1]);

    expect($indicador->apoyosPedagogicos->pluck('id')->all())->toBe([$primero->id, $segundo->id]);
});

it('rechaza un nivel objetivo de apoyo fuera de logrado y destacado', function () {
    $indicador = Indicador::factory()->create();

    rejected(fn () => DB::table('apoyos_pedagogicos')->insert([
        'indicador_id' => $indicador->id, 'posicion' => 1, 'herramienta' => 'H', 'nivel_objetivo' => 'en_inicio',
        'como_hacerlo' => 'C', 'ejemplo' => 'E', 'duracion' => '5 min', 'senal_de_logro' => 'S',
    ]), '23514');
});

it('no muestra las pautas de otro establecimiento', function () {
    $propia = Pauta::factory()->create();
    $otro = newEstablecimiento('2000', 'Colegio B');
    $ajenaId = DB::table('pautas')->insertGetId(['establecimiento_id' => $otro, 'nombre' => 'Ajena']);

    expect(Pauta::pluck('id')->all())->toBe([$propia->id])
        ->and(Pauta::find($ajenaId))->toBeNull();
});

it('rechaza en la base un pivote que mezcla establecimientos', function () {
    $otro = newEstablecimiento('2000', 'Colegio B');
    $ajenaId = DB::table('pautas')->insertGetId(['establecimiento_id' => $otro, 'nombre' => 'Ajena']);
    $indicador = Indicador::factory()->create();

    rejected(fn () => DB::table('indicador_pauta')->insert([
        'establecimiento_id' => $this->establecimiento, 'pauta_id' => $ajenaId,
        'indicador_id' => $indicador->id, 'posicion' => 1,
    ]), '23503');
});

it('congela en el snapshot el contenido de la pauta', function () {
    $pauta = Pauta::factory()->create(['nombre' => 'Pauta primer ciclo']);
    $indicador = Indicador::factory()->create(['numero' => 7, 'nombre' => 'Clima de aula', 'retro_para_destacado' => null]);
    $pauta->indicadores()->attach($indicador, ['posicion' => 1]);

    expect($pauta->toSnapshot())->toBe([
        'id' => $pauta->id,
        'nombre' => 'Pauta primer ciclo',
        'indicadores' => [[
            'id' => $indicador->id,
            'numero' => 7,
            'nombre' => 'Clima de aula',
            'niveles' => [
                'en_inicio' => $indicador->nivel_en_inicio,
                'en_desarrollo' => $indicador->nivel_en_desarrollo,
                'logrado' => $indicador->nivel_logrado,
                'destacado' => $indicador->nivel_destacado,
            ],
            'retro_que_significa' => $indicador->retro_que_significa,
            'retro_para_logrado' => $indicador->retro_para_logrado,
            'retro_para_destacado' => null,
            'fuentes' => $indicador->fuentes,
        ]],
    ]);
});
