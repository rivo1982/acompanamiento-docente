---
name: web-design-guidelines
description: "Trigger: audit or review Vinkured UI code (Blade, Livewire, Tailwind) for accessibility, UX and design-guideline compliance. Read-only; outputs terse file:line findings."
license: MIT
metadata:
  author: vinkured (rules vendored from vercel-labs/web-interface-guidelines)
  version: "1.0"
---

<!--
Provenance
- Rules: https://github.com/vercel-labs/web-interface-guidelines, commit 434b7f91364665f2f733b310ec54809bf8f37937, MIT (references/LICENSE-web-interface-guidelines). Vendored verbatim in references/web-interface-guidelines.md.
- Upstream skill wrapper: vercel-labs/agent-skills commit 063bee94c3f4df8453406c830b0a7df0f2860278. License of vercel-labs/agent-skills not verified; only the MIT-licensed rules are vendored verbatim, this SKILL.md is rewritten by the Vinkured project (the upstream fetched rules from the network at run time; this one never does).
-->

## Activation Contract

Load for a read-only audit of UI changes (a diff or a list of files). This skill never edits files and never uses the network.

## Hard Rules

- Network access and external sources are forbidden (no runtime fetch, no external tool). You MAY read locally exactly: `references/web-interface-guidelines.md`, `references/adaptaciones-vinkured.md`, `docs/diseno.md`, and the files or diff under audit. No other file reads.
- The overlay `references/adaptaciones-vinkured.md` wins over the vendored rules on any conflict (framework syntax, Spanish copy, WCAG bar).
- Check `docs/diseno.md` when it exists; a finding that contradicts the brief is reported as such.
- Report only: do not fix, do not rewrite files.
- Never print real personal data of minors found in a file; cite the `file:line` and say "personal data" only.

## Decision Gates

| Condition | Action |
|---|---|
| No files or diff given | Return `needs_input` asking the orchestrator for the file list or base ref. |
| Rule only applies to another framework | Translate it through the overlay; skip if no equivalent. |
| Rule conflicts with overlay | Apply the overlay. |

## Execution Steps

1. Read both reference files and the files or diff to audit.
2. Check every rule category (accessibility, focus, forms, animation, typography, content, images, performance, navigation, touch, locale, hydration, hover, copy, anti-patterns) as translated by the overlay.
3. Emit findings in the output format below.

## Output Contract

Group by file. One finding per line as `path:line - issue` (fix hint only when not obvious). A file with no findings is `✓ pass`. No preamble, no explanation unless the fix is non-obvious.

```text
## resources/views/livewire/casos/lista.blade.php

resources/views/livewire/casos/lista.blade.php:42 - icon button missing aria-label
resources/views/livewire/casos/lista.blade.php:18 - input lacks label

## resources/views/components/boton.blade.php

✓ pass
```

## References

- [references/web-interface-guidelines.md](references/web-interface-guidelines.md): vendored rules (MIT).
- [references/adaptaciones-vinkured.md](references/adaptaciones-vinkured.md): overlay, wins on conflicts.
- [references/LICENSE-web-interface-guidelines](references/LICENSE-web-interface-guidelines): MIT text.
