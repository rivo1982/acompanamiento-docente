<?php

use App\Exceptions\SinEstablecimientoActivo;
use App\Models\DiaNoHabil;
use App\Support\DiasHabiles;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/*
 * Ported from Vinkured tests/Feature/Plazos/DiasHabilesTest.php (characterization of the @host-owned copies).
 */

require_once __DIR__.'/helpers.php';

uses(RefreshDatabase::class);

beforeEach(fn () => usarZonaDeVinkured());
afterEach(fn () => restaurarZonaDelModulo());

function dias(): DiasHabiles
{
    return app(DiasHabiles::class);
}

/** @param list<array{0: ?string, 1: string}> $feriados [scope, fecha]: scope "A"/"B" or null for national */
function cargarFeriados(array $feriados, int $a, int $b): void
{
    foreach ($feriados as [$alcance, $fecha]) {
        diaNoHabil(match ($alcance) {
            'A' => $a, 'B' => $b, default => null,
        }, $fecha);
    }
    dias()->olvidar();
}

// ---------------------------------------------------------------------------------------------
// sumar / restar: table driven. 2026-10-12 is a Monday; 2026-10-16 a Friday.
// ---------------------------------------------------------------------------------------------

dataset('sumas', [
    'plain week, 3 days' => [[], '2026-10-12', 3, '2026-10-15'],
    'plain week, 4 days' => [[], '2026-10-12', 4, '2026-10-16'],
    'full week lands on next Monday' => [[], '2026-10-12', 5, '2026-10-19'],
    'across a weekend (Fri + 1)' => [[], '2026-10-16', 1, '2026-10-19'],
    'across a weekend (Fri + 2)' => [[], '2026-10-16', 2, '2026-10-20'],
    'starting on a Saturday (+1)' => [[], '2026-10-17', 1, '2026-10-19'],
    'starting on a Sunday (+1)' => [[], '2026-10-18', 1, '2026-10-19'],
    'two weeks' => [[], '2026-10-12', 10, '2026-10-26'],
    'national holiday on a weekday is skipped' => [[[null, '2026-10-14']], '2026-10-12', 3, '2026-10-16'],
    'holiday on a weekend changes nothing' => [[[null, '2026-10-17']], '2026-10-16', 1, '2026-10-19'],
    'two consecutive holidays' => [[[null, '2026-10-15'], [null, '2026-10-16']], '2026-10-14', 1, '2026-10-19'],
    'holiday + weekend + holiday' => [[[null, '2026-10-16'], [null, '2026-10-19']], '2026-10-15', 1, '2026-10-20'],
    'year boundary' => [[], '2026-12-30', 2, '2027-01-01'],
    'year boundary with New Year holiday' => [[[null, '2027-01-01']], '2026-12-31', 1, '2027-01-04'],
    'leap day is a business day (Tuesday)' => [[], '2028-02-25', 2, '2028-02-29'],
    'across a leap day' => [[], '2028-02-28', 2, '2028-03-01'],
    'non-leap February' => [[], '2027-02-26', 1, '2027-03-01'],
    'DST starts (Sept 2026): Fri to Mon' => [[], '2026-09-04', 1, '2026-09-07'],
    'DST starts (Sept 2026): Fri + 5' => [[], '2026-09-04', 5, '2026-09-11'],
    'DST ends (Apr 2026): Fri to Mon' => [[], '2026-04-03', 1, '2026-04-06'],
    'DST ends (Apr 2026): Fri + 5' => [[], '2026-04-03', 5, '2026-04-10'],
    'n = 0 returns the same business day' => [[], '2026-10-14', 0, '2026-10-14'],
    'n = 0 on a Saturday is returned unchanged' => [[], '2026-10-17', 0, '2026-10-17'],
    'n = 0 on a holiday is returned unchanged' => [[[null, '2026-10-14']], '2026-10-14', 0, '2026-10-14'],
]);

it('adds business days', function (array $feriados, string $desde, int $n, string $esperado) {
    [$a, $b] = dosEstablecimientosConActivo();
    cargarFeriados($feriados, $a, $b);

    $resultado = dias()->sumar($desde, $n);

    expect($resultado->format('Y-m-d'))->toBe($esperado)
        ->and($resultado->timezoneName)->toBe('America/Santiago');
})->with('sumas');

dataset('restas', [
    'plain week' => [[], '2026-10-15', 3, '2026-10-12'],
    'across a weekend (Mon - 1)' => [[], '2026-10-19', 1, '2026-10-16'],
    'across a weekend (Tue - 2)' => [[], '2026-10-20', 2, '2026-10-16'],
    'from a Sunday (-1)' => [[], '2026-10-18', 1, '2026-10-16'],
    'national holiday skipped' => [[[null, '2026-10-14']], '2026-10-16', 3, '2026-10-12'],
    'two consecutive holidays' => [[[null, '2026-10-15'], [null, '2026-10-16']], '2026-10-19', 1, '2026-10-14'],
    'year boundary' => [[], '2027-01-04', 1, '2027-01-01'],
    'year boundary with New Year holiday' => [[[null, '2027-01-01']], '2027-01-04', 1, '2026-12-31'],
    'leap day' => [[], '2028-03-01', 1, '2028-02-29'],
    'DST starts: Mon - 1' => [[], '2026-09-07', 1, '2026-09-04'],
    'DST ends: Mon - 1' => [[], '2026-04-06', 1, '2026-04-03'],
    'n = 0' => [[], '2026-10-17', 0, '2026-10-17'],
]);

it('subtracts business days', function (array $feriados, string $desde, int $n, string $esperado) {
    [$a, $b] = dosEstablecimientosConActivo();
    cargarFeriados($feriados, $a, $b);

    expect(dias()->restar($desde, $n)->format('Y-m-d'))->toBe($esperado);
})->with('restas');

it('treats a negative amount as the opposite direction', function () {
    dosEstablecimientosConActivo();

    expect(dias()->sumar('2026-10-19', -1)->format('Y-m-d'))->toBe('2026-10-16')
        ->and(dias()->restar('2026-10-16', -1)->format('Y-m-d'))->toBe('2026-10-19');
});

// ---------------------------------------------------------------------------------------------
// entre: signed
// ---------------------------------------------------------------------------------------------

dataset('entre', [
    'same day' => [[], '2026-10-14', '2026-10-14', 0],
    'next business day' => [[], '2026-10-14', '2026-10-15', 1],
    'previous business day is negative' => [[], '2026-10-15', '2026-10-14', -1],
    'Friday to Monday' => [[], '2026-10-16', '2026-10-19', 1],
    'Monday to Friday' => [[], '2026-10-19', '2026-10-16', -1],
    'Saturday to Sunday' => [[], '2026-10-17', '2026-10-18', 0],
    'Friday to Saturday' => [[], '2026-10-16', '2026-10-17', 0],
    'a full week' => [[], '2026-10-12', '2026-10-19', 5],
    'holiday excluded' => [[[null, '2026-10-14']], '2026-10-12', '2026-10-16', 3],
    'holiday as the end day does not count' => [[[null, '2026-10-16']], '2026-10-12', '2026-10-16', 3],
    'holiday as the start day is irrelevant' => [[[null, '2026-10-12']], '2026-10-12', '2026-10-16', 4],
    'year boundary' => [[], '2026-12-30', '2027-01-05', 4],
    'across leap day' => [[], '2028-02-28', '2028-03-01', 2],
    'across DST start' => [[], '2026-09-04', '2026-09-08', 2],
    'across DST end' => [[], '2026-04-03', '2026-04-07', 2],
]);

it('counts signed business days between two dates', function (array $feriados, string $desde, string $hasta, int $esperado) {
    [$a, $b] = dosEstablecimientosConActivo();
    cargarFeriados($feriados, $a, $b);

    expect(dias()->entre($desde, $hasta))->toBe($esperado)
        ->and(dias()->entre($hasta, $desde))->toBe(-$esperado);
})->with('entre');

it('knows which days are business days', function () {
    [$a, $b] = dosEstablecimientosConActivo();
    cargarFeriados([[null, '2026-10-14'], ['A', '2026-10-15'], ['B', '2026-10-16']], $a, $b);

    expect(dias()->esHabil('2026-10-12'))->toBeTrue()
        ->and(dias()->esHabil('2026-10-14'))->toBeFalse()
        ->and(dias()->esHabil('2026-10-15'))->toBeFalse()
        ->and(dias()->esHabil('2026-10-16'))->toBeTrue()
        ->and(dias()->esHabil('2026-10-17'))->toBeFalse()
        ->and(dias()->esHabil('2026-10-18'))->toBeFalse();
});

// ---------------------------------------------------------------------------------------------
// Property style: sumar / restar / entre are consistent
// ---------------------------------------------------------------------------------------------

it('keeps sumar, restar and entre consistent for every business day and amount', function () {
    [$a, $b] = dosEstablecimientosConActivo();
    cargarFeriados([
        [null, '2026-10-12'], [null, '2026-10-31'], [null, '2026-11-01'], ['A', '2026-11-20'], ['A', '2026-12-08'],
        ['B', '2026-11-23'], [null, '2026-12-25'], [null, '2027-01-01'], [null, '2027-01-04'], ['A', '2027-02-15'],
    ], $a, $b);

    $comprobadas = 0;
    for ($d = CarbonImmutable::parse('2026-10-01', 'UTC'); $d <= CarbonImmutable::parse('2027-03-15', 'UTC'); $d = $d->addDay()) {
        $fecha = $d->format('Y-m-d');
        if (! dias()->esHabil($fecha)) {
            continue;
        }
        foreach (range(0, 12) as $n) {
            $sumada = dias()->sumar($fecha, $n);
            expect(dias()->entre($fecha, $sumada))->toBe($n, "entre({$fecha}, sumar({$fecha}, {$n}))");
            expect(dias()->esHabil($sumada))->toBeTrue("sumar({$fecha}, {$n}) must be a business day");

            $restada = dias()->restar($fecha, $n);
            expect(dias()->entre($restada, $fecha))->toBe($n, "entre(restar({$fecha}, {$n}), {$fecha})");
            expect(dias()->restar($sumada, $n)->format('Y-m-d'))->toBe($fecha, "restar(sumar({$fecha}, {$n}), {$n})");
            $comprobadas++;
        }
    }

    expect($comprobadas)->toBeGreaterThan(500);
});

// ---------------------------------------------------------------------------------------------
// Isolation between establecimientos
// ---------------------------------------------------------------------------------------------

it('applies a national holiday to every establecimiento', function () {
    [$a, $b] = dosEstablecimientosConActivo();
    cargarFeriados([[null, '2026-10-14']], $a, $b);

    expect(dias()->sumar('2026-10-13', 1)->format('Y-m-d'))->toBe('2026-10-15')
        ->and(dias()->sumar('2026-10-13', 1, $b)->format('Y-m-d'))->toBe('2026-10-15');
});

it('applies an establecimiento holiday only to that establecimiento', function () {
    [$a, $b] = dosEstablecimientosConActivo();
    cargarFeriados([['A', '2026-10-14'], ['B', '2026-10-15']], $a, $b);

    // Active = A: its own Wednesday is skipped, B's Thursday is not.
    expect(dias()->sumar('2026-10-13', 1)->format('Y-m-d'))->toBe('2026-10-15')
        ->and(dias()->sumar('2026-10-14', 1)->format('Y-m-d'))->toBe('2026-10-15')
        ->and(dias()->entre('2026-10-12', '2026-10-16'))->toBe(3);

    // Explicit B: the opposite.
    expect(dias()->sumar('2026-10-13', 1, $b)->format('Y-m-d'))->toBe('2026-10-14')
        ->and(dias()->sumar('2026-10-14', 1, $b)->format('Y-m-d'))->toBe('2026-10-16')
        ->and(dias()->entre('2026-10-12', '2026-10-16', $b))->toBe(3);
});

it('does not leak holidays through the memo when the active establecimiento changes', function () {
    [$a, $b] = dosEstablecimientosConActivo();
    cargarFeriados([['A', '2026-10-14']], $a, $b);

    expect(dias()->entre('2026-10-12', '2026-10-16'))->toBe(3);

    activar(usuarioConMembresia($b), $b);

    expect(dias()->entre('2026-10-12', '2026-10-16'))->toBe(4);
});

// ---------------------------------------------------------------------------------------------
// Fail closed
// ---------------------------------------------------------------------------------------------

it('fails closed without an active establecimiento', function () {
    [$a] = dosEstablecimientosConActivo();
    sinContexto();

    expect(fn () => dias()->sumar('2026-10-12', 1))->toThrow(SinEstablecimientoActivo::class)
        ->and(fn () => dias()->restar('2026-10-12', 1))->toThrow(SinEstablecimientoActivo::class)
        ->and(fn () => dias()->entre('2026-10-12', '2026-10-16'))->toThrow(SinEstablecimientoActivo::class)
        ->and(fn () => dias()->esHabil('2026-10-12'))->toThrow(SinEstablecimientoActivo::class);
});

it('works without an active establecimiento when an explicit id is given', function () {
    [$a, $b] = dosEstablecimientosConActivo();
    cargarFeriados([['A', '2026-10-14'], ['B', '2026-10-15'], [null, '2026-10-16']], $a, $b);
    sinContexto();

    expect(dias()->sumar('2026-10-13', 1, $a)->format('Y-m-d'))->toBe('2026-10-15')
        ->and(dias()->sumar('2026-10-13', 1, $b)->format('Y-m-d'))->toBe('2026-10-14')
        ->and(dias()->sumar('2026-10-14', 2, $a)->format('Y-m-d'))->toBe('2026-10-19');
});

it('rejects an invalid explicit establecimiento id', function () {
    dosEstablecimientosConActivo();

    expect(fn () => dias()->sumar('2026-10-12', 1, 0))->toThrow(InvalidArgumentException::class)
        ->and(fn () => dias()->sumar('2026-10-12', 1, -3))->toThrow(InvalidArgumentException::class);
});

it('rejects input that is not a date', function () {
    dosEstablecimientosConActivo();

    expect(fn () => dias()->sumar('not a date', 1))->toThrow(InvalidArgumentException::class);
});

// ---------------------------------------------------------------------------------------------
// DiaNoHabil: dedicated scope
// ---------------------------------------------------------------------------------------------

it('scopes DiaNoHabil to national rows plus the active establecimiento', function () {
    [$a, $b] = dosEstablecimientosConActivo();
    diaNoHabil(null, '2026-10-14', 'Nacional');
    diaNoHabil($a, '2026-10-15', 'De A');
    diaNoHabil($b, '2026-10-16', 'De B');

    expect(DiaNoHabil::query()->orderBy('fecha')->pluck('descripcion')->all())->toBe(['Nacional', 'De A']);

    activar(usuarioConMembresia($b), $b);

    expect(DiaNoHabil::query()->orderBy('fecha')->pluck('descripcion')->all())->toBe(['Nacional', 'De B']);
});

it('fails closed when querying DiaNoHabil without an active establecimiento', function () {
    dosEstablecimientosConActivo();
    sinContexto();

    expect(fn () => DiaNoHabil::query()->get())->toThrow(SinEstablecimientoActivo::class);
});

it('offers an explicit, named way to read the calendar of one establecimiento', function () {
    [$a, $b] = dosEstablecimientosConActivo();
    diaNoHabil(null, '2026-10-14', 'Nacional');
    diaNoHabil($a, '2026-10-15', 'De A');
    diaNoHabil($b, '2026-10-16', 'De B');
    sinContexto();

    expect(DiaNoHabil::paraEstablecimiento($b)->orderBy('fecha')->pluck('descripcion')->all())->toBe(['Nacional', 'De B']);
});

it('does not allow mass assignment of the tenant column on DiaNoHabil', function () {
    expect((new DiaNoHabil)->isFillable('establecimiento_id'))->toBeFalse()
        ->and((new DiaNoHabil)->isFillable('id'))->toBeFalse();
});

// ---------------------------------------------------------------------------------------------
// Time zone
// ---------------------------------------------------------------------------------------------

it('uses the America/Santiago calendar date of an instant, not the UTC one', function () {
    dosEstablecimientosConActivo();

    // 23:30 in Santiago on Friday 2026-10-16 is 02:30 UTC on Saturday 2026-10-17.
    $instante = CarbonImmutable::parse('2026-10-16 23:30', 'America/Santiago');
    expect($instante->utc()->format('Y-m-d'))->toBe('2026-10-17');

    expect(dias()->fechaLocal($instante))->toBe('2026-10-16')
        ->and(dias()->fechaLocal($instante->utc()))->toBe('2026-10-16')
        ->and(dias()->sumar($instante->utc(), 1)->format('Y-m-d'))->toBe('2026-10-19')
        ->and(dias()->entre($instante->utc(), '2026-10-19'))->toBe(1);
});

it('uses the Santiago date for evening instants that are already the next day in UTC', function () {
    dosEstablecimientosConActivo();

    // 21:30 Santiago on Sunday 2026-10-18 is already Monday 00:30 UTC, but locally still a weekend day.
    $domingoNoche = CarbonImmutable::parse('2026-10-18 21:30', 'America/Santiago');

    expect($domingoNoche->utc()->format('Y-m-d'))->toBe('2026-10-19')
        ->and(dias()->fechaLocal($domingoNoche->utc()))->toBe('2026-10-18')
        ->and(dias()->esHabil($domingoNoche->utc()))->toBeFalse();
});

it('keeps civil dates intact on days where the local clock skips or repeats an hour', function () {
    dosEstablecimientosConActivo();

    // 2026-09-06 00:00 does not exist in Santiago (clocks jump 24:00 -> 01:00).
    expect(dias()->fechaLocal(CarbonImmutable::parse('2026-09-06 01:00', 'America/Santiago')))->toBe('2026-09-06')
        ->and(dias()->fechaLocal(CarbonImmutable::parse('2026-09-05 23:59', 'America/Santiago')->addMinutes(2)))->toBe('2026-09-06')
        ->and(dias()->sumar('2026-09-06', 1)->format('Y-m-d'))->toBe('2026-09-07')
        ->and(dias()->restar('2026-09-06', 1)->format('Y-m-d'))->toBe('2026-09-04')
        // April: the 23:00-24:00 hour of Saturday 2026-04-04 happens twice.
        ->and(dias()->fechaLocal(CarbonImmutable::parse('2026-04-04 23:30 -03:00')))->toBe('2026-04-04')
        ->and(dias()->fechaLocal(CarbonImmutable::parse('2026-04-04 23:30 -04:00')))->toBe('2026-04-04')
        ->and(dias()->fechaLocal(CarbonImmutable::parse('2026-04-05 00:30 -04:00')))->toBe('2026-04-05');
});

// ---------------------------------------------------------------------------------------------
// Queries
// ---------------------------------------------------------------------------------------------

it('loads the calendar with one query and memoizes it for many computations', function () {
    [$a, $b] = dosEstablecimientosConActivo();
    cargarFeriados([[null, '2026-10-14'], ['A', '2026-11-02']], $a, $b);

    DB::flushQueryLog();
    DB::enableQueryLog();

    for ($i = 0; $i < 50; $i++) {
        $venc = CarbonImmutable::parse('2026-10-12 18:00', 'America/Santiago')->addDays($i);
        dias()->entre('2026-10-12', $venc);
        dias()->sumar($venc, 3);
    }

    $consultas = collect(DB::getQueryLog())->filter(fn (array $q): bool => str_contains($q['query'], 'dias_no_habiles'));
    DB::disableQueryLog();

    expect($consultas)->toHaveCount(1);
});

it('does not query again for another day inside the loaded window, and reloads when it leaves it', function () {
    [$a, $b] = dosEstablecimientosConActivo();
    cargarFeriados([], $a, $b);
    dias()->sumar('2026-10-12', 1);

    DB::enableQueryLog();
    DB::flushQueryLog();
    dias()->sumar('2026-10-20', 20);
    $dentro = collect(DB::getQueryLog())->filter(fn (array $q): bool => str_contains($q['query'], 'dias_no_habiles'))->count();

    DB::flushQueryLog();
    dias()->sumar('2028-01-03', 5);
    $fuera = collect(DB::getQueryLog())->filter(fn (array $q): bool => str_contains($q['query'], 'dias_no_habiles'))->count();
    DB::disableQueryLog();

    expect($dentro)->toBe(0)->and($fuera)->toBe(1);
});

it('registers the service once per request', function () {
    expect(app(DiasHabiles::class))->toBe(app(DiasHabiles::class));
});

// ---------------------------------------------------------------------------------------------
// Walks never leave the loaded calendar
// ---------------------------------------------------------------------------------------------

/** Every weekday from $desde to $hasta (inclusive) as an establecimiento non-business day. */
function cierre(int $estId, string $desde, string $hasta): void
{
    for ($d = CarbonImmutable::parse($desde, 'UTC'); $d <= CarbonImmutable::parse($hasta, 'UTC'); $d = $d->addDay()) {
        if ($d->isWeekday()) {
            diaNoHabil($estId, $d->format('Y-m-d'));
        }
    }
    dias()->olvidar();
}

it('skips a 14-day establecimiento vacation followed by a national holiday, with a memoized service', function () {
    [$a, $b] = dosEstablecimientosConActivo();
    cierre($a, '2026-10-13', '2026-10-26');
    diaNoHabil(null, '2026-10-27');
    dias()->olvidar();
    dias()->esHabil('2026-10-12'); // memoize first

    // Mon 12 +1: Oct 13-26 closed (vacation), Tue 27 national holiday -> Wed 28.
    expect(dias()->sumar('2026-10-12', 1)->format('Y-m-d'))->toBe('2026-10-28')
        ->and(dias()->restar('2026-10-28', 1)->format('Y-m-d'))->toBe('2026-10-12')
        ->and(dias()->sumar('2026-10-12', 1, $b)->format('Y-m-d'))->toBe('2026-10-13');
});

it('never evaluates a day outside the loaded calendar, even across a closure longer than the margin', function () {
    [$a, $b] = dosEstablecimientosConActivo();
    // About 190 consecutive closed weekdays (Oct 2026 - Jun 2027), then two national holidays.
    cierre($a, '2026-10-13', '2027-06-30');
    diaNoHabil(null, '2027-07-01');
    diaNoHabil(null, '2027-07-02');
    dias()->olvidar();

    expect(dias()->sumar('2026-10-12', 1)->format('Y-m-d'))->toBe('2027-07-05');

    dias()->olvidar();

    expect(dias()->restar('2027-07-05', 1)->format('Y-m-d'))->toBe('2026-10-12');

    dias()->olvidar();

    expect(dias()->entre('2026-10-12', '2027-07-05'))->toBe(1)
        ->and(dias()->entre('2027-07-05', '2026-10-12'))->toBe(-1);
});

it('extends the calendar with queries only for the missing range', function () {
    [$a, $b] = dosEstablecimientosConActivo();
    cierre($a, '2026-10-13', '2027-06-30');

    DB::enableQueryLog();
    DB::flushQueryLog();
    dias()->sumar('2026-10-12', 1);
    $consultas = collect(DB::getQueryLog())->filter(fn (array $q): bool => str_contains($q['query'], 'dias_no_habiles'));
    DB::disableQueryLog();

    expect($consultas->count())->toBeGreaterThan(1)->toBeLessThan(6);
});

// ---------------------------------------------------------------------------------------------
// Bounded dates
// ---------------------------------------------------------------------------------------------

dataset('fechas implausibles', [
    'far future' => ['2100-01-04'],
    'far past' => ['1990-01-01'],
    'year 9999' => ['9999-12-31'],
]);

it('rejects implausible dates before running any query', function (string $fecha) {
    dosEstablecimientosConActivo();
    DB::enableQueryLog();
    DB::flushQueryLog();

    $intentos = [
        fn () => dias()->entre('2026-10-12', $fecha),
        fn () => dias()->entre($fecha, '2026-10-12'),
        fn () => dias()->sumar($fecha, 1),
        fn () => dias()->restar($fecha, 1),
        fn () => dias()->esHabil($fecha),
        fn () => dias()->sumar(CarbonImmutable::parse($fecha, 'America/Santiago'), 1),
    ];
    foreach ($intentos as $intento) {
        expect($intento)->toThrow(InvalidArgumentException::class);
    }

    $consultas = DB::getQueryLog();
    DB::disableQueryLog();

    expect($consultas)->toBe([]);
})->with('fechas implausibles');

it('rejects an implausibly large amount of business days before running any query', function () {
    dosEstablecimientosConActivo();
    DB::enableQueryLog();
    DB::flushQueryLog();

    expect(fn () => dias()->sumar('2026-10-12', 1_000_000))->toThrow(InvalidArgumentException::class)
        ->and(fn () => dias()->restar('2026-10-12', 1_000_000))->toThrow(InvalidArgumentException::class)
        ->and(DB::getQueryLog())->toBe([]);

    DB::disableQueryLog();
});

it('still computes spans of many years inside the plausible window', function () {
    [$a, $b] = dosEstablecimientosConActivo();
    diaNoHabil(null, '2030-01-01');
    dias()->olvidar();

    // 2026-01-05 (Mon) to 2035-06-15 (Fri): 9 years of weekdays minus the one holiday.
    $semanas = intdiv((int) CarbonImmutable::parse('2026-01-05', 'UTC')->diffInDays(CarbonImmutable::parse('2035-06-11', 'UTC')), 7);

    expect(dias()->entre('2026-01-05', '2035-06-11'))->toBe($semanas * 5 - 1)
        ->and(dias()->sumar('2026-01-05', 2000)->format('Y-m-d'))->toBe('2033-09-06');
});

it('accepts the edges of the plausible window and rejects just beyond them', function () {
    dosEstablecimientosConActivo();
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-16 10:00', 'America/Santiago'));

    try {
        expect(dias()->esHabil('2046-10-16'))->toBeTrue()
            ->and(dias()->esHabil('2006-10-16'))->toBeTrue()
            ->and(fn () => dias()->esHabil('2046-10-17'))->toThrow(InvalidArgumentException::class)
            ->and(fn () => dias()->esHabil('2006-10-15'))->toThrow(InvalidArgumentException::class);
    } finally {
        CarbonImmutable::setTestNow();
    }
});
