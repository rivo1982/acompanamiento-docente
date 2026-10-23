# Feature: Teacher Coaching Module (Acompañamiento Docente)

## Objective
Laravel module that lets school leaders (Dirección/UTP) configure observation rubrics from a
platform-provided catalog, assign observers to teachers, record formative classroom
observations, deliver automatic + personal feedback with pedagogical support tips, and report
aggregated data per organization, school, rubric, indicator and teacher.

## Problem / Why
Coaching is done on paper; data is lost and teachers get no structured, actionable feedback.

## Scope (backend first, frontend later)
- Organization (sostenedor: SLEP, DEM, DAEM, Corporación, Fundación) → schools (RBD) → staff.
- Global catalog: 10 rubric templates, sections, 60 numbered indicators, 4 level texts,
  one feedback text per indicator, pedagogical tips (apoyo pedagógico).
- School rubric = name + indicators selected from the catalog; editable by admins only.
- Observer → teacher assignments (unlimited).
- Observation: auto date/teacher/duration, observer sets subject/level/course, one level per
  indicator + optional personal feedback; snapshot of rubric content at observation time.
- Reports (6.1–6.4) with role-based visibility (7.1–7.3). PDF export.

## Constraints and decisions
- Exactly 4 achievement levels (en_inicio, en_desarrollo, logrado, destacado); the level
  meaning table is shown on every rubric. User rejected a 5th "not observed" level.
- "Felicitaciones" is appended automatically for logrado/destacado.
- Scale is formative, not evaluative: never suggest equivalence with Evaluación Docente.
- Principals (directivo) and UTP heads are observers automatically; any teacher may be
  flagged as observer.
- Code identifiers in English, UI copy in Spanish. Glossary lives in docs/glossary.md.
- Pedagogical tips: structured data (tool, target level, how, example, time, success signal),
  3 per indicator; content must be validated by the user's pedagogy team.
- Platform stack: PHP/Laravel (team's version and frontend stack still unknown; built on 13.x).

## Delivery strategy
ask-on-risk. Forecast > 400 authored lines overall → one PR per work unit (chained).

## Tasks
- [x] T1 Data model: migrations, enums, Eloquent models, relationships, factories, tests.
- [ ] T2 Catalog seeder: 10 rubric templates, sections, 60 indicators from the source PDF.
- [ ] T3 Roles and authorization policies (admin, observer, teacher, organization).
- [ ] T4 School setup + staff bulk import from Excel.
- [ ] T5 School rubric configuration + PDF export.
- [ ] T6 Observer → teacher assignments.
- [ ] T7 Observation flow (start, score, feedback, finish, deliver to teacher, PDF).
- [ ] T8 Reports 6.1–6.4 with role visibility.
- [ ] T9 Pedagogical tips content (draft for 60 indicators, pending pedagogy review).
- [ ] T10+ Frontend (stack pending team answer).

## Progress / Evidence
- Setup: Laravel 13.38 project created, pushed to github.com/rivo1982/acompanamiento-docente
  (main @ af3a390). Branch feat/data-model created for T1.
- T1 route: delegated writer (trigger: 2+ non-trivial files).
- T1 evidence: RED observed (23/25 failing before implementation); GREEN `php artisan test --compact`
  25 tests / 51 assertions passing; `migrate:fresh` OK (12 new migrations, up/down verified);
  `pint --dirty --test` passed. Enum case renamed InProgressStart -> Beginning (value `en_inicio` kept).

## Next step
T2 — catalog seeder (10 templates, sections, 60 indicators).
