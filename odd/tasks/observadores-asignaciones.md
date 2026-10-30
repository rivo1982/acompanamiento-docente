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
- [ ] O1 Backend: actions to enable/remove an observer and to sync an observer's teachers, with
  authorization, tenancy and tests (Pest, test-first). Route: delegated (writer, 2+ non-trivial
  files).
- [ ] O2 Screen "Observadores" (Livewire, route under /usuarios or /acompanamiento), single-screen
  layout decided above, vinkured-ui process. Route: delegated.
- [ ] O3 Demo data: observers and assignments in EscenarioDemo; visual check by the user at
  http://localhost:18765.

## Acceptance criteria
- Admin can enable, remove and assign; non-admins get 403; data never crosses establecimientos.
- Automatic observers cannot be removed from the screen.
- Full Pest suite green.

## Progress / Evidence
- 2026-10-30: branch feat/observadores created from 8ac7b4f.

## Next step
O1.
