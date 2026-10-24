<?php

use App\Support\HostSchemaLoader;

/** One pg_dump TOC entry with the given name, type and SQL. */
function entradaDeDump(string $nombre, string $tipo, string $sql): string
{
    return "--\n-- Name: {$nombre}; Type: {$tipo}; Schema: public; Owner: -\n--\n\n{$sql}\n\n\n";
}

function tablaMinima(): string
{
    return entradaDeDump('establecimientos', 'TABLE', "CREATE TABLE public.establecimientos (\n    id bigint NOT NULL\n);");
}

it('keeps comment and SET lines inside a tagged dollar-quoted body', function (string $etiqueta) {
    $funcion = entradaDeDump('f()', 'FUNCTION', <<<SQL
CREATE FUNCTION public.f() RETURNS trigger
    LANGUAGE plpgsql
    AS {$etiqueta}
BEGIN
-- comentario dentro del cuerpo
UPDATE public.establecimientos
SET id = id;
    RETURN NEW;
END;
{$etiqueta};
SQL);

    $sql = (new HostSchemaLoader('unused'))->transform($funcion.tablaMinima());

    expect($sql)->toContain("-- comentario dentro del cuerpo\nUPDATE establecimientos\nSET id = id;")
        ->toContain("END;\n{$etiqueta};");
})->with([
    'anonymous tag' => '$$',
    'underscore tag' => '$_$',
    'named tag' => '$cuerpo$',
]);

it('does not close a tagged body on a different dollar quote inside it', function () {
    $funcion = entradaDeDump('f()', 'FUNCTION', <<<'SQL'
CREATE FUNCTION public.f() RETURNS text
    LANGUAGE plpgsql
    AS $cuerpo$
BEGIN
    RETURN $$texto$$;
-- sigue dentro del cuerpo
END;
$cuerpo$;
SQL);

    $sql = (new HostSchemaLoader('unused'))->transform($funcion.tablaMinima());

    expect($sql)->toContain("RETURN \$\$texto\$\$;\n-- sigue dentro del cuerpo\nEND;");
});

it('drops comment and SET lines again once a tagged body is closed', function () {
    $funcion = entradaDeDump('f()', 'FUNCTION', <<<'SQL'
CREATE FUNCTION public.f() RETURNS trigger
    LANGUAGE plpgsql
    AS $_$
BEGIN
    RETURN NEW;
END;
$_$;
-- comentario fuera del cuerpo
SET check_function_bodies = false;
SQL);

    $sql = (new HostSchemaLoader('unused'))->transform($funcion.tablaMinima());

    expect($sql)->not->toContain('comentario fuera del cuerpo')
        ->not->toContain('check_function_bodies');
});

it('does not treat positional parameters as dollar quotes', function () {
    $funcion = entradaDeDump('f()', 'FUNCTION', <<<'SQL'
CREATE FUNCTION public.f() RETURNS trigger
    LANGUAGE plpgsql
    AS $_$
BEGIN
    EXECUTE format('SELECT 1 WHERE id = $1') USING NEW;
-- dentro
END;
$_$;
SQL);

    $sql = (new HostSchemaLoader('unused'))->transform($funcion.tablaMinima());

    expect($sql)->toContain("USING NEW;\n-- dentro\nEND;");
});

it('fails closed on a dump without usable tables', function (string $dump) {
    (new HostSchemaLoader('unused'))->transform($dump);
})->throws(RuntimeException::class)->with([
    'empty' => '',
    'whitespace only' => "  \n\n ",
    'headerless SQL' => "CREATE TABLE public.establecimientos (id bigint);\n",
    'preamble only' => "--\n-- PostgreSQL database dump\n--\n\nSET statement_timeout = 0;\n",
    'no table entries' => entradaDeDump('f()', 'FUNCTION', "CREATE FUNCTION public.f() RETURNS integer\n    LANGUAGE sql\n    AS \$\$ SELECT 1 \$\$;"),
    'only the skipped migrations table' => entradaDeDump('migrations', 'TABLE', "CREATE TABLE public.migrations (\n    id integer NOT NULL\n);"),
]);

it('fails closed on an unterminated dollar-quoted body', function () {
    $funcion = entradaDeDump('f()', 'FUNCTION', "CREATE FUNCTION public.f() RETURNS trigger\n    LANGUAGE plpgsql\n    AS \$_\$\nBEGIN\n    RETURN NEW;\nEND;");

    (new HostSchemaLoader('unused'))->transform($funcion.tablaMinima());
})->throws(RuntimeException::class, 'dollar');
