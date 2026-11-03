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
- [ ] A1 Backend: IniciarAcompanamiento, GuardarRespuesta (upsert level + personal feedback),
  DescartarAcompanamiento, FinalizarAcompanamiento (all indicators answered; queue the teacher email
  after commit), ámbito in snapshot, Acompanamiento::asignatura(), mail class + template; tests
  test-first. Route: delegated (writer).
- [ ] A1.5 Apoyo pedagógico data: new migration replacing apoyos_pedagogicos with one ficha per
  indicator (6 text fields + contexto), seeder from apoyo_pedagogico.json (idempotent, by indicator
  numero), model + orientaciones accessor, tests. Route: delegated (writer, after A1).
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
A1.
