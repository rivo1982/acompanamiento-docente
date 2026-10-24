<?php

use App\Exceptions\SinEstablecimientoActivo;
use App\Support\DiasHabiles;
use App\Support\EstadoDePlazo;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/*
 * Ported from Vinkured tests/Feature/Plazos/EstadoDePlazoTest.php (characterization of the @host-owned copies).
 */

require_once __DIR__.'/helpers.php';

uses(RefreshDatabase::class);

beforeEach(fn () => usarZonaDeVinkured());
afterEach(function () {
    CarbonImmutable::setTestNow();
    restaurarZonaDelModulo();
});

function santiago(string $fechaHora): CarbonImmutable
{
    return CarbonImmutable::parse($fechaHora, 'America/Santiago');
}

function estado(string $vencimiento, string $ahora, ?int $establecimientoId = null): EstadoDePlazo
{
    return EstadoDePlazo::calcular(santiago($vencimiento), santiago($ahora), app(DiasHabiles::class), $establecimientoId);
}

// "Now" is Wednesday 2026-10-14 10:00 in Santiago in the table below.
dataset('estados', [
    'due Friday: 2 business days' => ['2026-10-16 18:00', EstadoDePlazo::EN_PLAZO, 2, 'En plazo (2 días hábiles)', 'neutro'],
    'due Thursday: 1 business day' => ['2026-10-15 09:00', EstadoDePlazo::EN_PLAZO, 1, 'En plazo (1 día hábil)', 'neutro'],
    'due next Wednesday: 5' => ['2026-10-21 12:00', EstadoDePlazo::EN_PLAZO, 5, 'En plazo (5 días hábiles)', 'neutro'],
    'due today later' => ['2026-10-14 18:00', EstadoDePlazo::VENCE_HOY, 0, 'Vence hoy', 'aviso'],
    'due this very instant is not overdue yet' => ['2026-10-14 10:00', EstadoDePlazo::VENCE_HOY, 0, 'Vence hoy', 'aviso'],
    'due today but already passed' => ['2026-10-14 09:00', EstadoDePlazo::VENCIDO, 0, 'Vencido hoy', 'peligro'],
    'overdue since yesterday: 1 business day' => ['2026-10-13 18:00', EstadoDePlazo::VENCIDO, 1, 'Vencido hace 1 día hábil', 'peligro'],
    'overdue since Monday: 2 business days' => ['2026-10-12 08:00', EstadoDePlazo::VENCIDO, 2, 'Vencido hace 2 días hábiles', 'peligro'],
    'overdue across a weekend' => ['2026-10-09 18:00', EstadoDePlazo::VENCIDO, 3, 'Vencido hace 3 días hábiles', 'peligro'],
]);

it('classifies a deadline against the current time', function (string $vencimiento, string $tipo, int $dias, string $etiqueta, string $variante) {
    dosEstablecimientosConActivo();

    $estado = estado($vencimiento, '2026-10-14 10:00');

    expect($estado->tipo)->toBe($tipo)
        ->and($estado->dias)->toBe($dias)
        ->and($estado->etiqueta())->toBe($etiqueta)
        ->and($estado->variante())->toBe($variante);
})->with('estados');

it('pluralizes business days: 0, 1 and 2', function () {
    expect(EstadoDePlazo::enPlazo(0)->etiqueta())->toBe('En plazo (0 días hábiles)')
        ->and(EstadoDePlazo::enPlazo(1)->etiqueta())->toBe('En plazo (1 día hábil)')
        ->and(EstadoDePlazo::enPlazo(2)->etiqueta())->toBe('En plazo (2 días hábiles)')
        ->and(EstadoDePlazo::vencido(0)->etiqueta())->toBe('Vencido hoy')
        ->and(EstadoDePlazo::vencido(1)->etiqueta())->toBe('Vencido hace 1 día hábil')
        ->and(EstadoDePlazo::vencido(2)->etiqueta())->toBe('Vencido hace 2 días hábiles')
        ->and(EstadoDePlazo::venceHoy()->etiqueta())->toBe('Vence hoy');
});

it('never has an empty label and maps each outcome to a badge variant', function () {
    foreach ([EstadoDePlazo::enPlazo(3), EstadoDePlazo::venceHoy(), EstadoDePlazo::vencido(4)] as $estado) {
        expect($estado->etiqueta())->not->toBe('');
    }

    expect(EstadoDePlazo::enPlazo(3)->variante())->toBe('neutro')
        ->and(EstadoDePlazo::venceHoy()->variante())->toBe('aviso')
        ->and(EstadoDePlazo::vencido(4)->variante())->toBe('peligro')
        ->and(EstadoDePlazo::vencido(4)->esVencido())->toBeTrue()
        ->and(EstadoDePlazo::venceHoy()->esVencido())->toBeFalse();
});

it('rejects states with a negative amount of days', function () {
    expect(fn () => EstadoDePlazo::enPlazo(-1))->toThrow(InvalidArgumentException::class)
        ->and(fn () => EstadoDePlazo::vencido(-1))->toThrow(InvalidArgumentException::class);
});

it('counts at least one business day when the local dates differ but no business day elapsed', function () {
    dosEstablecimientosConActivo();

    // Saturday 18:00 deadline, now Sunday 10:00: zero business days elapsed, but it is overdue.
    $estado = estado('2026-10-17 18:00', '2026-10-18 10:00');

    expect($estado->tipo)->toBe(EstadoDePlazo::VENCIDO)
        ->and($estado->dias)->toBe(1)
        ->and($estado->etiqueta())->toBe('Vencido hace 1 día hábil');
});

it('skips holidays when counting overdue days', function () {
    [$a, $b] = dosEstablecimientosConActivo();
    diaNoHabil(null, '2026-10-16');
    app(DiasHabiles::class)->olvidar();

    // Thursday 15 deadline, now Monday 19, Friday 16 is a holiday: only Monday counts.
    expect(estado('2026-10-15 18:00', '2026-10-19 09:00')->dias)->toBe(1);
});

it('skips an establecimiento holiday only for that establecimiento', function () {
    [$a, $b] = dosEstablecimientosConActivo();
    diaNoHabil($a, '2026-10-15');
    diaNoHabil($b, '2026-10-16');
    app(DiasHabiles::class)->olvidar();

    // Deadline Thursday 15 18:00, now Wednesday 14: Thursday is A's holiday (0 days), but a normal day for B (1).
    expect(estado('2026-10-15 18:00', '2026-10-14 10:00')->dias)->toBe(0)
        ->and(estado('2026-10-15 18:00', '2026-10-14 10:00', $b)->dias)->toBe(1)
        ->and(estado('2026-10-21 18:00', '2026-10-14 10:00')->dias)->toBe(4)
        ->and(estado('2026-10-21 18:00', '2026-10-14 10:00', $b)->dias)->toBe(4);
});

it('compares local dates, not UTC dates', function () {
    dosEstablecimientosConActivo();

    // Deadline Friday 23:30 Santiago = Saturday 02:30 UTC. At Friday noon it is "due today", not "in time".
    $vencimiento = santiago('2026-10-16 23:30')->utc();

    expect($vencimiento->format('Y-m-d'))->toBe('2026-10-17');

    $estado = EstadoDePlazo::calcular($vencimiento, santiago('2026-10-16 12:00')->utc(), app(DiasHabiles::class));

    expect($estado->tipo)->toBe(EstadoDePlazo::VENCE_HOY);

    // Seen from Santiago late Friday night after the deadline passed: overdue "today", not "yesterday".
    $pasado = EstadoDePlazo::calcular($vencimiento, santiago('2026-10-16 23:45')->utc(), app(DiasHabiles::class));

    expect($pasado->tipo)->toBe(EstadoDePlazo::VENCIDO)->and($pasado->dias)->toBe(0);
});

it('handles deadlines across the September and April clock changes', function () {
    dosEstablecimientosConActivo();

    // Saturday 2026-09-05 22:00 deadline; Monday 2026-09-07 08:00 (after clocks went forward): overdue 1.
    expect(estado('2026-09-05 22:00', '2026-09-07 08:00')->dias)->toBe(1)
        // Friday 2026-04-03 deadline, Monday 2026-04-06 (after clocks went back): overdue 1.
        ->and(estado('2026-04-03 18:00', '2026-04-06 08:00')->dias)->toBe(1)
        // In time across the change: Friday to Tuesday = 2.
        ->and(estado('2026-04-07 12:00', '2026-04-03 08:00')->dias)->toBe(2);
});

it('uses the current time by default', function () {
    dosEstablecimientosConActivo();
    CarbonImmutable::setTestNow(santiago('2026-10-14 10:00'));

    expect(EstadoDePlazo::calcular(santiago('2026-10-15 09:00'), null, app(DiasHabiles::class))->etiqueta())
        ->toBe('En plazo (1 día hábil)')
        ->and(EstadoDePlazo::calcular(santiago('2026-10-13 09:00'), null, app(DiasHabiles::class))->etiqueta())
        ->toBe('Vencido hace 1 día hábil');
});

it('fails closed without an active establecimiento unless an explicit id is given', function () {
    [$a] = dosEstablecimientosConActivo();
    sinContexto();

    expect(fn () => estado('2026-10-16 18:00', '2026-10-14 10:00'))->toThrow(SinEstablecimientoActivo::class)
        ->and(estado('2026-10-16 18:00', '2026-10-14 10:00', $a)->dias)->toBe(2);
});

it('computes 50 chips with one query to the calendar', function () {
    [$a, $b] = dosEstablecimientosConActivo();
    diaNoHabil(null, '2026-10-20');
    diaNoHabil($a, '2026-11-02');
    app(DiasHabiles::class)->olvidar();

    DB::flushQueryLog();
    DB::enableQueryLog();

    $estados = [];
    for ($i = 0; $i < 50; $i++) {
        $estados[] = estado(santiago('2026-10-01 18:00')->addDays($i * 2)->format('Y-m-d H:i'), '2026-10-14 10:00');
    }

    $consultas = collect(DB::getQueryLog())->filter(fn (array $q): bool => str_contains($q['query'], 'dias_no_habiles'));
    DB::disableQueryLog();

    expect($estados)->toHaveCount(50)->and($consultas)->toHaveCount(1);
});
