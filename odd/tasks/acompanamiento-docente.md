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
- Authentication is provided by the host platform (pending team confirmation); module adds no login.

## Delivery strategy
ask-on-risk. Forecast > 400 authored lines overall → one PR per work unit (chained).

## Tasks
- [x] T1 Data model: migrations, enums, Eloquent models, relationships, factories, tests.
- [x] T1.1 Review follow-ups (advisory, from T1 review): guard Observation::finish() against
  re-run (R3-001); remove fake()->unique() 1..60 / 1..10 collisions in Indicator and
  RubricTemplate factories before T2 seeds real numbers (R3-002); clear is_observer on demotion
  from admin roles (R3-003); fail loudly when a score's indicator is missing from the snapshot
  (R3-004); review R3-005 (Observation.php:54-56).
- [x] T2 Catalog seeder: 10 rubric templates, sections, 60 indicators from the source PDF.
- [x] T1.2 Review follow-ups (advisory, from T1.1+T2 review): demotion must always clear
  is_observer — the "keep if re-flagged in same save" branch is unreachable for real admins;
  fix docblock and replace the raw-query test (R3-001/R3-002); make finish() guard
  concurrency-safe via conditional update (R3-003); domain exception for snapshot entries with
  missing keys (R3-004); handle renamed sections on re-seed (R3-005).
- [x] T3 Roles and authorization policies (admin, observer, teacher, organization).
- [ ] T3.1 Review follow-ups (advisory, from T1.2+T3 review, commits 07096e0..b3397fc, approved
  and acknowledged): section swap on re-seed can collide on names (R3-001); finish() conditional
  update skips Eloquent events — fire them explicitly or document (R3-002); organizationAdmin()
  factory fallback (R3-003); R3-004 DECIDED by user: ex-observers LOSE access to teacher
  reports 6.4 once they lose the observer flag or change school (privacy); school admins keep
  the full history.
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
- T1 commit f97e40e. Native review: full branch range exceeded lens budget (mostly Boost
  vendor skills); reviewed data-model commit only (base ee59f4b), risk medium, consent granted,
  lens review-reliability → approved with 5 advisory findings, acknowledged (authority burned).
- T1.1+T2 route: delegated writer (trigger: 2+ non-trivial files).
- T1.1 evidence: RED observed for finish() re-run, missing snapshot indicator, admin demotion and
  factory/catalog number collision (UNIQUE constraint on rubric_templates.number); all GREEN after fix.
  R3-001 → ObservationAlreadyFinishedException; R3-002 → factory numbers 1000..999999 (unique);
  R3-003 → is_observer reset when leaving Principal/CurriculumHead unless re-flagged in a dirty save;
  R3-004 → IndicatorNotInSnapshotException; R3-005 reviewed, no defect (creating hook only fills
  null snapshot / started_at; regression test added and passed on first run).
- T2 evidence: CatalogSeeder (updateOrCreate on template number, (template, section name),
  indicator number; one DB transaction) called from DatabaseSeeder; 7 seeder tests RED (class missing)
  then GREEN. `migrate:fresh --seed` OK → 10 templates / 13 sections / 60 indicators, re-seed keeps
  counts. `php artisan test --compact` 40 tests / 84 assertions passing; `pint --dirty --test` passed.
- Commits 8a772c5 (T1.1), cf0eeb0 (T2). Native review base f97e40e, risk medium, consent granted,
  lens review-reliability → approved with 5 advisory findings (tracked as T1.2), acknowledged.

- T1.2+T3 route: delegated writer (trigger: 2+ non-trivial files).
- T1.2 evidence: RED 5 failing (stale-copy finish not rejected; incomplete snapshot entry raised
  ErrorException; 3 seeder tests, seedCatalog() missing). User demotion simplification is a refactor
  (old branch unreachable), its model-level tests passed before and after. GREEN after fix:
  finish() = conditional UPDATE ... WHERE status = en_curso + refresh(); IndicatorNotInSnapshotException::incomplete();
  CatalogSeeder::seedCatalog(array) public, sections keyed by (template, position), trailing sections
  deleted only when empty.
- T3 evidence: RED 19/37 policy tests failing (denials passed trivially without policies). Found and
  fixed pre-existing factory defect: UserFactory staff states overrode `for($school)` (states apply
  after parent resolvers), so CoachingAssignmentTest users were silently in different schools.
  Policies (auto-discovered): RubricTemplate, Indicator, School, Rubric, CoachingAssignment, Observation;
  ReportPolicy registered as gates viewSchoolReports / viewTeacherReport in AppServiceProvider.
  CoachingAssignment saving guard throws InvalidCoachingAssignmentException (unflagged observer, self,
  or other school). Decision: rubric export = view (any school staff + org admin).
- T1.2+T3 checks: `migrate:fresh --seed` OK (10/13/60); `php artisan test --compact` 84 tests /
  253 assertions passing; `pint --dirty --test` passed.

## Next step
T4 — school setup + staff bulk import from Excel.
