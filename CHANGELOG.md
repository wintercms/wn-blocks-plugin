# Changelog

## Unreleased

### Collapsible sections
- `.block` `type: section` fields support a `collapsible: true` shorthand, with
  `collapsed: true|false` controlling the initial state.
- Handled via the `data-block-collapsible` attribute and an inline bootstrap in
  the block widget partial, independent of core's collapsible-section JS. This
  fixes the core behaviour where adding an item to a repeater nested inside a
  section re-collapsed it and double-bound its click handler (causing the
  "Add item" stall).
- **Open/closed state now persists** per section across page reloads
  (`localStorage`, keyed by field name).

### Tabs
- `.block` definitions may declare top-level `tabs` and `secondaryTabs`, passed
  through to the backend `Form` widget like a standard `fields.yaml`.

### Shared field includes
- `.block` definitions may declare a top-level `include:` (string or list) to
  merge `fields`, `tabs`, `secondaryTabs`, and `config` from external plain-YAML
  files.
- Included definitions form the base; the block's own definitions override on
  collision. Paths resolve via `File::symbolizePath()` (`$/`, `~/`, `#/`).
- **Nested includes** are resolved recursively, guarded against circular
  references.
- A **schema guard** logs a warning when an include would redefine a field with
  a different `type`.
- Missing include files are skipped and logged as a warning.

### Editor UX
- **Recently used blocks** are pinned to the top of the "add block" palette
  (tracked in `localStorage`, most-recent first).
- **Cut / Paste / Duplicate blocks** — each block has one horizontal toolbar
  (collapse, cut, paste, duplicate, config, delete). Cut/duplicate place the
  block's field values on the clipboard (`sessionStorage`); paste inserts after
  a block, or appends via a "Paste block" entry at the top of the *+ Add New
  Item* palette (for empty widgets). Duplicate also clones in place. Paste
  affordances appear only where
  the copied block type is offered (respects `allow`/`ignore`/`tags`) and
  survive navigation within the same browser tab.

### Tests
- `BlockManagerTest`: include merging, block-overrides-include precedence,
  nested includes, circular-include guard, missing-file skip, multiple includes,
  and the no-include no-op.
- `BlocksTest`: `collapsible`/`collapsed` shorthand translation to
  `data-block-collapsible` / `data-block-collapsible-open`, and that non-section
  / plain-section fields are left untouched.
- Fixtures under `tests/fixtures/blocks/includes/`.

### Housekeeping
- Documented the two distinct `blocks.js` files (frontend Snowboard build vs.
  backend FormWidget script) in `winter.mix.js` to prevent accidental merging.
- Stopped tracking `.DS_Store` files.
