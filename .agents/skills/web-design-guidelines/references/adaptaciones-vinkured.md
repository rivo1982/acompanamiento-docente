# Adaptaciones Vinkured (overlay)

This file wins over `web-interface-guidelines.md` on any conflict. Technical text is English; UI copy examples are Spanish (Chile).

## Minimum bar

WCAG 2.2 AA for every screen: text contrast 4.5:1 (3:1 large text and UI components), visible focus, target size at least 24x24 CSS px (prefer 44 px on touch), keyboard-operable everything, no information by color alone, labels and error text associated with fields, no focus trap except modals, reduced motion respected.

## Blade / Livewire equivalents of the React examples

| Vendored rule says | In Vinkured |
|---|---|
| `htmlFor` | `for=` on `<label>` |
| `className` | `class` |
| `onClick`, `onKeyDown`, `onPaste` | `wire:click`, `x-on:keydown`, `x-on:paste` / `@click` (Alpine) |
| `<Link>` | plain `<a href>` (or `wire:navigate` on `<a>`) |
| `<div onClick>` | `<button type="button">` with `wire:click` |
| controlled input / `value` + `onChange` | `wire:model` (use `.live` or `.blur` deliberately; avoid per-keystroke requests on large forms) |
| `autoFocus` | `autofocus` only with clear justification; Alpine `x-init` for modals |
| `aria-live` on async updates | on Livewire-updated regions (toasts, validation summary) |
| `useEffect`-style/virtualization advice | paginate in Livewire (`WithPagination`); no unbounded lists |
| `Intl.*` formats | PHP `Carbon` / `NumberFormatter` with locale `es_CL`, or `Intl` in Alpine |
| hydration mismatch rules | `wire:ignore`, `wire:key` on loops; do not render time-dependent values differently server vs client |
| loading states | `wire:loading`, `wire:loading.attr="disabled"`, `wire:target` |
| spinner/button "Saving…" | label stays specific: "Guardando…" with `wire:loading` |
| URL reflects state | `#[Url]` on Livewire properties for filters, tabs and pagination |
| destructive confirmation | accessible confirmation modal (Livewire/Alpine); no `wire:confirm` for irreversible actions on minors' data |

## Spanish (Chile) copy rules (replace English typographic preferences)

- Sentence case; plain, direct verbs; no ALL CAPS for labels. Keep the same name for an action across the flow.
- Use the ellipsis character `…` for loading/truncation and menu items that open a dialog ("Guardando…"). Use proper quotes « » or " " consistently as set by the brief; apostrophe rules for English do not apply.
- Non-breaking space between number and unit or in "N.º 12" style tokens (`&nbsp;`).
- Numbers: thousands separator `.` and decimal `,` (es-CL), for example `1.234,5`; dates `dd-mm-aaaa` or `dd/mm/aaaa` per the brief, never ambiguous `mm/dd`; RUT shown with dots and hyphen only where the domain requires it, tabular numerals.
- Headings and buttons: do not use title case. Errors: say what failed and how to fix it, without apologizing.
- Domain terms from `CONTEXT.md` (Coordinador de Convivencia, Aviso, Alerta preventiva, Derivación, Caso). Never "Denunciado".
- Set `lang="es-CL"` on `<html>`; mark foreign terms with `lang`.

## Additional audit checks for this project

- Real personal data of minors in views, fixtures, seeders or screenshots: report as blocker (cite `file:line` only, do not quote the data).
- New UI dependency (component library, icon set, font service, CDN script) without an ADR: report as blocker.
- Visual decisions not traceable to `docs/diseno.md`: report as `needs_brief`.
- Mobile-first: report layouts that break at 375 px, hover-only affordances and gesture-only actions.
