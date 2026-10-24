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
- [x] C2.1 Review follow-ups (C2 commit f011507, review base cd21406, approved + acknowledged;
  do BEFORE/with C3 because bulk import multiplies them):
  - ReenviarInvitacion vs ShouldBeUnique: a resend while a send job holds the unique lock is
    dropped, and the in-flight job may send an already-superseded link → invitee may end with
    no working link (WARNING). Make send/resend converge on "latest token is the one mailed".
  - Concurrent invitations for the same new correo/RUT: lockForUpdate on missing rows locks
    nothing → raw unique violation (WARNING). Translate to the domain conflict exception (or
    advisory lock per establecimiento+RUT/correo).
  - EnviarInvitacion::encolar swallows queueing failures (SUGGESTION) → surface a failure the
    caller/import can record; test the branch.
- [x] C3 Bulk import: ImportacionUsuario model + estado values, upload, per-row processing
  reusing C2, summary (total/creados/con_error/errores without enumeration leaks).
- [x] C3.1 Review follow-ups (C2.1+C3 commit fb7a310, review base 41f922b, approved +
  acknowledged):
  - ProcesarImportacion final `completada` write is unconditional: a redelivered attempt (when
    retry_after < processing time) can be overwritten/clobbered — make the final transition
    conditional on estado=procesando and set retry_after guidance (WARNING).
  - EnviarInvitacion gained a required `sello`: jobs queued before deploy would fail on
    unserialize. Not applicable yet (never deployed, no persistent queue) — add a tolerant
    fallback or record as deploy note for Vinkured (WARNING).
  - Mail::send inside the DB transaction: if commit fails after send, the mailed link is dead
    until the retry mails a fresh one — accepted tradeoff, document it (SUGGESTION).
- [ ] C4 UI (Livewire, Vinkured UI skills) for invite, import and results. Livewire 4 install
  APPROVED by user (2026-10-24). Process per .agents/skills/vinkured-ui/SKILL.md: per screen one
  writer → one read-only auditor (web-design-guidelines) → ≤1 correction pass → visual check
  375/768/1280 (if a browser tool is available; otherwise reported as pending) → tests → commit.
  - [x] C4.0 UI foundation (+ C3.1 fixes): Livewire 4 + Tailwind 4.3 + Vite matching Vinkured
    versions; copy Vinkured design assets as @host-owned (docs/diseno.md, CONTEXT.md,
    resources/css/app.css tokens, Inter font, Blade components, layouts), host Login +
    establecimiento selection + routes/middleware wiring.
  - [x] C4.0.1 Review follow-ups (C3.1+C4.0, commits 4cd1878..ae517fa; first review base 3a2c6ee
    ESCALATED "incomplete candidate" because host copies were outside the range; re-reviewed with
    base 4cd1878 incl. host copies → 4 lenses, approved + acknowledged. Note: START hit a
    gentle-ai 4.0.0 internal error "unsupported negotiated START risk reason dangerous_sink";
    lineage-bound STATUS recovered it):
    - ProcesarImportacion::terminar raw update bypasses casts/timestamps and ignores affected
      rows → use a conditional Eloquent/query that keeps casts and log ids when the race is lost.
    - FundacionUiTest host-tag list misses AriaCurrent/FormatoDelPanel; move the Vite build test
      out of the default suite (group/skip when node_modules absent).
    - Untested host-copied paths (login lockout, SelectorDeEstablecimiento::cambiar 403/success,
      SeleccionarEstablecimiento mount 0/1 memberships): port the corresponding Vinkured tests.
    - Copied app layout/paginacion reference host-only concepts (Alertas/Avisos, FiltraPorEstado):
      document as integration notes (copies stay identical). diseno.md has two contradictory
      "Modo oscuro" rows → report upstream to Vinkured.
  - [x] C4.1 Screen: aceptar invitación (guest, token link, set password / log in). Visual check 375/768/1280:
    pending (no browser in this environment).
    Audit (web-design-guidelines, read-only): 0 blockers, 3 findings, 1 needs_brief.
    needs_brief DECIDED (user, 2026-10-24): page-level error for an unusable invitation link uses
    the Aviso state (amarillo: "algo no está bien, pero tiene solución") — propose adding this rule
    to Vinkured's docs/diseno.md "Mensajes y estados" (host-owned, not edited here).
    Correction pass (parent, inline): per-state page title (Invitación no válida / Sesión de otra
    cuenta / Aceptar invitación) via layoutData; translate="no" on VINKURED; removed duplicated
    logout control (copy points to the header button). PantallaAceptarInvitacionTest 23/23.
  - [x] C4.1.1 Review follow-ups (C4.0.1+C4.1 commits 8b5fdf3..58b1186, review base 38536d9,
    approved + acknowledged): invitation page throttle 10/min per IP will block staff of one school
    behind a shared NAT right after a bulk import (WARNING) → key the limiter by IP + token hash
    (or a higher per-IP ceiling plus a strict per-token limit); fix the test's RateLimiter clear
    (named limiter key is hashed); test the establecimiento-selection fallback redirect; build
    terminar() columns without model defaults.
  - [x] C4.2 Screen: invitar funcionario + invitaciones pendientes (reenviar, anular). PARTIAL: invite form done;
    pending list/reenviar/anular blocked by needs_brief (see Progress). Form committed a82b26b/7a4c502.
    needs_brief DECIDED (user, 2026-10-24):
    1. Valid invitation badge follows the brief: "En plazo (N días hábiles)" — copy Vinkured's
       business-day logic (DiasHabiles, EstadoDePlazo, DiaNoHabil model and what they need) as
       @host-owned.
    2. Cancelled invitation badge: "Anulada", Neutro (gris) — propose adding to Vinkured's brief.
    Audit C4.2 (7a4c502 + 6e3ede2): 0 blockers, 5 findings, 2 needs_brief:
    - "Vencida hoy": resolved by host precedent (Vinkured Panel/Usuarios.php:93 uses it); the
      brief's mapping (diseno.md:106 vs :99) is inconsistent → report upstream.
    - Reenviar confirmation DECIDED (user, 2026-10-24): no confirmation; show a visible hint
      "Se enviará un nuevo enlace; el anterior dejará de funcionar." → propose to Vinkured brief.
    Correction pass (one): live-region double announcement (both views), select name, "Plazo no
    disponible" per brief, table loading skeleton per brief, count/status re-announcement, the
    Reenviar hint.
    C4.2 correction pass (delegated writer): (1) removed the wrapping aria-live in both views; one
    mechanism = role on the message (status/alert) + server focus, same as C4.1; (2) select
    name="funcion"; (3) "Plazo no disponible" (Neutro); (5) removed the sr-only role="status" total
    (total never changes on page/reenviar/anular; pagination focuses the h2 that carries "(N)", so
    it is announced once on page change only); (6) hint shown once above the table
    (#aviso-reenviar), aria-describedby on every Reenviar button. (4) needs_brief PENDING: brief says
    only "esqueleto en tablas" (diseno.md:156), no tokens/shape/row count, and Vinkured has no
    skeleton precedent (no wire:loading skeleton in Alertas/Avisos index) — not implemented.
    Tests: +4 pendientes (badge, single announcement, count not live, hint), invitar asserts select
    name and no aria-live. Evidence: public 54/54; pest 599 passed; pint passed; npm run build ok.
    Table skeleton stays PENDING (needs_brief for Vinkured: brief names it but defines no tokens/
    shape and Vinkured has no implementation) — reported, not looped (vinkured-ui decision gate).
    Native review (base 89c4ffc incl. host business-day copies; commits a82b26b..0c96634):
    approved + acknowledged. Advisory fixed inline by parent: Plazos test helper restored UTC after
    the timezone switch → now restores APP_TIMEZONE/America/Santiago; added a test; 600 passed.
    Advisory pending (C4.2.1): prove timestamps/expiry ordering under America/Santiago (raw CASE
    vs now() string) with a test; anular() re-check that the link is still live between confirm
    and action (relies on AnularInvitacion today).
  - [x] C4.2.1 Advisories: expiry ordering/badges proven under America/Santiago (midnight + September DST
    tests; the SQL no longer compares with now()); anular() re-checks invitada + live link before acting;
    "usted" copy in the import domain messages replaced (infinitive).
  - [x] C4.3 Screen: carga masiva (upload) + resultado de la carga masiva. Checked after the correction pass (all
    items pass; visual check pending, no browser). Earlier note:
    needs_brief (estado badges) pending and the back-link from the invitations views is outside the writer's surface
    (see Progress). Visual check pending (no browser). Commits 3d0a215 (C4.2.1) + 7681853 (C4.3).
    needs_brief DECIDED (user, 2026-10-24): import estado badges — Pendiente Neutro, Procesando
    Información, Completada Éxito, Fallida Aviso (consistent with Aviso for recoverable page-level
    problems) → propose to Vinkured brief. Correction pass will add badges, "Carga masiva" links in
    the invitations views, the ValidadorDePlanilla "Divídala" copy fix, and audit findings.
    Correction pass committed bb4b7f0. Native review (base 4e56734, commits 3d0a215..bb4b7f0):
    approved + acknowledged. Advisory R3-mimes FIXED by parent (inline, test-first): `mimes:xlsx`
    trusted the detected MIME → a real .xlsx detected as application/zip/octet-stream was REJECTED
    and a text file claiming the xlsx MIME was ACCEPTED (RED: 3 failing). Now a content rule checks
    the ZIP signature (PK\x03\x04); LectorXlsx still validates the rest. 652 passed; public 54/54.
    Advisory pending (C4.3.1): polling bound is fixed at 10 min regardless of import size/progress
    → consider progress-aware bound (e.g. extend while counters change).
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

- C2.1+C3 route: delegated writer (trigger: 2+ non-trivial files).
- C2.1 done:
  - Resend convergence: EnviarInvitacion is no longer ShouldBeUnique. It carries ids + `sello` =
    sha256('envio-de-invitacion:'.invitacion_hash) of the (discarded-token) hash stored by the invite/resend that
    queued it (no correo, token or hash in the payload). Under FOR UPDATE it sends only if the sello still matches
    the stored hash: an older queued job superseded by a resend sends nothing; a job redelivered after a successful
    send sends nothing (the send rotated the hash). Rotation AND Mail::send run inside ONE transaction with the row
    locked until the mail is out: a resend arriving meanwhile waits, then kills that link and queues its own job, so
    the last mail sent always carries the stored token. A mail failure rolls back (hash back to the sello's) and the
    retry sends. Cost: the membresia row is locked for the duration of the mail transport call (also delays an
    AceptarInvitacion on that row). Tests: superseded job, resend mid-send (sequential simulation via
    eloquent.saved listener), redelivery idempotence, failure+retry.
  - Concurrent invites: InvitarFuncionario takes pg_advisory_xact_lock on "rut:{est}:{rut}" then "correo:{correo}"
    (fixed order, no deadlock; int64 key derived in PHP so no RUT/correo travels in SQL) before its reads; a residual
    UniqueConstraintViolationException (23505, e.g. a host write) becomes InvitacionEnConflicto::simultanea()
    (establishment-only message, not chained: the DB message contains correo/RUT). Tests: 2 advisory locks held;
    23505 simulated for usuarios.correo and personas RUT via a creating listener that pre-inserts the row.
  - EnviarInvitacion::encolar(est, Membresia) now throws InvitacionNoEncolada (membresiaId, Spanish message, not
    chained; log keeps ids only). InvitarFuncionario/ReenviarInvitacion propagate it (row saved, link not mailed).
    InvitacionEnConflicto::yaInvitada now says "invitación pendiente".
- C3 done (no UI/routes):
  - App\Models\ImportacionUsuario (NEW class for Vinkured, PerteneceAEstablecimiento, nothing mass-assignable,
    casts estado enum / errores array), App\Enums\EstadoImportacion, config/importaciones.php (disco=local via
    IMPORTACIONES_DISCO, directorio=importaciones).
  - PROPOSAL for C5: estado values pendiente -> procesando -> completada | fallida (last two terminal); CHECK
    `importaciones_usuarios_estado_check CHECK (estado IN ('pendiente','procesando','completada','fallida'))`.
    errores JSON shape: list of {fila: int|null, mensajes: list<string>} (fila null = file-level error).
  - IniciarImportacion::iniciar(UploadedFile|string, Membresia): re-checks Administrador, stores the file as
    importaciones/{uuid}.xlsx, `archivo` = original basename only (max 255), estado pendiente, dispatches
    ProcesarImportacion(est, importacionId, archivoId uuid) after the commit; a queue failure leaves the import
    fallida ("No se pudo programar...") and deletes the file.
  - ProcesarImportacion: ShouldQueueAfterCommit + ShouldBeUnique (est:id), tries 1, paraTrabajo; atomic claim
    pendiente->procesando (re-run of completada/fallida/procesando = no-op); Administrador re-check (else fallida);
    PlanillaIlegible / file-level validator errors -> fallida [{fila:null,...}]; each valid row through
    InvitarFuncionario (own transaction); conflicts / InvitacionNoEncolada / revoked admin -> row errors; unexpected
    exception -> generic row error + log with ids only; counters total (non-empty data rows), creados, con_error;
    completada. failed() -> fallida + generic message + ids-only log.
  - File retention decision: the stored spreadsheet is ALWAYS deleted when processing ends (finally), on fallida,
    in failed(), and when the queueing fails. Open risk for C5: if a job is lost before running, the file stays on
    disk (no purge yet) -> propose a scheduled purge of importaciones/ files older than N hours. Also: queue
    retry_after must exceed the processing time of a 2000-row file (tries=1; a timeout kills the job -> fallida).
  - RED: C2.1 65 tests in scope, 53 passed, 9 failed, 3 errors. C3 15 tests, 0 passed (classes missing).
    GREEN: Invitaciones 65 passed; ProcesarImportacionTest 16 passed (100 assertions).
  - vendor/bin/pest --compact: 355 passed (1436 assertions). vendor/bin/pint --dirty --format agent: passed.
    public tables 54 before / 54 after.

- C3.1+C4.0 route: delegated writer (trigger: 2+ non-trivial files).
- C3.1 done:
  - ProcesarImportacion: both final transitions (completada, fallida) go through `terminar()`, ONE
    `UPDATE ... WHERE id = ? AND estado = 'procesando'`; a late/redelivered attempt or failed() result is never
    clobbered. PHPDoc deploy note: queue `retry_after` (DB_QUEUE_RETRY_AFTER, default 90 s) and worker `--timeout`
    must exceed the worst-case processing time of a 2000-row file (config/queue.php is outside this task's surface).
  - EnviarInvitacion: a payload serialized without `sello` (pre-C2.1) is skipped (no send, no rotation) and logged
    as warning with ids only. Deploy note (PHPDoc): drain EnviarInvitacion jobs before deploying, or the
    Administrador resends pending invitations whose send was discarded.
  - EnviarInvitacion PHPDoc documents the accepted tradeoff: Mail::send inside the transaction; a commit failure
    after the send leaves the mailed link dead until the retry mails a fresh one.
  - RED: 2 new ProcesarImportacion tests failed (completada clobbered fallida; fallida clobbered completada);
    1 new EnviarInvitacion test errored ("Typed property ...::$sello must not be accessed before initialization").
    GREEN: Importacion 18/18, Invitaciones 66/66.
- C4.0 PARTIAL (not checked):
  - livewire/livewire ^4.4 installed (v4.4.8, same minor as Vinkured's lock 4.4.7; only package added to the
    lock). package.json now identical to Vinkured's (+ @fontsource-variable/inter); package-lock.json created;
    `npm install` 0 vulnerabilities; `npm run build` OK.
  - Copied @host-owned (byte-identical + tag line): CONTEXT.md, docs/diseno.md, resources/css/app.css,
    resources/js/app.js, vite.config.js (bunny fonts removed, as Vinkured), app/Livewire/{Login,
    SeleccionarEstablecimiento, SelectorDeEstablecimiento}.php, resources/views/livewire/{login,
    seleccionar-establecimiento, selector-de-establecimiento}.blade.php, resources/views/components/{badge, button,
    campo, card, modal, page-header, paginacion, tabla}.blade.php, resources/views/components/layouts/{app,
    simple}.blade.php.
  - Wiring: routes login (guest), logout + establecimientos.seleccionar (auth), `panel` placeholder home
    (auth + establecimiento.activo, module-owned view resources/views/inicio.blade.php on the simple layout; named
    `panel` so the copies redirect unchanged; remove at integration). bootstrap/app.php: redirectGuestsTo(login),
    redirectUsersTo(panel), as Vinkured. No new middleware or gates needed.
  - BLOCKER: layouts/app.blade.php does not render: it calls App\Support\AriaCurrent (host helper, app/Support is
    outside the allowed surface). paginacion.blade.php also needs App\Support\FormatoDelPanel at render time. Both
    are small host classes to copy as @host-owned. With AriaCurrent copied, the app layout should render unchanged:
    its Alertas/Avisos entries are behind `can('viewAny', Alerta/Aviso::class)` with no policy -> false (unverified).
  - `/` still returns welcome: Vinkured redirects to panel/login, but tests/Feature/ExampleTest.php (outside the
    surface) asserts 200.
  - Tests: tests/Feature/Acceso/LoginTest.php (18, ported from Vinkured LoginTest/LogoutTest + module cases);
    RED with the wiring reverted: 18 tests, 2 passed, 4 failed, 12 errors; GREEN 18/18.
    tests/Feature/Ui/FundacionUiTest.php (31: host tags, tokens in source and in a temp-dir Vite build, self-hosted
    Inter, simple layout render) written after the copy (structural; no RED).
  - vendor/bin/pest --compact: 407 passed (1575 assertions). pint --dirty: passed. public tables 54 before / 54
    after.

- C4.0 CLOSED by parent (inline, mechanical): copied app/Support/AriaCurrent.php and
  app/Support/FormatoDelPanel.php as @host-owned (diff vs Vinkured = tag lines only); added
  FundacionUiTest "renders the host app layout for an active user" → 32/32 passed. `/` welcome
  page kept for now (ExampleTest); align with Vinkured redirect at integration.

- C4.0.1+C4.1 route: delegated writer (trigger: 2+ non-trivial files).
- C4.0.1 done:
  - ProcesarImportacion::terminar: values go through the model casts
    (`(new ImportacionUsuario)->forceFill([...])->getAttributes()`: enum -> value, errores -> JSON), then ONE Eloquent
    builder `update` WHERE id AND estado='procesando' (the builder adds updated_at). Affected rows = 0 -> race lost ->
    `Log::warning` with ids + discarded estado only. RED: "no pisa con completada..." now spies the log -> failed
    ("warning ... called 0 times"); GREEN after the change. New characterization test: cast JSON errores + renewed
    updated_at (passed before and after; structural).
  - FundacionUiTest: host-tag list includes app/Support/AriaCurrent.php and app/Support/FormatoDelPanel.php. The
    two real Vite build cases are in Pest group `build`, excluded from the default run in phpunit.xml
    (`<groups><exclude>`), and skipped when node_modules/.bin/vite is absent. Run them with
    `vendor/bin/pest --group=build` after `npm install` (9 passed). Source-token and vite.config checks stay in the
    default run.
  - Ported host tests (characterization of copied host code, so no RED): tests/Feature/Acceso/LoginTest.php +3 (+2
    dataset rows) from Vinkured tests/Feature/Acceso/LoginTest.php (lockout on the 6th attempt with focus JS,
    pluralized seconds, other email not locked); tests/Feature/Acceso/SeleccionDeEstablecimientoTest.php from Vinkured
    tests/Feature/Acceso/EstablecimientoActivoTest.php (selector mount with 1 membership -> panel; 0 active ->
    logout + login; SelectorDeEstablecimiento::cambiar success sets context + flash; 403 for foreign or only-invited
    establecimiento, context unchanged).
  - Integration notes (copies stay identical): layouts/app.blade.php shows Alertas/Avisos entries behind
    `can('viewAny', Alerta/Aviso::class)` (no policy here -> hidden); paginacion.blade.php relies on host
    FormatoDelPanel. docs/diseno.md has two contradictory "Modo oscuro" rows (lines 13-14): upstream report to
    Vinkured PENDING (human action).
- C4.1 done (no visual check: no browser here):
  - Route `invitaciones.aceptar` GET /invitaciones/{token} -> full-page Livewire App\Livewire\Invitaciones\
    AceptarInvitacion on the host `simple` layout, outside auth/establecimiento.activo; `throttle:invitaciones`
    (AppServiceProvider limiter: 10/min by IP). EnlaceDeInvitacion now uses route(). Response headers
    `Referrer-Policy: no-referrer` and `Cache-Control: no-store, private` (Livewire view ->response()).
  - Token: stored encrypted (Crypt, APP_KEY) in a #[Locked] property, decrypted only in memory on submit; never in
    component data or visible text. The single copy in the HTML is Livewire's memo `path` (= page URL), unavoidable
    for a full-page component; pinned by a test.
  - States: invalid (one generic message "La invitación no es válida o ya venció." + "Pedir una nueva invitación al
    Administrador del establecimiento.", no form, no establecimiento); new account (contraseña nueva + confirmar,
    rule shown before submit as campo ayuda, autocomplete new-password); established account as guest (Aviso + link
    to login); same user logged in (one "Aceptar invitación" button -> activates, selects the new establecimiento
    -> panel, else selection page); different user logged in (Aviso + "Cerrar sesión" form, no invitation data);
    accepted (Éxito role=status + "Iniciar sesión" link). Only the inviting establecimiento's name is shown (not in
    invalid/other-account states); never correo/RUT.
  - Decisions: after a new account accepts, NO auto-login: an in-page Éxito state with a link to login (the host
    Login shows flashes only in Aviso style, which would contradict "el éxito usa Éxito"). Host Login ignores the
    intended URL (redirects to panel/selection), so the established-account copy asks to log in and reopen the
    link instead of promising a return (host-owned, not changed). Copy uses infinitives per the brief's internal-UI
    rule ("Iniciar sesión con esa cuenta y volver a abrir el enlace de la invitación."), not "Inicie sesión".
    Password rules = ReglasDeContrasena + `confirmed` ("Las contraseñas no coinciden."), all errors at once; the
    action re-validates. Errors inline via campo (aria-invalid, aria-describedby ayuda+error); focus moves to the
    password field on error and to the status message on state changes.
  - Brief tokens used: fondo/superficie via layout+card, texto, texto-atenuado (ayuda, descripción), acento (primary
    button), borde-campo, estados Aviso (aviso/aviso-fondo) and Éxito (exito/exito-fondo), text-titulo/text-base/
    text-tabla, radius control/tarjeta, spacing steps 4/8/12/24 (gap-1, gap-6, p-3), alto-control (40 px, 44 px on
    coarse pointers). No needs_brief.
  - RED: PantallaAceptarInvitacionTest 21 tests, 0 passed, 21 errors (class/route missing). GREEN: 21 passed;
    tests/Feature/Invitaciones 87 passed.
- Verification (C4.0.1+C4.1): vendor/bin/pest --compact: 442 passed (1846 assertions), build group excluded;
  vendor/bin/pest --compact --group=build: 9 passed; vendor/bin/pint --dirty --format agent: passed (fixed imports
  /spacing in 3 test files, re-run passed); npm run build: OK; public tables 54 before / 54 after (first count taken
  right after the first focused RED run, which only touches acompanamiento_test).

- C4.1.1+C4.2 route: delegated writer (trigger: 2+ non-trivial files).
- C4.1.1 done:
  - Limiter `invitaciones` = two limits: 10/min by `enlace:{ip}:{sha256(token)}` + 120/min by `ip:{ip}` (school behind one
    NAT can open many different links; one link hammered gets 429). Tests: 20 different valid links from one IP all load;
    same link 11th request 429 while another link and another IP still load; 121st distinct link from one IP 429.
  - Test limiter reset: named limiter keys are md5(name+key), so `RateLimiter::clear('invitaciones:127.0.0.1')` cleared
    nothing; beforeEach now flushes the (array) cache store.
  - Selection fallback tested: membresia suspended right after acceptance (eloquent.saved listener) -> redirect to
    establecimientos.seleccionar, no establecimiento in session.
  - ProcesarImportacion::terminar(): UPDATE columns = Arr::only(model-cast attributes, requested keys + estado); model
    defaults (total/creados/con_error = 0) no longer enter the fallida UPDATE. Test checks counters kept + SQL columns.
  - RED: terminar test failed ("0 is identical to 4"); 3 throttle tests failed (429 on links 11+); fallback test first
    errored (final class not mockable) and was rewritten as characterization (passes against existing code). GREEN.
- C4.2 PARTIAL (not checked):
  - ValidadorDePlanilla::validarFuncionario(array $celdas): ValidacionDeFuncionario (new readonly class: ?FilaDePlanilla
    + errores por campo, keys ValidadorDePlanilla::CAMPOS, `fila` for extra columns; mensajes() flattens in column
    order). validar() now uses it internally: same messages/order (all Importacion + Unit tests unchanged and green).
  - Route `invitaciones.create` GET /usuarios/invitaciones/nueva (auth + establecimiento.activo +
    can:invitar-funcionarios) -> App\Livewire\Invitaciones\NuevaInvitacion on host layouts/app (ancho formulario).
    Gate re-checked in mount and in invitar(); the action re-reads the admin membresia (EstablecimientoActual::membresia()).
  - Form: RUT, Nombres, Primer apellido, Segundo apellido, Correo (type email), Teléfono (type tel), Función (select
    Docente/Directivo/Asistente de la educación/Profesional de apoyo; values = spreadsheet values). One column, two from lg.
    Errors inline via x-campo (all at once, focus to first invalid). Button "Enviar invitación"/"Enviando invitación…".
  - Decisions: separate page for the form (brief: forms up to 768 px, tables full width); success stays on the form
    (cleared) with Éxito role=status "Invitación enviada a <nombres> <primer apellido>." built from the typed data (same
    text whether the correo has an account elsewhere; never echoes the correo). Conflict (InvitacionEnConflicto) =
    form error -> Peligro role=alert, data kept. InvitacionNoEncolada -> Aviso role=alert "La invitación quedó
    registrada, pero su correo no se pudo programar. Reenviarla más tarde desde las invitaciones pendientes.", form
    cleared. Focus moves to #mensaje-invitacion.
  - Brief tokens used: layout app (fondo/superficie/borde), card, page-header (text-titulo), campo (borde-campo,
    peligro, texto-atenuado ayuda, text-tabla/text-base), button primario (acento/acento-hover/sobre-acento), estados
    Éxito/Aviso/Peligro (texto + fondo), radius control/tarjeta, spacing 4/12/24 (gap-6, p-3, mb-6), alto-control.
  - Tests: PantallaInvitarFuncionarioTest 19 (RED 19 errors -> GREEN 19); Unit validarFuncionario +2 (written after the
    refactor: no RED).
  - needs_brief (blocks the "Invitaciones pendientes" list, reenviar, anular, pagination, empty state):
    1. Badge for an anulada invitation (membresia invitada with hash+expiry null): text and variant are not in the
       brief's badge mapping. Host Panel/Usuarios shows null expiry as "Sin vencimiento" (Neutro), which would be
       misleading here (the link is dead). Proposal: "Anulada", Neutro.
    2. Vigente badge "En plazo (N días hábiles)" requires business days (host DiasHabiles + EstadoDePlazo +
       DiaNoHabil model over dias_no_habiles); none exists in this repo and copying them as @host-owned is outside
       the allowed surfaces (app/Support/*.php root, app/Models). Needs either authorization to copy them, or a brief
       decision for invitations (e.g. calendar date "Vence el dd-mm-aaaa" without a day count).
  - Copy issue for upstream (app/Exceptions outside surface): InvitacionEnConflicto messages use "usted" ("Puede
    reenviarla o anularla.", "Inténtelo nuevamente."), shown as-is on the form; brief's internal-UI rule is infinitive.
  - Integration notes: no navigation entry (layouts/app nav is @host-owned and built from host policies; an
    "Invitar funcionario"/"Usuarios" entry must be added in Vinkured). Overlap: host Panel/Usuarios lists invitada +
    suspendida (read-only, shows correo, LIMITE_FILAS, vencidas first); the pending list should reuse its ordering and
    be linked from that panel block at integration.
- Verification (C4.1.1+C4.2 partial): vendor/bin/pest --compact: 469 passed (2241 assertions); vendor/bin/pint --dirty
  --format agent: fixed ordered_imports in one test, re-run passed; npm run build: OK; public tables 54 before / 54
  after. Visual check: pending (no browser).

- C4.2 (list) route: delegated writer (trigger: 2+ non-trivial files).
- C4.2 list done (NOT checked: one failing test outside the writer's surface, see below; visual check pending, no browser):
  - Copied @host-owned (diff vs Vinkured = tag lines only): app/Support/DiasHabiles.php, app/Support/EstadoDePlazo.php,
    app/Models/DiaNoHabil.php. Closure already present (EstablecimientoActual, SinEstablecimientoActivo, table
    dias_no_habiles in the host schema). AppServiceProvider: `scoped(DiasHabiles::class)` as Vinkured.
  - Ported tests: tests/Feature/Plazos/{DiasHabilesTest,EstadoDePlazoTest}.php from Vinkured tests/Feature/Plazos (+
    tests/Feature/Plazos/helpers.php with `diaNoHabil` from Vinkured tests/Pest.php). FINDING: Vinkured's app timezone is
    America/Santiago (config/app.php, env APP_TIMEZONE), this repo's is UTC; first run 72/100 (28 failed, all
    zone-dependent). The ported tests and the list tests switch to America/Santiago (config + PHP default) and back.
    Integration/decision note: config/app.php (outside surface) should match Vinkured, otherwise local dates/plazos are UTC.
    CopiasDelAnfitrionTest (tags + scoped binding; RED by removing the binding: "same object" failed).
  - Route `invitaciones.index` GET /usuarios/invitaciones (auth + establecimiento.activo + can:invitar-funcionarios) ->
    App\Livewire\Invitaciones\InvitacionesPendientes on layouts/app (full width). Links both ways: list header
    "Invitar funcionario" (primario) + empty-state action; form header "Ver invitaciones pendientes" (secundario).
  - Rows: membresias `invitada` of the active establecimiento (incl. anuladas), columns Nombre, Correo (host Panel/Usuarios
    shows it), Función (names of the persona's funciones, one query), Invitada el (FormatoDelPanel::fecha), Situación,
    Acciones. Badges = Vinkured Panel\Usuarios::situacion mapping (Vencida / Vencida hoy Peligro, Vence hoy Aviso,
    "En plazo (N días hábiles)" Neutro, Vencimiento no disponible Neutro) + "Anulada" Neutro instead of the host's "Sin
    vencimiento". Order = host CASE (vencidas 0, vigentes 1) with anuladas as group 2 (host puts null expiry last inside
    group 1 via ASC NULLS LAST, so the result is the same). 20/page, page clamped like host FiltraPorEstado::paginar,
    host x-paginacion with focus to the card h2. Empty state "No hay invitaciones pendientes." + "Invitar funcionario".
  - Actions: Reenviar (any invitada: vigente/vencida/anulada) and Anular (only with a live link) -> host x-modal
    confirmation (Cancelar autofocus, "Anular invitación" peligro). Every action re-checks the gate, reads the admin
    membresia, and loads the id only inside the active establecimiento (foreign or malformed id -> 404, nothing changes).
    Messages: Éxito "Invitación reenviada a X." / "Invitación de X anulada. Su enlace ya no sirve."; Aviso for
    InvitacionNoPendiente and for InvitacionNoEncolada ("El enlace anterior de X quedó sin efecto, pero el correo nuevo
    no se pudo programar. Reenviar la invitación en unos minutos."). No Peligro case (no field/form errors here).
    Focus to #mensaje-invitaciones after the action (setTimeout 0, after x-trap returns focus); wire:loading per row.
  - InvitacionEnConflicto copy: "Se puede reenviar o anular desde las invitaciones pendientes." and "Volver a
    intentarlo." (no usted). PENDING (outside surface): tests/Feature/Importacion/ProcesarImportacionTest.php:185 still
    expects the old yaInvitada text -> replace "Puede reenviarla o anularla." with "Se puede reenviar o anular desde las
    invitaciones pendientes.". Other "usted" copy outside surface: InvitacionNoEncolada ("Reenvíela más tarde.", not
    shown by the screens) and ProcesarImportacion row error ("Inténtelo nuevamente más tarde.", shown in C4.3).
  - Brief tokens used: layout app, page-header (text-titulo), card, text-subtitulo h2, tabla (text-tabla, filas 40 px,
    superficie-atenuada header), badge Neutro/Aviso/Peligro, button primario/secundario/peligro, modal (max-w-md,
    shadow-lg, velo), estados Éxito/Aviso (texto + fondo + borde), texto-atenuado, radius control, spacing 4/8/12/16/24
    (py-1, gap-2, p-3, gap-4, mb-6), tabular-nums for dates. No needs_brief.
  - RED: PantallaInvitacionesPendientesTest 22 tests, 0 passed, 22 errors (route/component missing). GREEN 22/22.
    InvitarFuncionarioTest copy change: RED 3 failed -> GREEN.
  - Integration note (nav): no layout edit; Vinkured must add a "Usuarios"/"Invitaciones pendientes" nav entry and link
    it from the panel block "Usuarios por gestionar".
- Verification (C4.2 list): public tables 54 before / 54 after; vendor/bin/pest --compact: 595 tests, 594 passed, 1
  failed (ProcesarImportacionTest:185, outside surface, expected); vendor/bin/pint --dirty --format agent: passed;
  npm run build: OK.

- Parent (inline): ProcesarImportacionTest:185 copy updated; config/app.php timezone aligned with
  Vinkured (`env('APP_TIMEZONE', 'America/Santiago')`, technical alignment under the user's
  "adopt Vinkured conventions" decision). Full suite: 595 passed (8499 assertions); public 54/54.

- C4.2.1+C4.3 route: delegated writer (trigger: 2+ non-trivial files).
- C4.2.1 done:
  - Zone proof: `invitacion_expira_en` is `timestamp without time zone` written in app-local time and the old CASE compared
    it with `now()->toDateTimeString()` (also local), so it was consistent; the CASE was redundant with ascending expiry
    anyway. Simplified to `CASE WHEN expira IS NULL THEN 1 ELSE 0 END, expira, id` (no zone-dependent SQL). Tests: local
    23:59:30 with expiries 23:59:00 (Vencida hoy, Peligro) and 00:00:30 next day (En plazo (1 día hábil), Neutro; a UTC
    comparison would call it expired); 06-09-2026 01:30 right after the DST jump (Vencida / Vence hoy). Both passed before
    and after the simplification (proof tests, no RED by construction).
  - anular(): re-reads the invitation and, if it is no longer `invitada` with a live link, shows Aviso "Esta invitación ya
    no tiene un enlace que anular." and calls nothing (AnularInvitacion is idempotent, so before this a link cancelled in
    another tab was reported as a success). RED: 2 failed (cancelled in between -> 'exito'; accepted in between -> other
    copy) -> GREEN. Residual: a tiny window between this read and AnularInvitacion's row lock (idempotent, harmless).
  - Copy (tests first, RED 6+3 failed -> GREEN): "Volver a subirla." (IniciarImportacion, ProcesarImportacion::failed),
    "Volver a intentarlo más tarde." (unexpected row error), PlanillaIlegible ("Quitar la contraseña y guardarla como
    .xlsx." / "Guardarla de nuevo como .xlsx desde Excel y volver a subirla."), InvitacionNoEncolada ("Reenviarla más
    tarde desde las invitaciones pendientes."; it is shown as a row error). PENDING (outside surface:
    tests/Unit/Importacion/ValidadorDePlanillaTest.php:120): ValidadorDePlanilla "Divídala en archivos más pequeños." ->
    proposal "Dividirla en archivos más pequeños."
- C4.3 done (not checked, see task):
  - Routes (auth + establecimiento.activo + can:invitar-funcionarios): `importaciones.index` GET /usuarios/importaciones
    (CargaMasiva), `importaciones.plantilla` GET /usuarios/importaciones/plantilla (PlantillaDePlanilla, streamed),
    `importaciones.show` GET /usuarios/importaciones/{importacion} (whereNumber; ResultadoDeImportacion).
  - Upload: WithFileUploads; accept=".xlsx,<xlsx MIME>"; server rules bail|required|file|extensions:xlsx|max:<config
    importaciones.tamano_maximo_kb = 2048>|mimes:xlsx; x-campo with help (format, size) and the column list (ol, in order,
    opcional marked) + accepted Función values + 2.000-row limit above it; "Cargando archivo…" while uploading, submit
    disabled during upload/submit ("Subiendo planilla…"); focus to #archivo on error. IniciarImportacion stores it, then
    the Livewire temp file AND its `.json` metadata (holds the original name; TemporaryUploadedFile::delete() leaves it)
    are deleted, also on validation failure; redirect to the result.
  - Template: headers only (decision: an example row left in the file would be processed and invited); generated on the fly
    with openspout to php://output, `plantilla-carga-masiva.xlsx`, Cache-Control no-store.
  - Result: wire:poll.5s only while pendiente/procesando (attribute removed when terminal; Livewire pauses a poll whose
    directive is gone). One role="status" whose text is identical while pending/processing and changes once on finish
    (announced once). Completada: counters (dl: Filas en la planilla / Personas invitadas / Filas con error) + table Fila |
    Mensajes; fallida: file-level messages (fila null) in an Aviso block without its own role (the status region already
    announces). "Ver invitaciones pendientes" only when creados > 0. Gate re-checked on every render (poll).
  - List: this establecimiento's imports, newest first, 20/page clamped like InvitacionesPendientes, host x-paginacion
    (focus to h2 "Cargas recientes (N)"); columns Archivo, Subida el (FormatoDelPanel::fechaYHora — several uploads per day
    are otherwise indistinguishable), Estado (plain text, see needs_brief), Resultado ("N de M invitadas"), Ver resultado
    (x-button fantasma, sr-only file name). Empty: "Aún no hay cargas masivas en este establecimiento." (the form above is
    the action).
  - Tenancy: ImportacionUsuario global scope -> another establecimiento's id = 404; malformed id = 404; non-admin 403.
  - Brief tokens used: layout app, page-header (text-titulo), card (+ max-w-3xl = forms up to 768 px), text-subtitulo,
    campo (borde-campo, peligro, texto-atenuado), button primario/secundario/fantasma, tabla, paginacion, Aviso (texto +
    fondo + borde), borde, radius control, spacing 1/2/3/4/6 (gap-1, mt-2, p-3, gap-4, mb-6, pl-6), tabular-nums.
  - RED: PantallaCargaMasivaTest + PantallaResultadoDeImportacionTest 23 tests, 0 passed (route/component missing).
    GREEN: 22/22 (one test dropped: the invitations-list back-link, outside surface).
  - needs_brief:
    1. Estado badges for imports (not in the brief's mapping). Shown as plain text meanwhile. Proposal: Pendiente Neutro,
       Procesando Información, Completada Éxito, Fallida Peligro (or Aviso, matching the page-level Aviso decision).
  - Outside surface / integration notes: add "Carga masiva" link (route importaciones.index) to
    resources/views/livewire/invitaciones/{invitaciones-pendientes,nueva-invitacion}.blade.php headers; nav entry in the
    host layout. A file chosen but never submitted stays in livewire-tmp until Livewire's 24 h cleanup (a smaller
    temporary_file_upload max via config/livewire.php would affect all host uploads; not done).
- Verification (C4.2.1+C4.3): public tables 54 before / 54 after; vendor/bin/pest --compact: 626 passed (8690
  assertions); vendor/bin/pint --dirty --format agent: fixed 1 unused import, re-run passed; npm run build: OK.

- C4.3 correction pass (delegated writer, single allowed pass; tests first: 29 RED -> GREEN):
  1. Estado badges (host x-badge, CargaMasiva::VARIANTES): Pendiente Neutro, Procesando Información, Completada Éxito,
     Fallida Aviso, in the list and on the result page (datasets test each variant on both screens).
  2. tamanoLegible(): "2\u{00A0}MB" (help text and max error asserted with the no-break space).
  3. Upload progress: one role="status" region `#archivo-carga` in the x-campo `pie` (outside the input's
     aria-describedby, so announced by the live region only), always present; "Cargando archivo…" (wire:loading) at
     start and, when the upload ends, server text "Archivo listo para subir: <nombre>." (wire:loading.remove).
  4. Resultado column: "Sin resultado aún" (pendiente/procesando), "No se completó" (fallida), "N de M invitadas".
  5. Staleness: chosen option = static hint "Abrir el resultado para ver el avance." under unfinished rows (no list
     polling: avoids repeated re-renders/announcements on a paginated table; the result page already polls).
  6. Polling bound: config importaciones.minutos_de_seguimiento (env IMPORTACIONES_MINUTOS_DE_SEGUIMIENTO, default 10)
     since created_at; after it the poll attribute is removed and the status region becomes an Aviso: "La carga
     masiva está demorando más de lo esperado y esta página dejó de actualizarse. Recargar la página más tarde o, si
     sigue sin terminar, contactar a soporte de VINKURED." Frozen-time tests: 09:59 polls, 10:00 stops (both pending
     states); limit read from config; a late-finished import renders normally.
  7. Naming: "Resultado de la carga masiva" (title/h1), "Carga masiva terminada…", "Procesando la carga masiva…",
     "Volver a la carga masiva", "Cargas masivas recientes (N)" (h2 + table caption).
  8. Fallida: status text "La carga masiva no se pudo completar. Revisar el archivo y volver a cargarlo."; the single
     role="status" region is styled as Aviso (fallida, demorada or file-level errors) and lists the file-level
     messages inside it (the separate Aviso block was removed: one announced region).
  9. Stat tiles: rounded-tarjeta.
  10. Row errors: paginated in memory, 20/page (clamped), host x-paginacion only when >1 page, focus to
      h2#titulo-errores (tabindex -1); caption "Filas con error" and th scope kept.
  11. "Carga masiva" secondary x-button (route importaciones.index) in the page-header actions of
      invitaciones-pendientes and nueva-invitacion (dataset test on both routes).
  12. ValidadorDePlanilla: "Dividirla en archivos más pequeños." (unit test updated).
- Verification (C4.3 correction pass): public tables 54 before / 54 after; vendor/bin/pest --compact: 649 passed (8772
  assertions); vendor/bin/pint --dirty --format agent: passed; npm run build: OK. Visual check pending (no browser).
- Proposal for the Vinkured brief (badge mapping): import estados as above.

## Demo environment

Local demo to see the screens in a browser with FICTITIOUS data only, in schema `acompanamiento_demo` of roberto_dev.

- Setup (idempotent, re-run any time; recalculates invitation deadlines from the seeding moment): `composer demo:setup`
  = `CREATE SCHEMA IF NOT EXISTS acompanamiento_demo` (psql) + `php artisan migrate --force` (the 0000 migration loads
  the host schema snapshot into the demo schema) + `php artisan db:seed --class=DemoSeeder --force`.
- Serve: `composer demo:serve` (`php artisan serve --host=127.0.0.1 --port=8000`), then http://127.0.0.1:8000.
- Env overrides: script `demo:env` uses Composer `@putenv` (APP_ENV=local, DB_CONNECTION=pgsql,
  DB_HOST=/var/run/postgresql, DB_PORT=5432, DB_DATABASE=roberto_dev, DB_USERNAME=roberto, DB_PASSWORD=,
  DB_SEARCH_PATH=acompanamiento_demo, QUEUE_CONNECTION=sync, MAIL_MAILER=log, SESSION_DRIVER=database,
  CACHE_STORE=database). Real environment variables win over `.env`, so `.env` is neither read nor changed.
- Credentials: `admin@demo.example.cl` / `Demo2026demo` (Administrador, directivo => module administrator and
  observer). Active docentes `{beatriz,carlos,daniela}.docente@demo.example.cl`, same password (Beatriz is jefe of 1° A).
- Data (database/seeders/Demo/EscenarioDemo.php): "Colegio Demostración" (RBD 999001, fictitious region/comuna),
  current anio escolar, 1° A/1° B; invitations En plazo (+5 days), Vence hoy (end of today), Vencida (−3 days) and
  Anulada (no hash/expiry), hashes = SHA-256 of random tokens nobody knows, no mail sent; imports completada (5 rows,
  2 row errors), fallida (file-level error) and pendiente (created now: the result page polls until the 10-minute
  limit). RUTs 11111111-1…88888888-8 (valid check digit, fake), emails @demo.example.cl.
- Safety: DemoSeeder::asegurarEsquemaDemo() refuses (before any write) unless the default connection is pgsql with
  search_path exactly `acompanamiento_demo` AND the live session's `current_schema()` is `acompanamiento_demo`.
  HostSchemaLoader still refuses a search_path that includes public. Nothing reads or writes `public`.
- Tests: tests/Feature/Demo/DemoSeederTest.php (refusal under acompanamiento_test, public, `acompanamiento_demo,public`,
  empty, non-pgsql, config/session mismatch; nothing written on refusal; admin login/gate; every badge rendered by the
  list; three import results; second run = same row counts and refreshed deadlines). Scenario tested directly in the
  test schema (EscenarioDemo), the guard through DemoSeeder.
- Evidence (2026-10-24): public tables 54 before / 54 after; tests/Feature/Demo 11/11; vendor/bin/pest --compact: 663
  passed (8817 assertions); pint: passed; `composer demo:setup`: migrations DONE + DemoSeeder DONE; acompanamiento_demo
  holds 64 tables (53 host + migrations + 10 module); seeded usuarios=8 membresias=8 (4 invitadas) importaciones=3
  cursos=2 funciones=4 indicadores=60; admin found (es_administrador, activa); re-run of `composer demo:setup`: same
  counts (8/8/3). Server not started by the writer.

## Next step
Read-only auditor for C4.2 (form + list), ≤1 correction pass, native review, then C4.3.
