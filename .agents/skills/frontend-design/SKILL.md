---
name: frontend-design
description: "Trigger: building or reshaping a Vinkured screen, layout, Blade view or Livewire component visually. Produce intentional, accessible, consistent UI in Blade + Livewire + Tailwind, driven by docs/diseno.md."
license: Apache-2.0
metadata:
  author: vinkured (adapted from anthropics/skills)
  version: "1.0"
---

<!--
Provenance
- Source: https://github.com/anthropics/skills (skill `frontend-design`)
- Commit: dbd4588f9e1033efb41dad4bef2f7947c8993d44
- License: Apache-2.0 (full text in LICENSE.txt in this folder, verbatim from upstream)
- Modified by the Vinkured project (Apache-2.0 section 4(b)). Changes:
  1. Output targets Blade + Livewire + Tailwind utility classes (no React assumptions).
  2. Reframed for internal administrative software used by school staff and guardians: clarity, accessibility and consistency win over aesthetic risk.
  3. UI language is Spanish (Chile).
  4. Brief and design tokens come from docs/diseno.md; missing brief returns `needs_brief`.
  5. Removed the "confirm with the client" step and the double ASCII wireframe pass so delegated workers do not stall.
  6. Added hard rule: no real personal data of minors in examples, fixtures or screenshots.
  7. Condensed upstream prose; the studio-persona framing was removed.
  8. Added an explicit precedence rule: the `needs_brief` stop rule overrides default-values guidance in other loaded skills.
-->

## Activation Contract

Load when a worker builds or reshapes UI for Vinkured: screens, layouts, Blade views, Livewire components. Always loaded together with `baseline-ui`. The product is internal software for school staff and guardians; it is not a marketing page.

## Hard Rules

- The brief is `docs/diseno.md`. Read it first. If it is missing, or lacks a token you need (color, type, spacing, radius, component, tone), STOP and return `needs_brief` listing exactly what is missing. Never invent brand identity. Never ask the end user: the orchestrator asks. This stop rule takes precedence over any default-values guidance in other loaded skills (for example `baseline-ui`): for color, typography, spacing and radius, framework defaults are allowed only where the brief explicitly leaves that property at default.
- Output is Blade + Livewire + Tailwind utility classes. Use the project's existing components and tokens before creating new ones.
- Clarity, accessibility (WCAG 2.2 AA minimum) and consistency win over aesthetic risk. Spend boldness in at most one place, and only where the brief allows it.
- UI copy is Spanish (Chile): neutral, plain, sentence case, with the tone (tú/usted) set by the brief.
- Mobile-first; verify layout at 375, 768 and 1280 px.
- Never use real personal data of minors (names, RUT, photos, cases) in examples, fixtures, seeders or screenshots. Use clearly fictitious, deterministic data.
- Never add a UI dependency (component library, icon set, JS framework, font service) without an ADR.
- Where the brief fixes a direction, follow it exactly. Where it explicitly leaves a non-token axis free (never color, type, spacing or radius), choose deliberately for the subject (school coexistence management), not the generic default.

## Design principles

- Subject first: the subject is school management (cases, notices, alerts, referrals). Let its vocabulary from `CONTEXT.md` shape labels and structure.
- Typography carries hierarchy: one family or two clearly distinct, a clear type scale from the brief, line length under 80 characters for prose.
- Structure is information: borders, dividers, numbering and labels must encode something real. Number items only when the content is a sequence.
- Avoid template tells: a tracked-out ALL-CAPS eyebrow above every heading, accenting a single word of a headline, identical rounded cards with the same soft shadow everywhere, gradient washes, a "→" on every link, middot-joined meta strings.
- Motion answers a user action (open, expand, confirm). No decorative entrance effects. Respect `prefers-reduced-motion`.
- Quality floor, never announced: responsive to mobile, visible keyboard focus, sufficient contrast, harmonious palette taken from the brief.

## Writing in design (Spanish, Chile)

- Words exist to make the interface easier to use. Name things as users understand them, not as the system is built.
- Active voice. A button says what happens ("Guardar cambios", not "Enviar"). An action keeps the same name across the flow (button "Publicar", toast "Publicado").
- Errors say what went wrong and how to fix it, without apologizing or vagueness. Empty states invite one clear action.
- Use domain terms from `CONTEXT.md`; never "Denunciado".

## Decision Gates

| Condition | Action |
|---|---|
| `docs/diseno.md` missing or token absent | Stop; return `needs_brief` with the missing items. |
| Needed component exists in the project | Reuse it. |
| Needed pattern is new and the brief covers it | Build it as a Blade component using brief tokens. |
| Needed pattern is new and the brief is silent | Return `needs_brief`; do not improvise. |
| Request implies a new UI dependency | Stop; an ADR is required first. |

## Execution Steps

1. Read `docs/diseno.md`, `CONTEXT.md` and the existing views/components relevant to the screen.
2. Write a compact plan in your own notes: tokens used (from the brief), layout in one sentence per breakpoint, states (empty, loading, error), copy. Check it once against the brief; revise anything that is a generic default rather than a brief-driven choice. No wireframe drafts.
3. Build Blade/Livewire markup with Tailwind utilities. Keep CSS specificity simple; prefer utilities over custom selectors.
4. Self-critique once: keyboard focus, contrast, empty/error/loading states, mobile layout, copy consistency, absence of minors' real data.

## Output Contract

Return the list of files changed, the brief tokens used, states covered, and any `needs_brief` items. If blocked: `needs_brief` with the exact missing tokens and nothing else.
