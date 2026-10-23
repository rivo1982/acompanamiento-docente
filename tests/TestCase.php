<?php

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * The only schema a database-resetting trait may ever touch.
     */
    public const TEST_SCHEMA = 'acompanamiento_test';

    /**
     * Traits that drop, migrate or truncate the database before a test.
     *
     * @var list<class-string>
     */
    private const DESTRUCTIVE_DATABASE_TRAITS = [
        RefreshDatabase::class,
        DatabaseMigrations::class,
        DatabaseTruncation::class,
    ];

    /**
     * Guard the database before Laravel boots the testing traits.
     *
     * The guard lives here rather than in beforeRefreshingDatabase() because a
     * test class using RefreshDatabase would override that hook with the
     * trait's own empty implementation.
     *
     * @return array<class-string, class-string>
     */
    protected function setUpTraits()
    {
        $uses = $this->traitsUsedByTest ?? class_uses_recursive(static::class);

        if (array_intersect(self::DESTRUCTIVE_DATABASE_TRAITS, array_keys($uses)) !== []) {
            $this->ensureSafeTestDatabase();
        }

        return parent::setUpTraits();
    }

    /**
     * Refuse to continue unless the default connection is PostgreSQL limited
     * to the isolated test schema, so the host `public` schema is never wiped.
     *
     * @throws RuntimeException
     */
    protected function ensureSafeTestDatabase(): void
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");
        $searchPath = config("database.connections.{$connection}.search_path");

        if ($driver !== 'pgsql' || $searchPath !== self::TEST_SCHEMA) {
            throw new RuntimeException(sprintf(
                'Refusing to reset the database: tests must use a pgsql connection with search_path "%s" (got connection "%s", driver "%s", search_path "%s").',
                self::TEST_SCHEMA,
                (string) $connection,
                (string) $driver,
                is_scalar($searchPath) ? (string) $searchPath : json_encode($searchPath),
            ));
        }
    }
}
