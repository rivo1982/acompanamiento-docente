<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

it('refuses to refresh when the search_path is the host public schema', function () {
    config()->set('database.connections.pgsql.search_path', 'public');

    $this->ensureSafeTestDatabase();
})->throws(RuntimeException::class, 'acompanamiento_test');

it('refuses to refresh when the search_path includes public besides the test schema', function () {
    config()->set('database.connections.pgsql.search_path', 'acompanamiento_test,public');

    $this->ensureSafeTestDatabase();
})->throws(RuntimeException::class);

it('refuses to refresh when the default connection is not pgsql', function () {
    config()->set('database.default', 'sqlite');

    $this->ensureSafeTestDatabase();
})->throws(RuntimeException::class);

it('allows refreshing only the isolated acompanamiento_test schema', function () {
    $this->ensureSafeTestDatabase();

    expect(config('database.default'))->toBe('pgsql')
        ->and(config('database.connections.pgsql.search_path'))->toBe('acompanamiento_test');
});

/**
 * A RefreshDatabase test case whose refreshDatabase() only records the call, so the guard wiring
 * is exercised through the real setUpTraits() without migrating or dropping anything.
 *
 * @param  list<string|null>  $connectionsToTransact
 */
function guardWiringProbe(array $connectionsToTransact = [null]): TestCase
{
    $probe = new class('guardWiringProbe') extends TestCase
    {
        use RefreshDatabase;

        public bool $refreshAttempted = false;

        /** @var list<string|null> */
        public array $connectionsToTransact = [null];

        public function refreshDatabase(): void
        {
            $this->refreshAttempted = true;
        }

        /** @return array<class-string, class-string> */
        public function runSetUpTraits(): array
        {
            return $this->setUpTraits();
        }
    };
    $probe->connectionsToTransact = $connectionsToTransact;

    return $probe;
}

it('runs the guard before RefreshDatabase refreshes anything', function () {
    config()->set('database.connections.pgsql.search_path', 'acompanamiento_guard_probe');
    $probe = guardWiringProbe();

    expect(fn () => $probe->runSetUpTraits())->toThrow(RuntimeException::class, 'acompanamiento_test')
        ->and($probe->refreshAttempted)->toBeFalse();
});

it('lets RefreshDatabase refresh once the guard passes', function () {
    $probe = guardWiringProbe();

    $probe->runSetUpTraits();

    expect($probe->refreshAttempted)->toBeTrue();
});

it('guards every connection RefreshDatabase would transact, not only the default', function (array $conexion) {
    config()->set('database.connections.secundaria', [...config('database.connections.pgsql'), ...$conexion]);
    $probe = guardWiringProbe([null, 'secundaria']);

    expect(fn () => $probe->runSetUpTraits())->toThrow(RuntimeException::class, 'secundaria')
        ->and($probe->refreshAttempted)->toBeFalse();
})->with([
    'public search_path' => [['search_path' => 'public']],
    'not pgsql' => [['driver' => 'sqlite']],
]);

it('accepts a non-default connection limited to the test schema', function () {
    config()->set('database.connections.secundaria', config('database.connections.pgsql'));
    $probe = guardWiringProbe(['pgsql', 'secundaria']);

    $probe->runSetUpTraits();

    expect($probe->refreshAttempted)->toBeTrue();
});

it('refuses a connection that is not configured at all', function () {
    $probe = guardWiringProbe([null, 'inexistente']);

    expect(fn () => $probe->runSetUpTraits())->toThrow(RuntimeException::class, 'inexistente');
});
