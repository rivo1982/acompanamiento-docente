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
- [x] C2 Unit invitation: invite one person (persona + funcionario + funcion + usuario +
  membresia invitada), token generation (hashed at rest) + expiry, plain-text mail via queued
  job, accept route + set password → membresia activa; resend/revoke; anti-enumeration.
- [ ] C2.1 Review follow-ups (C2 commit f011507, review base cd21406, approved + acknowledged;
  do BEFORE/with C3 because bulk import multiplies them):
  - ReenviarInvitacion vs ShouldBeUnique: a resend while a send job holds the unique lock is
    dropped, and the in-flight job may send an already-superseded link → invitee may end with
    no working link (WARNING). Make send/resend converge on "latest token is the one mailed".
  - Concurrent invitations for the same new correo/RUT: lockForUpdate on missing rows locks
    nothing → raw unique violation (WARNING). Translate to the domain conflict exception (or
    advisory lock per establecimiento+RUT/correo).
  - EnviarInvitacion::encolar swallows queueing failures (SUGGESTION) → surface a failure the
    caller/import can record; test the branch.
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

- C2 route: delegated writer (trigger: 2+ non-trivial files).
- C2 done (no UI/routes; UI is C4):
  - Defaults (module team, pending Vinkured confirmation): invitation valid 7 days
    (config/invitaciones.php `vigencia_dias`, env INVITACION_VIGENCIA_DIAS), counted from the send;
    plain-text mail, es-CL, "usted" (resources/views/mail/invitacion.blade.php).
  - Funciones genéricas proposed upstream: database/seeders/FuncionesGenericasSeeder.php (idempotent
    upsert by clave, like Vinkured FuncionesBaseSeeder): docente/Docente/docente,
    directivo/Directivo/directivo, asistente/Asistente de la educación/asistente,
    profesional_apoyo/Profesional de apoyo/profesional_apoyo; called from DatabaseSeeder. Spreadsheet
    categoria -> clave (InvitarFuncionario::CLAVE_POR_CATEGORIA). Note: a `directivo` invitee becomes
    module admin via RolesDelUsuario::esDirectivo (by design of A3).
  - Token lifecycle (never persisted in plaintext, also not in the jobs payload): InvitarFuncionario
    stores membresia `invitada` with invitada_en, invitacion_expira_en and the SHA-256 of a discarded
    random token (no usable link yet), then after commit dispatches EnviarInvitacion with ids only.
    The job, under FOR UPDATE, generates the real token (32 random bytes, base64url, 43 chars), stores
    only its SHA-256, renews the expiry, and sends the mail; plaintext lives only in memory and in the
    mail. Each send/retry/resend rotates (old link dies). Job skips if not `invitada` or expiry is null
    (anulada), so an anulación before the send wins. ReenviarInvitacion = new discarded hash + new
    expiry (old link dies immediately) + dispatch; AnularInvitacion = null hash + expiry, stays
    `invitada`, idempotent; both only for `invitada` (else InvitacionNoPendiente) and only in the
    active establecimiento (foreign id -> ModelNotFoundException).
  - Persona reuse: existing persona (same establecimiento, tipo rut, numero Rut::paraAlmacenar) is
    reused WITHOUT overwriting nombres/apellidos/correo/telefono (host data wins); funcionario and
    función genérica created only if missing. Usuario reused by lower(correo) (case-insensitive) or
    created with a random unusable password.
  - Conflicts only inside the establecimiento (InvitacionEnConflicto, Spanish, only "este
    establecimiento"): persona already activa/invitada/suspendida here, RUT linked to another correo
    here, correo linked to another persona here. Anti-enumeration: same InvitacionRegistrada (membresia
    id only), same job shape and identical mail text whether the correo has an account elsewhere
    (tests compare both cases).
  - Authorization: actions take the inviting Administrador's Membresia and re-check it from the DB
    (activa, es_administrador, establecimiento = EstablecimientoActual::id()) via
    Support/Invitaciones/AdministradorDelEstablecimiento, so they also work inside a C3 queued job
    (paraTrabajo, no user). Gate `invitar-funcionarios` = esDelUsuario && esAdministrador (like
    ver-panel-usuarios) for C4.
  - AceptarInvitacion: format check, then one transaction with FOR UPDATE on the membresia by hash;
    unknown/expired/anulada/rotated/used -> one generic InvitacionInvalida ("La invitación no es
    válida o ya venció."); single use (hash+expiry cleared, activa, activada_en). Established
    credential = es_soporte_vinkured or another `activa` membresia: password untouched, requires
    $autenticado to be that usuario (else InvitacionRequiereSesion). Otherwise password validated by
    ReglasDeContrasena (Spanish messages, field `contrasena`) with Password::defaults(); a failed
    validation does not consume the link; sets email_verified_at if null and rotates remember_token.
  - Password rule source: Vinkured defines no Password::defaults() (checked AppServiceProvider and
    Login); the module sets min 12 + letters + numbers in AppServiceProvider (proposal for Vinkured).
  - EnviarInvitacion: ShouldQueueAfterCommit + ShouldBeUnique (uniqueId est:membresia, uniqueFor 900),
    tries 3, backoff [60,300], FailOnException(InvitacionImposible), runs in paraTrabajo, transport
    errors replaced by EnvioDeInvitacionFallido without chaining, failed() logs ids only. Link built by
    Support/Invitaciones/EnlaceDeInvitacion (url('/invitaciones/{token}') until C4 adds the named route
    `invitaciones.aceptar`).
  - R3-003 folded in: ValidadorDePlanilla rejects a data row with non-empty cells beyond column 7.
  - Not done / for C5: no audit events (Auditoria allow-lists are host-owned); double acceptance is
    proven sequentially and by FOR UPDATE structurally (concurrency untestable under RefreshDatabase);
    concurrent invites of the same new correo/RUT can hit a unique violation (QueryException).
  - RED: 83 tests in scope (new + validator file), 25 passed, 2 failed (R3-003), 56 errors (classes
    missing). GREEN: 83 passed (360 assertions).
  - vendor/bin/pest --compact: 330 passed (1286 assertions). vendor/bin/pint --dirty --format agent:
    passed (fixed imports in one test file, re-run green). public tables 54 before / 54 after.

## Next step
C3 — bulk import (ImportacionUsuario + estado values, upload, per-row processing reusing
InvitarFuncionario inside EstablecimientoActual::paraTrabajo, summary without enumeration leaks).
