<?php

/*
 * A2.2: a respuesta only accepts indicators frozen in its acompanamiento's snapshot, and a
 * finalizado acompanamiento no longer accepts any change to its respuestas.
 */

use App\Enums\NivelLogro;
use App\Exceptions\AcompanamientoYaFinalizado;
use App\Exceptions\IndicadorFueraDelSnapshot;
use App\Models\Acompanamiento;
use App\Models\Indicador;
use App\Models\Pauta;
use App\Models\RespuestaAcompanamiento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->establecimiento = newEstablecimiento('1000');
    activar(usuarioConMembresia($this->establecimiento), $this->establecimiento);

    $pauta = Pauta::factory()->create();
    $this->indicador = Indicador::factory()->create();
    $this->otroIndicador = Indicador::factory()->create();
    $pauta->indicadores()->attach($this->indicador, ['posicion' => 1]);
    $pauta->indicadores()->attach($this->otroIndicador, ['posicion' => 2]);
    $this->acompanamiento = Acompanamiento::factory()->for($pauta)->create();
});

it('acepta un indicador del snapshot', function () {
    $respuesta = RespuestaAcompanamiento::factory()->for($this->acompanamiento)->create([
        'indicador_id' => $this->indicador->id,
        'nivel' => NivelLogro::Logrado,
    ]);

    expect($respuesta->exists)->toBeTrue();
});

it('crea con la fábrica por defecto una respuesta válida', function () {
    $respuesta = RespuestaAcompanamiento::factory()->create();

    expect($respuesta->acompanamiento->indicadorDelSnapshot($respuesta->indicador_id))->not->toBeNull();
});

it('rechaza un indicador ausente del snapshot al crear', function () {
    $ajeno = Indicador::factory()->create();

    expect(fn () => RespuestaAcompanamiento::factory()->for($this->acompanamiento)->create(['indicador_id' => $ajeno->id]))
        ->toThrow(IndicadorFueraDelSnapshot::class, "El indicador [{$ajeno->id}] no forma parte");

    expect(DB::table('respuestas_acompanamiento')->count())->toBe(0);
});

it('rechaza cambiar a un indicador ausente del snapshot', function () {
    $respuesta = RespuestaAcompanamiento::factory()->for($this->acompanamiento)->create(['indicador_id' => $this->indicador->id]);
    $ajeno = Indicador::factory()->create();

    expect(fn () => $respuesta->update(['indicador_id' => $ajeno->id]))->toThrow(IndicadorFueraDelSnapshot::class);

    expect(DB::table('respuestas_acompanamiento')->where('id', $respuesta->id)->value('indicador_id'))->toBe($this->indicador->id);
});

describe('con el acompañamiento finalizado', function () {
    beforeEach(function () {
        $this->respuesta = RespuestaAcompanamiento::factory()->for($this->acompanamiento)->create([
            'indicador_id' => $this->indicador->id,
            'nivel' => NivelLogro::EnDesarrollo,
        ]);
        $this->acompanamiento->finalizar();
    });

    it('rechaza crear respuestas', function () {
        expect(fn () => RespuestaAcompanamiento::factory()->for($this->acompanamiento)->create(['indicador_id' => $this->otroIndicador->id]))
            ->toThrow(AcompanamientoYaFinalizado::class, 'no admite cambios en sus respuestas');

        expect(DB::table('respuestas_acompanamiento')->count())->toBe(1);
    });

    it('rechaza modificar respuestas, también desde una copia previa al cierre', function () {
        expect(fn () => $this->respuesta->update(['nivel' => NivelLogro::Destacado]))
            ->toThrow(AcompanamientoYaFinalizado::class);

        expect(DB::table('respuestas_acompanamiento')->where('id', $this->respuesta->id)->value('nivel'))->toBe('en_desarrollo');
    });

    it('rechaza eliminar respuestas', function () {
        expect(fn () => $this->respuesta->delete())->toThrow(AcompanamientoYaFinalizado::class);

        expect(DB::table('respuestas_acompanamiento')->count())->toBe(1);
    });

    it('rechaza mover una respuesta abierta a un acompañamiento finalizado', function () {
        $abierto = Acompanamiento::factory()->for(Pauta::find($this->acompanamiento->pauta_id))->create();
        $respuesta = RespuestaAcompanamiento::factory()->for($abierto)->create(['indicador_id' => $this->otroIndicador->id]);

        expect(fn () => $respuesta->update(['acompanamiento_id' => $this->acompanamiento->id]))
            ->toThrow(AcompanamientoYaFinalizado::class);
    });
});

it('rechaza sacar una respuesta de un acompañamiento finalizado', function () {
    $respuesta = RespuestaAcompanamiento::factory()->for($this->acompanamiento)->create(['indicador_id' => $this->indicador->id]);
    $this->acompanamiento->finalizar();
    $abierto = Acompanamiento::factory()->for(Pauta::find($this->acompanamiento->pauta_id))->create();

    expect(fn () => $respuesta->update(['acompanamiento_id' => $abierto->id]))->toThrow(AcompanamientoYaFinalizado::class);
});

describe('en la base, sin pasar por Eloquent', function () {
    it('acepta insertar una respuesta de un indicador del snapshot mientras sigue en curso', function () {
        DB::table('respuestas_acompanamiento')->insert([
            'establecimiento_id' => $this->establecimiento, 'acompanamiento_id' => $this->acompanamiento->id,
            'indicador_id' => $this->indicador->id, 'nivel' => 'logrado',
        ]);

        expect(DB::table('respuestas_acompanamiento')->count())->toBe(1);
    });

    it('rechaza insertar un indicador ausente del snapshot', function () {
        $ajeno = Indicador::factory()->create();

        rejected(fn () => DB::table('respuestas_acompanamiento')->insert([
            'establecimiento_id' => $this->establecimiento, 'acompanamiento_id' => $this->acompanamiento->id,
            'indicador_id' => $ajeno->id, 'nivel' => 'logrado',
        ]), '23514');
    });

    it('rechaza cambiar en bloque a un indicador ausente del snapshot', function () {
        RespuestaAcompanamiento::factory()->for($this->acompanamiento)->create(['indicador_id' => $this->indicador->id]);
        $ajeno = Indicador::factory()->create();

        rejected(fn () => RespuestaAcompanamiento::query()->update(['indicador_id' => $ajeno->id]), '23514');

        expect(DB::table('respuestas_acompanamiento')->value('indicador_id'))->toBe($this->indicador->id);
    });

    describe('con el acompañamiento finalizado', function () {
        beforeEach(function () {
            RespuestaAcompanamiento::factory()->for($this->acompanamiento)->create([
                'indicador_id' => $this->indicador->id, 'nivel' => NivelLogro::EnDesarrollo,
            ]);
            $this->acompanamiento->finalizar();
        });

        it('rechaza insertar respuestas', function () {
            rejected(fn () => DB::table('respuestas_acompanamiento')->insert([
                'establecimiento_id' => $this->establecimiento, 'acompanamiento_id' => $this->acompanamiento->id,
                'indicador_id' => $this->otroIndicador->id, 'nivel' => 'logrado',
            ]), '23514');

            expect(DB::table('respuestas_acompanamiento')->count())->toBe(1);
        });

        it('rechaza modificar respuestas en bloque', function () {
            rejected(fn () => RespuestaAcompanamiento::query()
                ->where('acompanamiento_id', $this->acompanamiento->id)
                ->update(['nivel' => 'destacado']), '23514');

            expect(DB::table('respuestas_acompanamiento')->value('nivel'))->toBe('en_desarrollo');
        });

        it('rechaza eliminar respuestas en bloque', function () {
            rejected(fn () => RespuestaAcompanamiento::query()
                ->where('acompanamiento_id', $this->acompanamiento->id)
                ->delete(), '23514');

            expect(DB::table('respuestas_acompanamiento')->count())->toBe(1);
        });

        it('rechaza sacar en bloque una respuesta hacia un acompañamiento abierto', function () {
            $abierto = Acompanamiento::factory()->for(Pauta::find($this->acompanamiento->pauta_id))->create();

            rejected(fn () => DB::table('respuestas_acompanamiento')
                ->where('acompanamiento_id', $this->acompanamiento->id)
                ->update(['acompanamiento_id' => $abierto->id]), '23514');
        });
    });
});
