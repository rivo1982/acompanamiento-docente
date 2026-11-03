# Feature: Classroom observation flow (Acompañamiento)

Covers T7 of odd/tasks/acompanamiento-docente.md: start, score, feedback, finish, deliver to the
teacher, print/PDF.

## Objective
An observer starts an acompañamiento for a teacher assigned to them, using a school pauta, scores
one level per indicator with optional personal feedback, and finishes it. The teacher is notified
by email and sees the result (levels, automatic feedback, "Felicitaciones", apoyo pedagógico when
available) in the module, and can print/save it as PDF.

## Problem / Why
Observations are the core of the module. The data layer exists and is guarded (Acompanamiento,
RespuestaAcompanamiento, pauta_snapshot, DB trigger, AcompanamientoPolicy) but there are no actions,
screens, notification or print view.

## Scope
- Observer: list of their acompañamientos (en curso / finalizados), start a new one (assigned teacher,
  pauta, curso, asignatura), scoring form, discard an en-curso one, finish.
- Teacher: list of their finished acompañamientos and the result view.
- Admin (directivo): can view any acompañamiento of the establecimiento (existing policy).
- Email to the teacher on finish (notice + link, no results in the email).
- Result view printable via the browser ("Imprimir o guardar PDF").

## Constraints and decisions
- DECIDED (user, 2026-11-03): on finish the teacher gets an email notice with a link AND sees it in
  the module; the email carries no results.
- Defaults chosen by the parent (reported to the user; change on request):
  - Every indicator must have a level before finishing (exactly 4 levels; the user rejected a
    "no observado" level).
  - No editing or reopening after finishing (already enforced by model guard + DB trigger).
  - `iniciado_en` is set by the server only, never from input (fillable today → do not pass it).
  - Curso and asignatura are chosen from the establecimiento's lists; no forced match with the
    teacher's AsignacionDocente (pre-select when obvious).
  - An en-curso acompañamiento can be resumed or discarded (deleted) by its observer.
  - Apoyo pedagógico: SUPERSEDED by the user's annex (below).
  - The snapshot gains the ámbito name per indicator so results group like the pauta view.
- DECIDED (user, 2026-11-03): the apoyo pedagógico the observed teacher receives after the observer
  finishes is the user's annex "Anexo de apoyo pedagógico" (60 fichas, one per descriptor, 6 parts:
  foco de mejora, acción metodológica, acción procedimental, acción formativa, evidencia de avance,
  referencias y recursos), plus its general guidance (ciclo sugerido de acompañamiento, nota de uso,
  protocol note for descriptors 42 and 45, plan de mejora template). It must have an attractive style
  for the teacher. Transcribed to database/seeders/data/apoyo_pedagogico.json (60/60, names match
  catalog.json exactly). The old 3-tips-per-indicator apoyos_pedagogicos structure (never filled) is
  replaced by one ficha per indicator.
  - Display default (parent): the result shows each indicator's level and automatic feedback; fichas
    for indicators below Destacado, with En inicio / En desarrollo first (the annex's cycle: pick 1–2
    to work on); Destacado shows Felicitaciones without a ficha; 42/45 show the protocol note;
    printable "Plan de mejora acordado" template at the end.
- PDF via browser print, same pattern as pautas (no new dependency).
- Spanish identifiers; UI copy infinitive per docs/diseno.md; vinkured-ui process for screens;
  @host-owned never edited; tests in acompanamiento_test; never touch roberto_dev public.

## Delivery strategy
ask-on-risk. Forecast ~1500–2000 authored lines → one review slice per task.

## Tasks
- [x] A1 Backend: IniciarAcompanamiento / GuardarRespuesta (max 2000) / DescartarAcompanamiento /
  FinalizarAcompanamiento (all indicators required; mail queued after commit to the teacher's
  usuario correo via active membership; no account → log warning, no mail); Mailable
  AcompanamientoFinalizado (RUTA acompanamientos.show, placeholder 404 route until A3); `ambito` in
  snapshot; Acompanamiento::nombreAsignatura() (no Asignatura model exists). RED 20/20 → GREEN; parent
  fixed PautaTest exact snapshot assertion; suite 780. Commit 2f48ed2. Route: delegated.
  Review split (lens budget exceeded by the 60-ficha JSON): slice 05253f7..c2e1356 (data + root
  redirect + docs) via worktree, APPROVED/acknowledged (review-4d59a15cc7baa96e); slice
  c2e1356..2f48ed2 APPROVED/acknowledged (review-dfd255be352ea2d5). Reviewed boundary → 2f48ed2.
- [x] A1.1 Review follow-ups (advisory, DONE in ab1661e except the email-link page test, which moves to A3): resolve observador_id from the explicit $observador user
  (not ambient RolesDelModulo context) and fail loudly if none; catch+log failures in
  avisarAlDocente after commit (finish must not throw once committed); in-transaction recheck order
  (authorize before revealing finalized state) + test; assert the Log::warning when the teacher has
  no account; the email link 404 is resolved by A3 (add a test that the linked page responds).
  Data: A1.5 seeder tests must prove 60 fichas, numeros 1..60 unique, names match catalog, no empty
  fields.
- [x] A1.5 Apoyo pedagógico data: migration 2026_11_03_000100 recreates apoyos_pedagogicos as one
  ficha per indicator (indicador_id unique + contexto + 6 text fields; down() restores old shape);
  Indicador::apoyoPedagogico() hasOne; ApoyoPedagogicoSeeder (updateOrCreate by numero, chained from
  CatalogSeeder::run, fails loudly on unknown numero); App\Support\ApoyoPedagogico\Orientaciones
  (comoUsar, cicloSugerido, fundamentoCiclo, notaDeUso, notaProtocolo, requiereProtocolo,
  planDeMejora); NivelObjetivoApoyo removed. RED → GREEN; suite 791. Commits ab1661e (A1.1),
  849e09a (A1.5). Route: delegated. Review (base 2f48ed2, medium, 562 lines): APPROVED/acknowledged
  (review-90374e304683f08b). Reviewed boundary → 849e09a.
- [ ] A1.6 Review follow-ups (advisory): prove seeder atomicity (bad numero on a LATER ficha, assert
  rollback); log the exception (not only the message) in avisarAlDocente and consider narrowing the
  catch so DB errors inside an outer transaction are not swallowed.
- [ ] A2 Observer screens: "Mis acompañamientos" (observer), "Nuevo acompañamiento", scoring form
  (levels + level-meaning table + personal feedback, Finalizar with confirmation). vinkured-ui.
  Route: delegated (writer).
- [ ] A3 Result view (teacher/observer/admin) with automatic feedback, Felicitaciones, apoyo
  pedagógico fichas (attractive, color-coded by action type, per display default above), ciclo
  sugerido, plan de mejora template, print layout; teacher's list. vinkured-ui. Route: delegated.
- [ ] A4 Demo data (one en curso, one finalizado), home card, user visual check.

## Acceptance criteria
- Only an observer assigned to the teacher can start; only its observer can score/finish/discard.
- Finishing requires all indicators; the teacher gets exactly one email per finish.
- The teacher sees only finished acompañamientos of their own; admin sees all of the school.
- Full Pest suite green.

## Progress / Evidence
- 2026-11-03: exploration done (data layer complete, no actions/screens). Branch feat/observadores.

## Next step
A2 (writer running), then A3.
