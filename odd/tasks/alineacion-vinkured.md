# Feature: Align coaching module with Vinkured conventions

## Objective
Keep the module as a separate repository but make it structurally compatible with the host
platform Vinkured (/srv/vinkured) so a later integration is a merge, not a rewrite.

## Problem / Why
T1–T3 (branch feat/data-model) used English names, its own users/schools/organizations tables,
SQLite and PHPUnit. Vinkured already owns sostenedores, establecimientos, personas,
funcionarios, funciones, usuarios and membresias, isolates every business table by
establecimiento_id, uses PostgreSQL 17, Pest 4 and Spanish identifiers. Duplicated host tables
would make integration costly and risky.

## Decisions (user)
- Stay a separate repository, adopt Vinkured conventions (Spanish, PostgreSQL, Pest).
- Develop against database `roberto_dev` (PostgreSQL 17, local socket, role `roberto`, no
  CREATEDB), which is a dump of the current Vinkured database. Its `public` schema is the
  Vinkured host schema and is NEVER dropped or rewritten by this module.
- Ex-observers lose access to 6.4 reports (from previous feature).

## Constraints and technical decisions
- Host tables are read-only for the module: the module only ADDS its own tables (module-owned
  migrations) referencing host tables (establecimientos, funcionarios, usuarios, cursos,
  asignaturas, grados/niveles_ensenanza).
- Tests run in a separate schema `acompanamiento_test` inside `roberto_dev`
  (search_path limited to that schema). The Vinkured structure is loaded there from a
  schema-only dump (no data), so tests never touch real rows.
- Destructive artisan commands (db:wipe, migrate:fresh/reset/refresh) are prohibited outside the
  testing environment.
- Tenancy: every module business table carries establecimiento_id + UNIQUE(establecimiento_id,
  id) + composite RESTRICT FKs, following Vinkured's PerteneceAEstablecimiento pattern.
- Roles derived like Vinkured's RolesDelUsuario (funciones.clave / categoria, membresia), no
  role column on usuarios.
- Privacy: roberto_dev may contain real personal data (personas, estudiantes, casos). Do not
  read or print row contents; only structure and counts. User advised to confirm anonymization.
- Commits: Conventional Commits, no AI attribution (Vinkured CLAUDE.md + user rule).

## Tasks
- [x] A1 Infrastructure: PostgreSQL connection to roberto_dev, test schema acompanamiento_test
  with Vinkured schema-only snapshot, prohibit destructive commands, Pest 4 installed with
  existing PHPUnit tests still passing.
- [ ] A2 Rename module domain to Spanish and re-base on host tables (Establecimiento,
  Funcionario, Usuario...) with tenancy pattern; drop module copies of host tables.
- [ ] A3 Roles derived from funciones/membresias (directivo, UTP, observador).
- [ ] A4 Port tests to Pest, port policies, catalog seeder on new tables.
- [ ] A5 Resume original plan (T4+) on the aligned base.

## Progress / Evidence
- Branch feat/vinkured-alignment created from feat/data-model @ 08f1888.
- A1 route: delegated writer (trigger: 2+ non-trivial files).
- A1 evidence:
  - Schema `acompanamiento_test` created in roberto_dev (additive `CREATE SCHEMA IF NOT EXISTS`).
  - pgsql `search_path` = `env('DB_SEARCH_PATH', 'public')`; phpunit.xml points tests at
    roberto_dev with `DB_SEARCH_PATH=acompanamiento_test`. Laravel's PostgresBuilder
    dropAllTables/Views/Types only touch `getCurrentSchemaListing()` (parsed search_path).
  - Guard in `Tests\TestCase::setUpTraits()` (not `beforeRefreshingDatabase()`, which the
    RefreshDatabase trait would override with its empty hook) throws unless default connection
    is pgsql with search_path exactly `acompanamiento_test`, for RefreshDatabase,
    DatabaseMigrations and DatabaseTruncation.
  - `DB::prohibitDestructiveCommands(! testing)` in AppServiceProvider::boot().
  - RED observed: 8 failed + 1 error in tests/Feature/Infra before implementation; GREEN after.
  - Pest v4.7.8 + pest-plugin-laravel installed (phpunit 12.5.33); tests/Pest.php extends
    Tests\TestCase in Feature without global RefreshDatabase.
  - `php artisan test --compact` and `vendor/bin/pest --compact`: 93 passed, 269 assertions
    (all T1–T3 tests pass on PostgreSQL in acompanamiento_test).
  - `vendor/bin/pint --dirty --format agent`: passed.
  - `database/schema/vinkured-host-schema.sql`: pg_dump schema-only of public (54 CREATE
    TABLE, 0 COPY/INSERT lines). Objects are `public.`-qualified; A2 must rewrite or load it
    accordingly. Not auto-loaded (not named pgsql-schema.sql).
  - public table count before/after all test runs: 54 / 54.

## Next step
A2 — load the host schema snapshot into acompanamiento_test and re-base module domain.
