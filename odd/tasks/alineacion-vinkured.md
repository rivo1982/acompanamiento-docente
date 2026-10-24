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
- Dev environment will use search_path 'acompanamiento,public' (module tables + own migrations
  table in schema acompanamiento, host tables read from public); not configured yet.

## Tasks
- [x] A1 Infrastructure: PostgreSQL connection to roberto_dev, test schema acompanamiento_test
  with Vinkured schema-only snapshot, prohibit destructive commands, Pest 4 installed with
  existing PHPUnit tests still passing.
- [x] A2a Host schema loader + host model layer.
- [x] A2b Re-base module domain in Spanish on host tables (Establecimiento, Funcionario,
  Usuario...) with tenancy pattern; drop module copies of host tables (users, organizations,
  schools); register EstablecimientoActual/RolesDelUsuario as scoped and the middleware.
- [x] A2.1 Review follow-ups (A1+A2a review base c424e2b, approved + acknowledged): ~~scoped
  binding so paraTrabajo() state is shared~~ (done in A2b); tenancy tests for updating hook,
  tampered/foreign/suspended session establecimiento (refrescar → null); HostSchemaLoader:
  track tagged dollar quotes ($_$, $tag$), fail closed on empty/headerless snapshot; prove the
  setUpTraits guard wiring runs before RefreshDatabase and covers non-default connections.
- [x] A2.2 Review follow-ups (A2b review base 59b6e7a, 4 lenses, approved + acknowledged):
  RespuestaAcompanamiento write guard — reject indicador not in snapshot and writes after
  finalizado (R4/R3 WARNING); observer eligibility on AsignacionAcompanamiento (R1-001
  WARNING, fold into A3); remove estado/finalizado_en/duracion_minutos/pauta_snapshot from
  fillable (R1-002); single source for congratulation text in NivelLogro (R2-001); null-safe
  creating hook when pauta not visible (R4); default estado via enum (R2-002); named constant
  for factory school year (R2-003); distinct exception for incomplete snapshot entry (R2-004);
  truncate iniciado_en to seconds before computing duration (R3). R1-001 deferred to A3.
- [x] A2.3 Review follow-ups (A2.1+A2.2 review base a60b468, approved + acknowledged):
  enforce "no respuesta writes on finalizado acompanamiento / indicador outside snapshot" at the
  database level (trigger, like Vinkured's integrity triggers) so concurrent finalizar() and
  query-builder bulk writes cannot bypass it (R3-001, R3-002); make the truncation test
  discriminating or drop it (R3-003); remove vacuous try/catch assertion (R3-004).
- [x] A3 Roles derived from funciones/membresias (directivo, UTP, observador).
  DECIDED (user, 2026-10-24): module admin = any funcionario with a función of categoria
  'directivo' (RolesDelUsuario::esDirectivo). Jefe de UTP will be added upstream in Vinkured's
  FuncionesBaseSeeder as clave 'jefe_utp', categoria 'directivo' (proposal sent by user), so it
  is covered automatically; the module adds no UTP data of its own.
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
  - Commits: c424e2b (generated: composer.lock + schema snapshot), 1c2c5cd (A1 code).
    Native review: full range since 08f1888 exceeded lens budget (generated files); code-only
    range assessed medium / under_budget (163 lines) → pending in slice until a later commit
    reaches the delivery budget. Last reviewed boundary stays 08f1888.

- A2a route: delegated writer (trigger: 2+ non-trivial files).
- A2a evidence:
  - `database/migrations/0000_00_00_000000_load_vinkured_host_schema.php` runs first and calls
    `App\Support\HostSchemaLoader`: no-op when `to_regclass('establecimientos')` resolves in the
    search_path; otherwise refuses if the search_path includes public (empty = public); otherwise
    splits the dump by pg_dump TOC entries, drops preamble/SET/psql meta lines, SCHEMA and
    COMMENT ON SCHEMA entries and every entry touching `migrations`/`migrations_id_seq`, uses
    CREATE OR REPLACE FUNCTION, strips `public.` qualifiers (also inside `nextval('...')`), and
    rejects unsupported entry types (TYPE, VIEW, EXTENSION, TABLE DATA...). down() is a no-op.
  - Default Laravel migrations trimmed: users migration keeps only `users`; cache and jobs
    migrations deleted (host owns cache, cache_locks, jobs, job_batches, failed_jobs,
    sessions, password_reset_tokens). No module migration collides with a host table.
  - Host classes copied verbatim from /srv/vinkured/app with a `@host-owned` PHPDoc tag:
    Usuario, Membresia, Establecimiento, Persona, Funcionario, Funcion, FuncionarioFuncion,
    AsignacionDocente, Curso, AnioEscolar, Grado, Matricula, Estudiante (closure of
    Curso::matriculas), Concerns/PerteneceAEstablecimiento, Scopes/EstablecimientoActivoScope,
    Support/EstablecimientoActual, Support/RolesDelUsuario, Exceptions/EstablecimientoAjeno,
    Exceptions/SinEstablecimientoActivo, Http/Middleware/EstablecimientoActivo. Vinkured has no
    NivelEnsenanza model.
  - Host helpers ported into tests/Pest.php (same names). Deviation: newUsuario builds the
    UsuarioFactory attributes with forceFill (factory not copied; database/factories outside
    A2a surface).
  - Tests: tests/Feature/Infra/HostSchemaLoaderTest.php (transform on a sample dump + real
    snapshot, refusal with a mocked connection, no-op), HostSchemaLoadedTest.php (53 host
    tables, 15 functions, 19 triggers, trigger enforced), tests/Feature/Host/HostHelpersTest.php,
    tests/Feature/Host/AislamientoPorEstablecimientoTest.php (ported from Vinkured
    tests/Feature/Modelos/AislamientoPorEstablecimientoTest.php: fail closed reading/creating,
    explicit ajeno rejected, Funcion global; dataset reduced to copied models).
  - RED: 34 new tests, 8 failed + 26 errors (loader class and helpers missing). GREEN: 43 passed.
  - `vendor/bin/pest --compact`: 127 passed, 433 assertions. Pint: passed (fixed 2 test files).
  - acompanamiento_test table count after suite: 66 (53 host + migrations + 12 module).
  - public table count before/after: 54 / 54.
  - Deviations: Not registered yet (outside surface, for A2b/A3): scoped bindings of
    EstablecimientoActual/RolesDelUsuario in AppServiceProvider, the `establecimiento.activo`
    middleware alias and routes `login`/`establecimientos.seleccionar` it redirects to, auth
    provider model Usuario. Usuario references Database\Factories\UsuarioFactory only in its
    docblock; `Usuario::factory()` is unavailable until the factory is added.

- A2b route: delegated writer (trigger: 2+ non-trivial files).
- A2b evidence:
  - Deleted (spec kept in git history, b3397fc): models Organization, School, User,
    RubricTemplate, RubricSection, Indicator, TeachingTip, Rubric, CoachingAssignment,
    Observation, ObservationScore; enums AchievementLevel, ObservationStatus, OrganizationType,
    StaffRole, TeachingTipTarget; exceptions IndicatorNotInSnapshot, InvalidCoachingAssignment,
    ObservationAlreadyFinished; all app/Policies + Gate registration; all old factories; the
    Laravel `users` migration and the 12 T1 migrations; tests Feature/Models, Feature/Policies,
    Feature/Seeders, Unit/AchievementLevelTest.
  - Global catalog tables: pautas_base, ambitos (unique pauta_base_id+posicion), indicadores
    (numero unique), apoyos_pedagogicos (nivel_objetivo CHECK logrado/destacado).
  - Tenant tables (establecimiento_id + UNIQUE(establecimiento_id,id) + composite RESTRICT FKs):
    pautas (softDeletes), indicador_pauta, observadores, asignaciones_acompanamiento (CHECK
    observador_id <> docente_id), acompanamientos (estado CHECK, pauta_snapshot jsonb,
    asignatura_id simple FK to global asignaturas), respuestas_acompanamiento (nivel CHECK).
  - Models PautaBase, Ambito, Indicador, ApoyoPedagogico, Pauta, IndicadorPauta (custom Pivot
    with PerteneceAEstablecimiento so attach() gets the active establecimiento), Observador,
    AsignacionAcompanamiento, Acompanamiento, RespuestaAcompanamiento; enums NivelLogro,
    EstadoAcompanamiento, NivelObjetivoApoyo; exceptions AcompanamientoYaFinalizado,
    IndicadorFueraDelSnapshot. Preserved: Pauta::toSnapshot(), creating hook (iniciado_en +
    snapshot only when missing), finalizar() conditional UPDATE WHERE estado=en_curso + refresh,
    retroalimentacionAutomatica() from snapshot only + missing/incomplete exceptions, Felicitaciones.
  - Nivel de enseñanza derived by Acompanamiento::nivelEnsenanza() (curso → grado →
    niveles_ensenanza, query builder; host has no NivelEnsenanza model), not stored.
  - CatalogSeeder ported (Spanish columns, idempotent, sections by position, seedCatalog(array),
    one transaction); DatabaseSeeder only calls it.
  - Factories for all module models; tenant factories use the active establecimiento and
    database/factories/Concerns/CreaFilasDelAnfitrion (DB::table host rows) for funcionarios,
    cursos, asignaturas.
  - A2.1 item 1: EstablecimientoActual and RolesDelUsuario registered scoped; alias
    `establecimiento.activo` in bootstrap/app.php; auth provider model env('AUTH_MODEL',
    Usuario::class). DB::prohibitDestructiveCommands kept.
  - Tests: tests/Unit/NivelLogroTest.php, tests/Feature/Modulo/{CatalogoSeeder,Pauta,
    Acompanamiento,AsignacionAcompanamiento,AislamientoDelModulo}Test.php (port of Vinkured
    TenantIsolationGuardTest for module tables + enum/CHECK parity),
    tests/Feature/Configuracion/IntegracionConAnfitrionTest.php.
  - RED: 100 tests, 47 passed, 10 failed, 43 errors (missing classes/tables, unscoped bindings,
    no alias, auth model User). GREEN: `vendor/bin/pest --compact` 100 passed, 341 assertions.
  - `vendor/bin/pint --dirty --format agent`: passed (fixed 6 migrations first).
  - acompanamiento_test tables: 64 (53 host + migrations + 10 module).
  - Grep for School/Organization/RubricTemplate/Observation: only 2 hits inside
    database/seeders/data/catalog.json bibliography text (read-only, justified).
  - public table count before/after: 54 / 54.
  - Deviations: indicadores.numero has no CHECK 1..60 (factories use 1000+ so they never collide
    with the catalog); catalog child FKs are RESTRICT (were CASCADE); no Asignatura model (host has
    none), asignatura_id only. Old CoachingAssignment saving validation (flagged observer, same
    school) is not ported: the DB enforces same establecimiento and distinct pair; observer
    eligibility moves to A3.
  - Open for A3: observer eligibility (directivo/UTP derived from funciones + observadores flag)
    and its validation on asignaciones/acompanamientos; rebuild policies/gates on RolesDelUsuario;
    OPEN QUESTION: sostenedor-level access (old OrganizationAdmin) is not modeled by Vinkured
    membresias (per establecimiento only) — needs a product/host decision.

- A2.1+A2.2 route: delegated writer (trigger: 2+ non-trivial files).
- A2.1 evidence:
  - Tenancy (tests only, host-owned files untouched): tests/Feature/Host/
    EstablecimientoActualFallaCerradoTest.php — updating hook rejects moving a Pauta to another
    establecimiento (row unchanged); tampered session values (non-numeric, numeric prefix,
    negative, zero, 19 digits, float, array), a foreign establecimiento and a membresia suspended
    after selection resolve to null (refrescar, id, next request) and module reads throw
    SinEstablecimientoActivo. 11 tests green on first run: no host-owned defect found.
  - HostSchemaLoader tracks any dollar-quote tag ($$, $_$, $tag$; only the opening tag closes,
    $1 is not a tag), throws on an unterminated body, an empty/whitespace dump, a headerless dump
    and a dump with no host table (also only `migrations`). tests/Unit/HostSchemaLoaderTransformTest.php.
    RED: 11 failed / 2 passed. GREEN: 13 passed; real snapshot still 53 tables / 15 functions /
    19 triggers (the real snapshot has one $_$ body, exigir_padre_plantilla).
  - Guard: Tests\TestCase::ensureSafeTestDatabase() checks the default connection plus every
    connection in connectionsToTransact() / connectionsToTruncate() (null = default). Wiring
    proven with an anonymous RefreshDatabase probe whose refreshDatabase() only records the call:
    misconfigured search_path throws from setUpTraits() and refresh is never attempted; correct
    config reaches refresh; an unsafe or unknown non-default connection throws. RED: 3 failed.
- A2.2 evidence (R1-001 observer eligibility deferred to A3):
  - RespuestaAcompanamiento saving/deleting guard reads the acompanamiento from the database:
    indicador outside the snapshot -> IndicadorFueraDelSnapshot; any create/update/delete (also
    moving into or out of) a finalizado acompanamiento -> AcompanamientoYaFinalizado::paraRespuestas.
  - Acompanamiento: estado/finalizado_en/duracion_minutos/pauta_snapshot removed from $fillable;
    default estado EstadoAcompanamiento::EnCurso->value; creating hook throws PautaNoDisponible for
    a missing, foreign or soft-deleted pauta (was a null method call); iniciado_en and finalizado_en
    truncated with startOfSecond(). The truncation test was already green (the datetime cast
    stores Y-m-d H:i:s), the change makes it explicit.
  - NivelLogro: significado() = description + mensajeFelicitacion(); Logrado felicitación is now
    'Felicitaciones.' (exact spec texts kept). CreaFilasDelAnfitrion::ANIO_ESCOLAR_DE_FABRICA = 2026.
    EntradaDeSnapshotIncompleta replaces IndicadorFueraDelSnapshot::incompleto().
  - RespuestaAcompanamientoFactory default now attaches one indicador to the pauta and answers it.
  - RED: 14 failed + 1 error (tests/Feature/Modulo), 2 failed (tests/Unit/NivelLogroTest.php).
- `vendor/bin/pest --compact`: 149 passed, 463 assertions. Pint: passed. public tables 54 / 54.

- A2.3+A3 route: delegated writer (trigger: 2+ non-trivial files).
- A2.3+A3 status: implemented, NOT checked off: 1 required test fails outside the writer's surface
  (tests/Feature/Infra/HostSchemaLoadedTest.php counts every function/trigger in the test schema and
  expects 15/19 host objects; the module trigger makes it 16/20). Needs a parent-authorized edit of
  that Infra test to count only host objects (exclude the module function/trigger by name).
- A2.3 evidence:
  - database/migrations/2026_10_24_000420_create_respuestas_acompanamiento_integrity_trigger.php:
    plpgsql `respuestas_acompanamiento_exigen_acompanamiento_en_curso()` BEFORE INSERT OR UPDATE OR
    DELETE FOR EACH ROW (Vinkured style: unqualified names, ERRCODE check_violation = 23514, dropped
    in down()). Rejects any write whose OLD or NEW acompanamiento is finalizado and any NEW
    indicador_id absent from pauta_snapshot->'indicadores'. Race with finalizar(): the parent row is
    read FOR SHARE (the FK only takes FOR KEY SHARE, which does not conflict with finalizar()'s
    UPDATE); a concurrent finalizar() either waits for the respuesta commit or, if first, makes the
    respuesta re-read the finalizado row under READ COMMITTED and fail. Concurrency itself is not
    exercised by a test (RefreshDatabase runs everything in one transaction).
  - Eloquent guard kept (friendly exceptions). New DB tests in RespuestaAcompanamientoTest: raw insert
    accepted in curso (control); raw insert / bulk update to an indicador outside the snapshot, raw
    insert, bulk update, bulk delete and moving out of a finalizado acompanamiento rejected with 23514.
  - R3-003: truncation test removed. Reason: the datetime cast already formats assigned values with
    `Y-m-d H:i:s` (micro dropped on assignment) and floor(minutes) of whole-second start vs a
    fractional end never crosses a minute boundary, so no input can make startOfSecond() observable.
  - R3-004: vacuous try/catch removed from the incomplete-snapshot test.
- A3 evidence:
  - app/Support/RolesDelModulo.php (scoped in AppServiceProvider), built on EstablecimientoActual +
    RolesDelUsuario + `observadores`; current user's funcionarios = Funcionario::vigentes() with the
    active membresia's persona_id (scoped models). No cache: evaluated at call time, so losing the
    observer flag takes effect in the same request. Fail closed (other user, no active establecimiento).
  - Roles: administrador = RolesDelUsuario::esDirectivo(). Vinkured `administrador`
    (membresias.es_administrador) is NOT a module admin: the original spec gives school admin to
    Principal/CurriculumHead roles only (both directivo), and Vinkured itself keeps its Administrador
    out of functional panels (least privilege). observador = admin OR vigente funcionario in
    `observadores`. docente = vigente funcionario with a función of categoria 'docente' OR any
    asignacion_docente (Vinkured semantics: categoria 'docente' covers docente de aula; asignaciones
    capture whoever teaches a curso). A directivo-only funcionario is not observable as docente.
  - R1-001: AsignacionAcompanamiento saving guard (AsignacionAcompanamientoInvalida: mismoFuncionario,
    observadorNoHabilitado, docenteNoHabilitado) + AsignacionAcompanamiento::esParElegible() used by
    the policy. Foreign-establecimiento funcionarios are not found by scoped queries → invalid.
    Factory default now builds an eligible pair (CreaFilasDelAnfitrion::nuevoObservador /
    nuevoFuncionarioConFuncion).
  - Policies (app/Policies, registered with Gate::policy, Spanish abilities): PautaPolicy
    (verCualquiera/ver/exportar: any funcionario; crear/actualizar/eliminar: admin),
    PautaBasePolicy/IndicadorPolicy (verCualquiera/ver: any funcionario; nothing else),
    AsignacionAcompanamientoPolicy (verCualquiera/crear(observadorId, docenteId)/eliminar: admin,
    eligible pair), AcompanamientoPolicy (crear(Funcionario docente): current observer with an
    assignment to that docente; actualizar/responder/finalizar: its observer while en_curso;
    ver/exportar: its observer, its docente only finalizado, admin). Gates ver-reportes-establecimiento
    (admin) and ver-reporte-docente (self, admin, CURRENT observer with a finalizado acompanamiento of
    that docente) via ReportePolicy. Every model check also requires the row's establecimiento_id to be
    the active one. Sostenedor-level access not implemented (still an open question) → denied.
  - Note: actualizar/responder/finalizar follow the old matrix (observer_id match only); an observer
    who loses the flag can still finish an open acompanamiento — flag for product review if unwanted.
  - Tests: tests/Feature/Modulo/RolesDelModuloTest.php, AsignacionAcompanamientoTest.php (eligibility),
    tests/Feature/Politicas/{Pauta,Catalogo,AsignacionAcompanamiento,Acompanamiento}PolicyTest.php,
    ReportesGateTest.php (allow + deny per role, cross-establecimiento, finalizado-only for docente,
    ex-observer loses 6.4). Helpers added to tests/Pest.php: funcionarioConFuncion, habilitarObservador,
    usuarioDelModulo, puede (forgets scoped instances like a fresh request).
  - RED: 210 tests, 25 failed + 17 errors (missing RolesDelModulo/policies/trigger/guard).
  - GREEN: `vendor/bin/pest --compact` 210 tests, 209 passed, 1 failed (Infra count above).
  - `vendor/bin/pint --dirty --format agent`: passed. public tables before/after: 54 / 54.

  - Parent fix (inline): HostSchemaLoadedTest now excludes module functions/triggers from the
    host counts. Re-run: `vendor/bin/pest --compact` 210 passed / 812 assertions; public 54 / 54.

## Next step
A4: policies and Pest port are done here; remaining is a final audit that every
b3397fc test has a Pest counterpart (e.g. old SchoolPolicy view/manageStaff has no module equivalent:
staff management is host-owned) and the catalog seeder check on the new tables. Then A5.
