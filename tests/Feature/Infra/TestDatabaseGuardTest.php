<?php

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
