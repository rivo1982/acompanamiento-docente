<?php

namespace App\Support;

use Illuminate\Database\Concerns\ParsesSearchPath;
use Illuminate\Database\Connection;
use RuntimeException;

/**
 * Loads the Vinkured host schema snapshot (`pg_dump --schema-only --schema=public`) into the
 * schema of the current search_path, so the module's tests run against the real host structure
 * without ever touching `public`.
 *
 * - No-op when the host is already visible (marker table `establecimientos` resolves through
 *   the search_path): the dev/integration case, where the host lives in `public`.
 * - Refuses to load when the search_path includes `public`.
 * - Otherwise loads the snapshot without `public.` qualifiers, without the schema statements and
 *   pg_dump session settings, with `CREATE OR REPLACE FUNCTION` (functions survive Laravel's
 *   dropAllTables) and without the `migrations` table, which Laravel owns.
 */
final class HostSchemaLoader
{
    use ParsesSearchPath;

    public const MARKER_TABLE = 'establecimientos';

    /** @var list<string> */
    public const SKIPPED_TABLES = ['migrations'];

    /**
     * Dump entry types the loader knows how to reload repeatedly. Anything else (types, views,
     * extensions, table data) would survive dropAllTables or carry rows, so it is rejected.
     *
     * @var list<string>
     */
    private const SUPPORTED_ENTRY_TYPES = [
        'TABLE', 'SEQUENCE', 'SEQUENCE OWNED BY', 'DEFAULT', 'CONSTRAINT', 'FK CONSTRAINT',
        'INDEX', 'FUNCTION', 'TRIGGER', 'SCHEMA', 'COMMENT',
    ];

    public function __construct(private readonly string $snapshotPath) {}

    public static function fromDefaultSnapshot(): self
    {
        return new self(database_path('schema/vinkured-host-schema.sql'));
    }

    /**
     * Load the snapshot through the given connection. Returns false when the host schema was
     * already visible and nothing was executed.
     *
     * @throws RuntimeException
     */
    public function load(Connection $connection): bool
    {
        if ($this->hostSchemaIsVisible($connection)) {
            return false;
        }

        $this->ensureSearchPathExcludesPublic($connection);

        $dump = file_get_contents($this->snapshotPath);

        if ($dump === false) {
            throw new RuntimeException("Cannot read the host schema snapshot at {$this->snapshotPath}.");
        }

        $connection->unprepared($this->transform($dump));

        return true;
    }

    /**
     * Turn a pg_dump schema-only dump of `public` into SQL that creates the same objects in the
     * current schema.
     *
     * @throws RuntimeException
     */
    public function transform(string $dump): string
    {
        $statements = [];

        foreach ($this->entries($dump) as $entry) {
            if ($this->isDiscarded($entry)) {
                continue;
            }

            $sql = $entry['sql'];

            if ($entry['type'] === 'FUNCTION') {
                $sql = preg_replace('/^CREATE FUNCTION /m', 'CREATE OR REPLACE FUNCTION ', $sql);
            }

            $statements[] = trim(preg_replace('/(?<![\w."])public\./', '', $sql));
        }

        return implode("\n\n", $statements)."\n";
    }

    /**
     * Split the dump into its table-of-contents entries. The preamble before the first entry
     * (pg_dump session settings, including an empty search_path) is dropped.
     *
     * @return list<array{name: string, type: string, sql: string}>
     *
     * @throws RuntimeException
     */
    private function entries(string $dump): array
    {
        $dump = str_replace("\r\n", "\n", $dump);
        $chunks = preg_split('/^(?=--\n-- (?:Data for )?Name: )/m', $dump);
        array_shift($chunks);

        $entries = [];

        foreach ($chunks as $chunk) {
            if (! preg_match('/\A--\n-- (?:Data for )?Name: (.+?); Type: (.+?); Schema: /', $chunk, $header)) {
                throw new RuntimeException('Unrecognised pg_dump entry header in the host schema snapshot.');
            }

            if (! in_array($header[2], self::SUPPORTED_ENTRY_TYPES, true)) {
                throw new RuntimeException("The host schema snapshot contains an unsupported {$header[2]} entry ({$header[1]}).");
            }

            $entries[] = [
                'name' => $header[1],
                'type' => $header[2],
                'sql' => $this->withoutCommentsAndMetaCommands($chunk),
            ];
        }

        return $entries;
    }

    /**
     * @param  array{name: string, type: string, sql: string}  $entry
     */
    private function isDiscarded(array $entry): bool
    {
        if ($entry['type'] === 'SCHEMA' || ($entry['type'] === 'COMMENT' && str_starts_with($entry['name'], 'SCHEMA '))) {
            return true;
        }

        foreach (self::SKIPPED_TABLES as $table) {
            if (preg_match('/\bpublic\.'.preg_quote($table, '/').'(?:_id_seq)?(?![\w])/', $entry['sql'])) {
                return true;
            }
        }

        return $entry['sql'] === '';
    }

    /**
     * Drop, outside dollar-quoted bodies, full-line `--` comments, pg_dump session `SET` lines
     * and psql meta-commands (e.g. `\restrict`), which PDO cannot execute.
     */
    private function withoutCommentsAndMetaCommands(string $chunk): string
    {
        $kept = [];
        $insideBody = false;

        foreach (explode("\n", $chunk) as $line) {
            if (! $insideBody && (str_starts_with($line, '--') || str_starts_with($line, 'SET ') || str_starts_with($line, '\\'))) {
                continue;
            }

            if (substr_count($line, '$$') % 2 === 1) {
                $insideBody = ! $insideBody;
            }

            $kept[] = $line;
        }

        return trim(implode("\n", $kept));
    }

    private function hostSchemaIsVisible(Connection $connection): bool
    {
        return (bool) $connection->scalar('select to_regclass(?) is not null', [self::MARKER_TABLE]);
    }

    /**
     * @throws RuntimeException
     */
    private function ensureSearchPathExcludesPublic(Connection $connection): void
    {
        $searchPath = $connection->getConfig('search_path') ?: 'public';
        $schemas = array_map(strtolower(...), $this->parseSearchPath($searchPath));

        if ($schemas === [] || in_array('public', $schemas, true)) {
            throw new RuntimeException(sprintf(
                'Refusing to load the host schema snapshot: the search_path "%s" includes public, which belongs to the host.',
                is_array($searchPath) ? implode(',', $searchPath) : (string) $searchPath,
            ));
        }
    }
}
