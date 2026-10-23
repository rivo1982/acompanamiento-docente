<?php

use App\Support\HostSchemaLoader;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/** A trimmed pg_dump --schema-only of `public` with one entry of every kind the loader handles. */
function sampleHostDump(): string
{
    return <<<'SQL'
--
-- PostgreSQL database dump
--

SET statement_timeout = 0;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;

--
-- Name: public; Type: SCHEMA; Schema: -; Owner: -
--

CREATE SCHEMA public;


--
-- Name: SCHEMA public; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON SCHEMA public IS 'standard public schema';


--
-- Name: establecimiento_id_inmutable(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.establecimiento_id_inmutable() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    RETURN NEW;
END;
$$;


--
-- Name: establecimientos; Type: TABLE; Schema: public; Owner: -
--

SET default_tablespace = '';

CREATE TABLE public.establecimientos (
    id bigint NOT NULL
);


--
-- Name: establecimientos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.establecimientos_id_seq
    START WITH 1;


--
-- Name: establecimientos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.establecimientos ALTER COLUMN id SET DEFAULT nextval('public.establecimientos_id_seq'::regclass);


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.migrations (
    id integer NOT NULL
);


--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: establecimientos establecimientos_inmutable; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER establecimientos_inmutable BEFORE UPDATE ON public.establecimientos FOR EACH ROW EXECUTE FUNCTION public.establecimiento_id_inmutable();


--
-- PostgreSQL database dump complete
--

SQL;
}

/** A connection double that records whether any SQL would be executed. */
function fakeHostConnection(string $searchPath, bool $hostTablePresent): Connection
{
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getConfig')->with('search_path')->andReturn($searchPath);
    $connection->shouldReceive('scalar')->andReturn($hostTablePresent);
    $connection->shouldNotReceive('unprepared', 'statement', 'insert', 'update', 'delete', 'affectingStatement');

    return $connection;
}

it('removes every public qualifier, including the ones inside string literals', function () {
    $sql = (new HostSchemaLoader('unused'))->transform(sampleHostDump());

    expect($sql)->not->toMatch('/\bpublic\./')
        ->toContain('CREATE TABLE establecimientos (')
        ->toContain("nextval('establecimientos_id_seq'::regclass)")
        ->toContain('ON establecimientos FOR EACH ROW EXECUTE FUNCTION establecimiento_id_inmutable()');
});

it('drops the schema statements and the pg_dump session settings', function () {
    $sql = (new HostSchemaLoader('unused'))->transform(sampleHostDump());

    expect($sql)->not->toContain('CREATE SCHEMA')
        ->not->toContain('COMMENT ON SCHEMA')
        ->not->toContain('set_config')
        ->not->toMatch('/^SET /m');
});

it('creates functions with CREATE OR REPLACE so they survive dropping all tables', function () {
    $sql = (new HostSchemaLoader('unused'))->transform(sampleHostDump());

    expect($sql)->toContain('CREATE OR REPLACE FUNCTION establecimiento_id_inmutable()')
        ->not->toMatch('/CREATE FUNCTION/');
});

it('skips the migrations table because Laravel owns it', function () {
    $sql = (new HostSchemaLoader('unused'))->transform(sampleHostDump());

    expect($sql)->not->toContain('migrations')
        ->toContain('CREATE TABLE establecimientos');
});

it('rejects a snapshot with an object kind it cannot reload safely', function () {
    $dump = sampleHostDump()."\n--\n-- Name: estado; Type: TYPE; Schema: public; Owner: -\n--\n\nCREATE TYPE public.estado AS ENUM ('a');\n";

    (new HostSchemaLoader('unused'))->transform($dump);
})->throws(RuntimeException::class, 'TYPE');

it('transforms the real host snapshot into 53 tables and 15 functions without public', function () {
    $sql = HostSchemaLoader::fromDefaultSnapshot()->transform(
        file_get_contents(database_path('schema/vinkured-host-schema.sql'))
    );

    expect(preg_match_all('/^CREATE TABLE /m', $sql))->toBe(53)
        ->and(preg_match_all('/^CREATE OR REPLACE FUNCTION /m', $sql))->toBe(15)
        ->and(preg_match_all('/^CREATE TRIGGER /m', $sql))->toBe(19)
        ->and($sql)->not->toMatch('/\bpublic\./');
});

it('refuses to load when the search_path includes public', function (string $searchPath) {
    $connection = fakeHostConnection($searchPath, hostTablePresent: false);

    expect(fn () => HostSchemaLoader::fromDefaultSnapshot()->load($connection))
        ->toThrow(RuntimeException::class, 'public');
})->with([
    'only public' => 'public',
    'public after the module schema' => 'acompanamiento,public',
    'quoted public' => '"$user", "public"',
    'empty search_path defaults to public' => '',
]);

it('does nothing when the host tables are already visible in the search_path', function () {
    $connection = fakeHostConnection('acompanamiento,public', hostTablePresent: true);

    expect(HostSchemaLoader::fromDefaultSnapshot()->load($connection))->toBeFalse();
});

it('is a no-op on the migrated test schema, where establecimientos already exists', function () {
    $antes = DB::scalar("select count(*) from information_schema.tables where table_schema = 'acompanamiento_test'");

    expect(HostSchemaLoader::fromDefaultSnapshot()->load(DB::connection()))->toBeFalse()
        ->and(DB::scalar("select count(*) from information_schema.tables where table_schema = 'acompanamiento_test'"))->toBe($antes);
});
