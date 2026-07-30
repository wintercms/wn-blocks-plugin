# Changelog

## Unreleased

### Editor UX
- **Copy / Cut / Paste / Duplicate blocks** — each block has one horizontal
  toolbar (collapse, copy, cut, paste, duplicate, config, delete). Copy/cut/
  duplicate place the block's full field data on the clipboard (`sessionStorage`);
  paste inserts after a block, or appends via a "Paste block" entry at the top
  of the *+ Add New Item* palette (for empty widgets). Duplicate also clones in
  place. Paste affordances appear only where the copied block type is offered
  (respects `allow`/`ignore`/`tags`) and survive navigation within the same
  browser tab. Direct add/paste/duplicate requests run the same empty-add-item
  cleanup as the core popover flow, so "Add new item" rows no longer pile up.
- **Server-side copy/paste** — copying a block calls `onCopyItem`, which builds
  the block's Form widget server-side and calls `getSaveData()`. This correctly
  captures every field type — switches, mediafinders, and nested repeaters with
  their own rows — which a client-side DOM scrape cannot. The clipboard payload
  (`{group, config, data}`) is sent back to `onAddItem` as `_paste_data`, which
  seeds the new item via `getValueFromIndex()` before rendering, so the pasted
  block appears fully populated without a round-trip DOM fill step.
- Richeditor/codeeditor widgets are automatically refreshed after a paste fill,
  since the server-populated fields bypass their normal client-side init.
