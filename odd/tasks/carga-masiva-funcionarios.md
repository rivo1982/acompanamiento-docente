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
  - [ ] C4.2 Screen: invitar funcionario + invitaciones pendientes (reenviar, anular).
  - [ ] C4.3 Screen: carga masiva (upload) + resultado de la importación.
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

## Next step
Parent: commit C4.0.1+C4.1, run the read-only auditor for C4.1 (vinkured-ui process), then C4.2 — invitar
funcionario + invitaciones pendientes.
