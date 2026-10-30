# Feature: Observers and assignments (Observadores)

Covers T6 of odd/tasks/acompanamiento-docente.md (observer → teacher assignments) plus the
management of the `observadores` flag.

## Objective
One admin screen where the module administrator (directivo) sees every observer of the active
establecimiento, enables or removes a teacher as observer, and chooses which teachers each
observer accompanies.

## Problem / Why
Observations (T7) need to know who may observe whom. Today the data model and policies exist
(Observador, AsignacionAcompanamiento, RolesDelModulo, AsignacionAcompanamientoPolicy) but there
is no way to manage them.

## Scope
- List observers of the active establecimiento: automatic ones (directivo functions, incl. Jefe de
  UTP) marked "automático" and not removable; enabled ones (`observadores` rows) removable.
- Enable a vigente funcionario as observer; remove an enabled observer (removal also ends their
  assignments — decide in O1 whether rows are deleted or kept for history, following T3.1 R3-004:
  ex-observers lose access to teacher reports).
- Per observer: choose the teachers they accompany (checkbox list of observable teachers, save).
- Admin only (same gate family as invitations); tenancy by active establecimiento.

## Constraints and decisions
- DECIDED (user, 2026-10-30): one single screen — observer list, clicking one shows and edits the
  teachers they accompany (not two separate screens).
- Domain identifiers and UI copy in Spanish, matching the existing module code (alignment A1–A5).
- UI process per .agents/skills/vinkured-ui/SKILL.md: writer → read-only auditor
  (web-design-guidelines) → ≤1 correction pass → visual check → tests → commit.
- Host-owned copies (@host-owned) are never edited.
- Test isolation: schema acompanamiento_test; never touch roberto_dev public.

## Delivery strategy
ask-on-risk. Forecast ~600–800 authored lines (backend actions + screen + tests) → slices per task.

## Tasks
- [x] O1 Backend: actions to enable/remove an observer and to sync an observer's teachers, with
  authorization, tenancy and tests (Pest, test-first). Route: delegated (writer, 2+ non-trivial
  files). Commit 43c8e87. Actions HabilitarObservador / QuitarObservador /
  AsignarDocentesAObservador; read helper App\Support\ObservadoresDelEstablecimiento; gate
  `gestionar-observadores` (= RolesDelModulo::esAdministrador). Removing an observer deletes its
  assignments (acompanamientos keep observador_id/docente_id directly, so history is kept).
  RED observed (29 tests, 0 passing) → GREEN; full suite 692 passing.
  Review (base 4ef4191, medium, 725 lines incl. demo serve fixes): 1 lens (reliability), APPROVED
  and acknowledged (lineage review-5c62aa39c0415c8e). Reviewed boundary → fba3c04.
- [x] O1.1 Review follow-ups (advisory): R3-001 sincronizar checks puedeObservar outside the
  transaction without locking the observer row → FIXED: locks the `observadores` row
  (lockForUpdate) and re-checks inside the transaction. R3-002 → FIXED: docentes_asignados counts
  only observable teachers (shared query with the checkbox list). R3-003 demo:serve bare `php` →
  KEPT: Composer only expands `@php` at the start of a script line, and `cd public` is required by
  server.php. RED observed (2 failing) → GREEN. Commit 451df10. Route: delegated (same writer as O2).
- [x] O2 Screen "Observadores": route `usuarios/observadores` (`observadores.index`, gate
  `gestionar-observadores`), enable form, observer table ("Automático" badge Neutro, assigned count,
  "Ver docentes"), detail card with teacher checkboxes + "Guardar docentes", "Quitar como
  observador" with confirmation modal (non-automatic only). RED 13/13 → GREEN. Commit 40f11f3.
  Route: delegated. Self-audit fixes: selected row not colour-only, touch target via
  --alto-control, removed undefined quote style. Open guideline note: lists not paginated (school
  size). needs_brief for the Vinkured team: "Automático" badge (Neutro) not in the brief's badge
  table; no checkbox control in the brief; selected-row pattern; no navigation entry (host-owned
  layout).
- [x] O3 Demo data: Beatriz enabled observer; Alicia (directiva, automatic) → Carlos, Daniela;
  Beatriz → Carlos. Test-first in DemoSeederTest (RED 0≠1 → GREEN). Commit 3295513. Route: inline.
  Demo reseeded (acompanamiento_demo: 1 observer, 3 assignments; public still 54 tables).
  Review (base fba3c04, medium, 813 lines): 1 lens (reliability), APPROVED and acknowledged
  (lineage review-2a75dca3f3d7372b). Reviewed boundary → 3295513.
- [ ] O3.1 User visual check at http://localhost:18765/usuarios/observadores (375/768/1280).
- [ ] O2.1 Review follow-ups (advisory): R3-001 the comment in AsignarDocentesAObservador claims a
  concurrent QuitarObservador always waits for the sync; that only holds if QuitarObservador locks
  or deletes the `observadores` row BEFORE deleting assignments → make QuitarObservador lock the row
  first, or fix the comment. R3-002 test the screen guard paths: quitar without confirmarQuita,
  quitar/guardar with no observer selected.

## Acceptance criteria
- Admin can enable, remove and assign; non-admins get 403; data never crosses establecimientos.
- Automatic observers cannot be removed from the screen.
- Full Pest suite green.

## Progress / Evidence
- 2026-10-30: branch feat/observadores created from 8ac7b4f. O1, O1.1, O2, O3 done; full suite
  709 passing.
- 2026-10-30: user could not find the screen from /panel → provisional home now links the module
  screens by permission (commit 2de45f2, test-first InicioTest RED→GREEN, suite 712). Assess: medium,
  under_budget (pending in slice from 3295513).

## Next step
O3.1 user visual check, then O2.1 follow-ups.
