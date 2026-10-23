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
- [ ] A1 Infrastructure: PostgreSQL connection to roberto_dev, test schema acompanamiento_test
  with Vinkured schema-only snapshot, prohibit destructive commands, Pest 4 installed with
  existing PHPUnit tests still passing.
- [ ] A2 Rename module domain to Spanish and re-base on host tables (Establecimiento,
  Funcionario, Usuario...) with tenancy pattern; drop module copies of host tables.
- [ ] A3 Roles derived from funciones/membresias (directivo, UTP, observador).
- [ ] A4 Port tests to Pest, port policies, catalog seeder on new tables.
- [ ] A5 Resume original plan (T4+) on the aligned base.

## Progress / Evidence
- Branch feat/vinkured-alignment created from feat/data-model @ 08f1888.

## Next step
A1 — route: delegated writer (2+ non-trivial files: config, phpunit.xml, provider, composer).
