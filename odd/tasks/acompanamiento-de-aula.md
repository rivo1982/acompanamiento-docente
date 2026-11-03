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
  - Apoyo pedagógico tips are read live from the catalog; today no tips exist (T9 content pending),
    so the section only shows when tips exist.
  - The snapshot gains the ámbito name per indicator so results group like the pauta view.
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
- [ ] A2 Observer screens: "Mis acompañamientos" (observer), "Nuevo acompañamiento", scoring form
  (levels + level-meaning table + personal feedback, Finalizar with confirmation). vinkured-ui.
  Route: delegated (writer).
- [ ] A3 Result view (teacher/observer/admin) with automatic feedback, Felicitaciones, apoyos when
  present, print layout; teacher's list. vinkured-ui. Route: delegated (writer).
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
