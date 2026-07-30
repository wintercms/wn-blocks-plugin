# Changelog

## Unreleased

### Component-backed block fields
- `.block` definitions may declare a top-level `component: <componentKey>` to
  derive default `fields:` from a CMS component's `defineProperties()`.
  Property types are mapped to block/form field types (string/text → text,
  integer/float → number, checkbox → checkbox, dropdown → dropdown, set →
  checkboxlist). The block's own `fields:` always take precedence over the
  ones derived from the component on key collision.
- Unknown or unresolvable components are logged as a warning and otherwise
  ignored, leaving the block's own fields untouched.
