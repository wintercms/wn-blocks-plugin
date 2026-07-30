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
  (`localStorage`, keyed by field name and scoped per widget instance).

### Tests
- `BlocksTest`: `collapsible`/`collapsed` shorthand translation to
  `data-block-collapsible` / `data-block-collapsible-open`, and that non-section
  / plain-section fields are left untouched.
