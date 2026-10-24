# Feature: Bulk staff import and invitations (Vinkured Q18), built to hand back to Vinkured

## Objective
Let a Vinkured Administrador load staff (one by one or from an .xlsx spreadsheet) into the host
tables and invite each person by email to activate their account, so establishments that buy
only the coaching module can onboard their teachers without waiting for the Vinkured team.

## Why
Vinkured decided (docs/alcance-y-pendientes.md:24, CONTEXT.md "Administrador") that the
Administrador loads users one by one or in bulk and each person activates by email invitation,
but only the schema exists: membresias invitation columns, importaciones_usuarios (no model, no
estado values), no invitation send/accept, no RUT validation, no spreadsheet library, no Q18
file spec. Mapping evidence: Vinkured exploration of 2026-10-24 (see alineacion-vinkured.md).

## Decisions (user, 2026-10-24)
- Built by us "a la medida de Vinkured": host tables + importaciones_usuarios + membresia
  invitation flow; coordinated with the Vinkured team to avoid duplicate work.
- Spreadsheet format .xlsx, one row per person, columns in this order:
  RUT | Nombres | Primer apellido | Segundo apellido | Correo | Teléfono | Función.
  Función ∈ docente, directivo, asistente, profesional de apoyo (Vinkured funciones.categoria).
  No fecha de ingreso (null = vigente in Funcionario::scopeVigentes).

## Constraints (from Vinkured)
- Only the Administrador (membresias.es_administrador, gate pattern of ver-panel-usuarios).
- ADR-0005 anti-enumeration: responses and per-row errors must never reveal that an email already
  has an account in another establecimiento.
- Host tables written through host models inside EstablecimientoActual (paraTrabajo for jobs);
  module/host files tagged @host-owned stay untouched — new host-side code is written as new
  classes intended to move into Vinkured.
- Mail: plain-text Mailable sent by a ShouldQueueAfterCommit job with ids only, retries, no
  address leakage in exceptions/logs (pattern of NotificarRespuestaAlApoderado).
- New PHP dependency for .xlsx parsing requires an ADR proposal for Vinkured.
- Audit (Auditoria) allow-lists and accion CHECK are host-owned: propose changes, do not edit.
- Real personal data: Vinkured ticket 03 (legal review) must be resolved before loading real
  data in production.
- Safety: roberto_dev public schema is never modified; tests only in acompanamiento_test.

## Tasks
- [x] C1 RUT value object/validator (DV mod 11, normalization to Vinkured's '12345678-5' storage
  form) + spreadsheet row parser/validator (pure, no DB) + .xlsx reader behind an interface,
  with dependency choice and ADR draft for Vinkured.
- [ ] C2 Unit invitation: invite one person (persona + funcionario + funcion + usuario +
  membresia invitada), token generation (hashed at rest) + expiry, plain-text mail via queued
  job, accept route + set password → membresia activa; resend/revoke; anti-enumeration.
- [ ] C3 Bulk import: ImportacionUsuario model + estado values, upload, per-row processing
  reusing C2, summary (total/creados/con_error/errores without enumeration leaks).
- [ ] C4 UI (Livewire, Vinkured UI skills) for invite, import and results.
- [ ] C5 Hand-off package for Vinkured: ADRs, migration/CHECK proposals (estado values, audit
  allow-lists), CONTEXT.md glossary entries.

## Progress / Evidence
- Feature doc created on branch feat/vinkured-alignment.
- C1 route: delegated writer (trigger: 2+ non-trivial files).
- C1 done: openspout/openspout v5.3.0 (MIT) added; App\Support\Rut (+ RutInvalido),
  App\Support\Importacion\{LectorDePlanilla, LectorXlsx, FilaDePlanilla, ValidadorDePlanilla,
  ResultadoDeValidacion} (+ PlanillaIlegible), LectorDePlanilla bound to LectorXlsx in
  AppServiceProvider; ADR draft docs/adr/0007-lectura-de-planillas-con-openspout.md (Propuesta).
  - Storage form "12345678-5" matches Vinkured personas.numero_documento fixture '1-9'
    (/srv/vinkured tests/Pest.php newPersona).
  - Correo: trimmed + FILTER_VALIDATE_EMAIL like ReglasDeRespuesta::correoParaEnviar, plus
    lowercase (Vinkured's rule does not lowercase; ours does for duplicate detection).
  - Header must be row 1, compared lowercase/ASCII/collapsed spaces; trailing empty header cells
    ignored; extra non-empty columns rejected. Names/surnames max 100 chars (our choice).
  - Reader preserves empty rows (SHOULD_PRESERVE_EMPTY_ROWS) so positions = Excel row numbers;
    rejects non-.xlsx extension, OLE signature (encrypted/.xls), non-ZIP and corrupt files.
    C3 must store uploads with a .xlsx extension (reader checks the extension).
  - RED: 53 new tests, 0 passed (classes not found). GREEN: 54 new tests pass.
  - vendor/bin/pest --compact: 271 passed (984 assertions); public tables 54 before / 54 after.
  - vendor/bin/pint --dirty --format agent: passed.

- C1 commits f2d76dc (openspout) + 347b39b. Native review base 78d7544 (also covers A3.1/A4
  7624006), lens review-reliability → approved, acknowledged. Advisory:
  - R3-001 trigger migration edited in place: verified no persistent schema ran it (only
    acompanamiento_test, rebuilt per run; schema `acompanamiento` does not exist yet). RULE from
    now on: once any migration runs in a persistent schema or the branch is merged, change
    schema only through NEW migrations.
  - R3-002 lock mode checked structurally only (concurrency untestable under RefreshDatabase) —
    accepted, documented.
  - R3-003 data rows with more than 7 columns silently drop extra cells → fold into C2: reject
    non-empty cells beyond column 7 per row.

## Next step
C2 — unit invitation (route: delegated writer; HIGH risk: authentication).
