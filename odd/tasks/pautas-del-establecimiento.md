# Feature: School rubrics (Pautas del establecimiento)

Covers T5 of odd/tasks/acompanamiento-docente.md: school rubric configuration + PDF export.

## Objective
The module administrator (directivo) builds the establecimiento's own rubrics (pautas) from the
platform catalog (10 pautas base → ámbitos → 60 numbered indicadores), edits and deletes them, and
any funcionario of the establecimiento can view and print/save a rubric as PDF.

## Problem / Why
Observations (T7) are made with a school rubric; today the model and policy exist (Pauta,
IndicadorPauta with `posicion`, PautaPolicy, Pauta::toSnapshot) but there is no way to create,
edit, view or print one.

## Scope
- List the establecimiento's pautas (name, number of indicators, last update).
- Create / edit a pauta: name + indicators chosen from the catalog, grouped by pauta base and
  ámbito, with a shortcut to select all indicators of a pauta base. Indicator order follows the
  catalog numbering.
- Delete a pauta (soft delete; Pauta uses SoftDeletes). Decide in P1 what happens when a pauta
  already has acompanamientos (acompanamientos store a snapshot).
- View a pauta: indicators with their 4 level descriptors, plus the level-meaning table (shown on
  every rubric, constraint from the business plan).
- Print view for "Imprimir o guardar PDF" via the browser's print dialog.

## Constraints and decisions
- DECIDED (user, 2026-10-30): PDF through the browser's print dialog ("Imprimir o guardar PDF") with
  a print-ready view — no new PDF dependency (Vinkured has none).
- Exactly 4 achievement levels (en_inicio, en_desarrollo, logrado, destacado); the level meaning
  table is shown on every rubric. Formative scale: never suggest equivalence with Evaluación Docente.
- Admin-only for create/edit/delete (PautaPolicy: esAdministrador); view/print for any funcionario
  of the active establecimiento.
- Spanish identifiers/UI copy as in the module; UI per .agents/skills/vinkured-ui/SKILL.md;
  @host-owned files never edited; tests in schema acompanamiento_test; never touch roberto_dev public.

## Delivery strategy
ask-on-risk. Forecast ~900–1200 authored lines → one review slice per task.

## Tasks
- [x] P1 Backend: GuardarPauta (crear/actualizar, NOMBRE_MAXIMO 120), EliminarPauta (soft delete),
  read helper App\Support\CatalogoDePautas (catalogo / pautasDelEstablecimiento / indicadoresDe).
  Decisions: posicion = order by indicador numero (= catalog order); name trimmed, unique
  case-insensitively among non-deleted pautas of the establecimiento under a per-establecimiento
  advisory lock (no DB unique index); update and delete allowed with acompanamientos (each keeps its
  pauta_snapshot; a deleted pauta cannot start new acompanamientos). RED 27/27 → GREEN; suite 739.
  Commit 74f41e6. Route: delegated. Review (base 3295513, medium, 810 lines incl. home links
  2de45f2): 1 lens (reliability), APPROVED and acknowledged (lineage review-e87a7271cbc79211).
  Reviewed boundary → 74f41e6.
- [x] P1.1 Review follow-ups: indicadoresDe order after a reordering actualizar (fails without
  ORDER BY posicion, proven with a throwaway check); 120 multibyte chars accepted / 121 rejected.
  Commit 1c17be6. Route: delegated (with P2/P3).
- [x] P2 Screens: `pautas` list (any funcionario; admin: Nueva/Editar/Eliminar with confirmation),
  `pautas/nueva` + `pautas/{pauta}/editar` form (name + catalog picker in <details> per pauta base,
  "Seleccionar todos"/"Quitar todos", live counts with aria-live). RED 17/17 → GREEN.
  Route: delegated. Parent correction: GuardarPauta copy to infinitive per docs/diseno.md
  ("Escribir…", "Elegir…").
- [x] P3 View `pautas/{pauta}`: level-meaning table (NivelLogro::tablaSignificados) + indicators by
  ámbito with 4 descriptors, "Imprimir o guardar PDF" (window.print()), print: variants. Parent
  correction: host layout has no print variants → module view adds an inline @media print rule
  hiding #barra-lateral, the skip link and the layout header (no host edit); proposal for Vinkured:
  add print:hidden to those layout elements. Test-first (RED → GREEN). Commit fe7acb2 (P2+P3).
- [x] P4 Demo data: PAUTAS "Observación de aula (demo)" (indicators 1–8) and "Planificación y
  convivencia (demo)" (12, 13, 14, 46, 47); home card "Pautas" for any funcionario; home copy to
  infinitive. Test-first (RED → GREEN). Commit 05253f7. Demo reseeded (2 pautas, 13 rows; public 54).
  Route: inline. Suite 760 passing.
  Review (base 74f41e6, medium, 1047 lines): 1 lens (reliability), APPROVED and acknowledged
  (lineage review-f1312a57ad5bbf0f). Reviewed boundary → 05253f7.
- [x] P4.1 User visual check: user approved (2026-10-30, "quedó perfecto"). Also fixed: site root showed
  Laravel's welcome page whose "Dashboard" link 404'd → `/` redirects to `/panel` (commit c9536ef,
  test-first; removed welcome view and framework ExampleTest). Assess: medium, under_budget.
- [ ] P3.1 Review follow-ups (advisory): R3-001 VerPauta groups by ámbito relying on the relation's
  orderByPivot — add explicit ordering in the query and a view test where catalog order ≠ posicion
  order. R3-002 FormularioDePauta: filter non-scalar `indicadores` entries in render() and
  quitarTodos() like guardar() (tampered payload → 500).
- needs_brief for the Vinkured team: print/PDF document design "se define aparte" in the brief;
  print chrome rule above; "Pautas" navigation entry (host-owned layout menu).

## Acceptance criteria
- Admin can create, edit and delete pautas; non-admins get 403 on those; data never crosses
  establecimientos.
- Any funcionario of the establecimiento can view and print a pauta, including the level table.
- Full Pest suite green.

## Progress / Evidence
- 2026-10-30: continuing on branch feat/observadores (pautas build on it; the user pushes).

## Next step
P3.1 follow-ups; then T7 observation flow (acompañamiento).
