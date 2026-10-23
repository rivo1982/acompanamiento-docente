---
name: baseline-ui
description: "Trigger: writing or polishing Vinkured UI (Blade, Livewire, Tailwind). Apply an opinionated baseline of spacing, interaction, animation, typography and layout guardrails to prevent generic UI."
license: MIT
metadata:
  author: vinkured (adapted from ibelick/ui-skills)
  version: "1.0"
---

<!--
Provenance
- Source: https://github.com/ibelick/ui-skills (skill `baseline-ui`)
- Commit: f18d13077d6e3841821ea0a36526c0fd44a570b5
- License: MIT, Copyright (c) 2026 Julien Thibeaut (text in LICENSE in this folder, fetched at that commit)
- Modified by the Vinkured project. Changes:
  1. Removed the JavaScript-framework-specific parts: animation library, class-merge helper, headless primitive libraries, CSS animation plugin, effect-hook guidance and the Stack section.
  2. Mapped the remaining rules to Blade, Livewire and Alpine; "AlertDialog" became an accessible confirmation modal.
  3. Upstream opinionated defaults (no gradients, no animation unless requested) kept as defaults overridable by docs/diseno.md.
  4. Removed the file-review command mode.
  5. Tailwind defaults now apply only to properties the brief explicitly leaves at default; missing color, type, spacing or radius tokens trigger `needs_brief`.
-->

## Activation Contract

Load for any Vinkured UI writing or polish, together with `frontend-design`. Rules marked MUST/NEVER are guardrails; defaults marked (default) yield to an explicit decision in `docs/diseno.md`.

## Hard Rules

### Stack
- Tailwind defaults apply only to properties that `docs/diseno.md` explicitly leaves at default (section "Valores por defecto permitidos"). For color, type, spacing and radius, a token missing from the brief triggers `needs_brief` per `frontend-design` and `vinkured-ui`; never fall back silently.
- MUST reuse existing Blade components before creating new ones; new components live in `resources/views/components`.
- Interactivity: Livewire for server state, Alpine for local UI state. No new JS dependency without an ADR.

### Components
- MUST use semantic HTML first (`button`, `a`, `label`, `table`, `dialog`).
- MUST add `aria-label` to icon-only buttons; decorative icons get `aria-hidden="true"`.
- NEVER mix interaction patterns within one surface (for example two different modal implementations).
- NEVER rebuild keyboard or focus behavior by hand unless needed; when a modal or menu is required, it must trap and restore focus, close on Escape and be labelled.

### Interaction
- MUST use an accessible confirmation modal (Livewire/Alpine) for destructive or irreversible actions, naming the consequence in the primary button.
- SHOULD use structural skeletons for loading states (`wire:loading` states).
- NEVER use `h-screen`; use `h-dvh`.
- MUST respect `safe-area-inset` for fixed elements.
- MUST show errors next to the field or action that caused them.
- NEVER block paste in `input` or `textarea`.

### Animation
- (default) NEVER add animation unless requested or the brief calls for it.
- MUST animate only compositor properties (`transform`, `opacity`); NEVER layout properties (`width`, `height`, `top`, `left`, `margin`, `padding`).
- SHOULD avoid animating paint properties (`background`, `color`) except small local UI.
- SHOULD use `ease-out` on entrance; NEVER exceed 200 ms for interaction feedback; no custom easing curves unless requested.
- MUST pause looping animations when off-screen.
- MUST respect `prefers-reduced-motion` (`motion-safe:` / `motion-reduce:` variants).
- SHOULD avoid animating large images or full-screen surfaces.

### Typography
- MUST use `text-balance` for headings and `text-pretty` for paragraphs.
- MUST use `tabular-nums` for data and numeric table columns.
- SHOULD use `truncate` or `line-clamp` in dense UI, always with the full text reachable (title, tooltip or detail view).
- NEVER change `tracking-*` unless requested.

### Layout
- MUST use a fixed z-index scale defined once in the brief or Tailwind config; no arbitrary `z-[...]`.
- SHOULD use `size-*` for square elements instead of `w-*` plus `h-*`.

### Performance
- NEVER animate large `blur()` or `backdrop-filter` surfaces.
- NEVER apply `will-change` outside an active animation.
- Prefer server-rendered markup and Livewire state over client-side re-derivation of data.

### Design
- (default) NEVER use gradients, purple or multicolor gradients, or glow effects as primary affordances, unless `docs/diseno.md` says otherwise.
- SHOULD use Tailwind's default shadow scale when the brief allows defaults for shadows.
- MUST give empty states one clear next action.
- SHOULD limit accent color to one per view.
- SHOULD use theme color tokens from the brief before introducing new ones; if the brief lacks a needed color, return `needs_brief`.

## Decision Gates

| Condition | Action |
|---|---|
| Brief overrides a (default) rule | Follow the brief. |
| Brief silent on a design decision this skill does not settle | Return `needs_brief`; do not improvise. |
| Rule conflicts with accessibility | Accessibility wins. |

## Execution Steps

1. Read the brief and the existing components relevant to the change.
2. Build or edit the UI applying the rules above.
3. Before returning, walk the Hard Rules once as a checklist and fix violations.

## Output Contract

Return files changed, any rule deliberately overridden (with the brief line that authorizes it), and any `needs_brief` items.
