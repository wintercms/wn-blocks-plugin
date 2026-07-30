# Changelog

## Unreleased

### Shared field includes
- `.block` definitions may declare a top-level `include:` (string or list) to
  merge `fields` and `config` from external plain-YAML files.
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

### Tests
- `BlockManagerTest`: include merging, block-overrides-include precedence,
  nested includes, circular-include guard, missing-file skip, multiple includes,
  and the no-include no-op.
- Fixtures under `tests/fixtures/blocks/includes/`.
