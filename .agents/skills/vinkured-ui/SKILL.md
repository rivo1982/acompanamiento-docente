---
name: vinkured-ui
description: "Trigger: any UI work on Vinkured (screens, Livewire components, layouts, Blade views). Orchestrate design skills per screen: brief, writer, audit, visual check, tests, one commit."
license: Apache-2.0
metadata:
  author: vinkured
  version: "1.0"
---

## Activation Contract

For the ORCHESTRATOR (the main agent that delegates to bounded workers). Load when the request involves UI on Vinkured. Workers do not load this skill and do not rediscover skills: the orchestrator passes exact `SKILL.md` paths under `## Skills to load before work`.

## Preconditions

`docs/diseno.md` must exist. If it does not, ask the user ONE question to obtain it (offer the template below), and stop UI work until the file exists. Any worker returning `needs_brief` sends the orchestrator back here: ask the user one question, update the brief, relaunch.

Brief template (Spanish, `docs/diseno.md`):

- Identidad: nombre, personalidad, qué transmite a funcionarios y apoderados.
- Paleta: colores base con contraste AA verificado (texto 4.5:1, componentes 3:1), estados (éxito, alerta, error), escala z-index.
- Tipografía: familias, roles, escala.
- Espaciado y radios: escala y radios por jerarquía.
- Componentes base: botón, campo, tabla, modal de confirmación, aviso, insignia, navegación.
- Tono y copy en español de Chile: tuteo o usted, términos de `CONTEXT.md`, mensajes de error y de estado vacío.
- Densidad de tablas y formularios.
- Estados vacíos, de error y de carga.
- Excepciones a las reglas por defecto de `baseline-ui` (degradados, animación) si las hay.
- Valores por defecto permitidos: lista explícita de propiedades que pueden quedar en los valores por defecto de Tailwind (por ejemplo "z-index: Tailwind por defecto"). Todo lo que no esté aquí ni en las secciones anteriores es `needs_brief`.

## Hard Rules

- No real data of minors in views, fixtures, seeders or screenshots; fictitious deterministic data only.
- WCAG 2.2 AA minimum.
- UI copy in Spanish (Chile).
- Mobile-first.
- No new UI dependency (component library, icon set, JS framework, font service) without an ADR.
- Precedence: `docs/diseno.md` brief > skill rules. When the brief lacks a token the screen needs for color, typography, spacing or radius, the worker STOPS and returns `needs_brief` listing the missing tokens. Falling back to framework defaults is NOT allowed for those four families; Tailwind defaults are allowed only for properties the brief lists under "Valores por defecto permitidos" (for example "z-index: Tailwind defaults").
- Skills never fetch from the network at run time.
- Design decisions not in the brief go back to the orchestrator as `needs_brief`; never improvised by workers.
- One writer at a time; the auditor is read-only.
- Commits: Conventional Commits, no AI attribution; tests and docs with the unit.

## Roles

| Role | Mode | Skills to load (exact paths) |
|---|---|---|
| Orchestrator | coordinates, asks the user | this skill |
| Writer worker | writes Blade/Livewire/Tailwind and tests; obeys the precedence rule (brief first, `needs_brief` on missing color/type/spacing/radius tokens) | `.agents/skills/frontend-design/SKILL.md`, `.agents/skills/baseline-ui/SKILL.md`, plus `docs/diseno.md` and `CONTEXT.md` as context |
| Auditor worker | read-only over the diff | `.agents/skills/web-design-guidelines/SKILL.md` (it reads its own `references/`) |
| Visual verifier | browser at 375, 768, 1280 px | browser-verification skill resolved by name through the skill registry; do not hardcode its path |

## Decision Gates

| Condition | Action |
|---|---|
| `docs/diseno.md` missing | Ask the user one question; stop UI work. |
| Worker returns `needs_brief` | Ask the user one question about the exact missing items; update the brief; relaunch. |
| Audit has blockers or findings | One correction pass by the same writer, then re-audit only the touched lines. No second pass. |
| Findings remain after the correction pass | Report them to the user as pending; do not loop. |
| Visual check fails at a breakpoint | Counts as the correction pass if unused; otherwise report. |
| Request needs a new UI dependency | Stop; ADR first. |

## Execution Steps (per screen)

1. Create the ODD feature document for the screen (tasks plus Engram mirror) before the first write.
2. Delegate ONE writer worker with `## Skills to load before work` (paths above), `## Allowed edit surfaces`, the brief path and the Pest/Livewire tests expected.
3. Delegate ONE read-only auditor worker over the writer's diff with `web-design-guidelines`.
4. At most ONE correction pass for audit findings.
5. Visual verification at 375, 768 and 1280 px with the browser-verification skill; use fictitious data.
6. Run Pest and Livewire tests (`php artisan test`, test database only).
7. One work-unit commit per screen: Conventional Commit, tests and docs included, no AI attribution. Record the commit in the feature document.

## Output Contract

Return per screen: files changed, brief tokens used, audit result (pass or pending findings), viewports verified, tests run with observed result, commit hash, and any `needs_brief` raised or open.

## References

- [../frontend-design/SKILL.md](../frontend-design/SKILL.md)
- [../baseline-ui/SKILL.md](../baseline-ui/SKILL.md)
- [../web-design-guidelines/SKILL.md](../web-design-guidelines/SKILL.md)
