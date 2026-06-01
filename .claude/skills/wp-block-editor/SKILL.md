---
name: wp-block-editor
description: WordPress block editor (Gutenberg) reference — block.json, edit/save, attributes, supports, and the @wordpress/* APIs (block-editor, blocks, components, element, i18n, hooks). TRIGGER when the user is working on files under src/blocks/, editing a block.json, importing from @wordpress/blocks, @wordpress/block-editor, @wordpress/components, @wordpress/element, @wordpress/i18n, or @wordpress/hooks; or asking how to add/modify a block, attribute, inspector control, RichText, InnerBlocks, or block supports. SKIP for non-block WordPress work (REST API, classic editor, theme.json).
---

# WordPress block editor reference

This skill is the working reference for Gutenberg block development. The patterns here are WordPress-standard — for project-specific conventions (block namespace, helpers, custom controls), discover them from the codebase first.

## Detect the project's conventions first

Before adding or modifying a block, identify what this project already does:

1. **Block namespace**: open an existing `block.json` (`Glob "**/block.json"` excluding `node_modules` and `build/`) and read the `name` field — everything before the `/` is the project's block namespace (e.g. `acme-blocks/hero` → namespace is `acme-blocks`).
2. **Block category**: read `category` in the same `block.json`. If the project registers a custom category, `Grep "block_categories_all|register_block_pattern_categories"` to find where.
3. **Textdomain**: the `Text Domain:` header in the main plugin/theme PHP file. Use it in every `__()` call and match it in `block.json`'s `textdomain` field.
4. **API version**: read `apiVersion` in an existing `block.json`. Modern projects use `3` (iframed canvas). Match what's already there.
5. **Build output location**: WordPress `register_block_type` reads from a built `block.json`, not the source. Check whether the project builds to `build/blocks/` or somewhere else (look at `package.json` scripts and `Glob "build/**/block.json"`).
6. **Registration wrapper**: many projects wrap `registerBlockType` in a helper (e.g. to default the icon or category). `Grep "registerBlockType"` and check if the codebase calls it directly or through a wrapper. Use whichever pattern already exists.
7. **Class-name helper / prefix**: `Grep "getClass|className.*useBlockProps"` to see if there's a project helper for generating prefixed class names.
8. **Custom controls**: `Glob "**/custom-control*"` or look in `src/components/`, `src/controls/`, etc., for project-built control wrappers (responsive ranges, dimension pickers, typography controls). Use these instead of rolling your own.

Substitute the discovered values for `<block-namespace>`, `<plugin-textdomain>`, etc. throughout the templates below.

## How to use this skill

1. **For project-specific patterns** (registration wrapper, prefix/class helpers, attribute shapes, custom controls, file layout): use what you discovered above — match the codebase, don't introduce parallel patterns.
2. **For upstream API details** (prop signatures, attribute `source` semantics, `supports` keys, component-by-component prop reference, recent additions): `WebFetch` the URL from the index at the bottom **before** writing code. Don't guess from memory — Gutenberg APIs change between WP releases and prop names like `__next40pxDefaultSize` / `__nextHasNoMarginBottom` are easy to miss.
3. When upstream docs disagree with observed behavior, the package source under `node_modules/@wordpress/<pkg>/` is authoritative.

## Standard file layout per block

```
src/blocks/<name>/
  block.json                 # metadata (apiVersion, namespace, attributes, etc.)
  index.js                   # imports edit/save, calls registerBlockType
  edit.js                    # the edit React component (or components/edit.js)
  save.js                    # the save React component (or components/save.js)
  inspector.js               # InspectorControls / InspectorAdvancedControls (optional split)
  editor.scss                # editor-only styles (loaded via editorStyle in block.json)
  style.scss                 # shared front+back styles (loaded via style)
```

Some projects nest into `components/` subfolders. Match the existing layout.

For parent/child blocks, the child lives in a subfolder with its own `block.json`, `edit.js`, `save.js`. The child's `block.json` declares `"parent": ["<block-namespace>/<parent>"]`.

## block.json — the core fields

```json
{
  "$schema": "https://schemas.wp.org/trunk/block.json",
  "apiVersion": 3,
  "name": "<block-namespace>/<name>",
  "version": "1.0",
  "title": "<Human Title>",
  "description": "<one sentence>",
  "category": "<category-slug>",
  "attributes": { "block_id": { "type": "string" } },
  "textdomain": "<plugin-textdomain>",
  "editorScript": "file:./index.js",
  "editorStyle":  "file:./index.css",
  "style":        "file:./style-index.css"
}
```

Things to look up upstream when extending block.json:
- **`attributes[name].source`** — `"attribute" | "html" | "text" | "query" | "meta"` and the related `selector` / `attribute` fields. Subtle and version-sensitive — fetch the attributes doc.
- **`supports`** — anchor, align, color, spacing, typography, layout, etc. Each enables editor UI and adds saved class names. Fetch the supports doc.
- **`parent` / `ancestor`** — used by child blocks; check an existing parent/child pair in the codebase before adding new restrictions.
- **`providesContext` / `usesContext`** — share state from a parent block to descendants.
- **`variations`** — alternate presets of the same block.
- **`render`** — set this for dynamic (server-rendered) blocks; `save` then returns `null` or `<InnerBlocks.Content />`.

## Registering a block

The minimal pattern:

```js
// src/blocks/<name>/index.js
import "./styles/style.scss";
import "./styles/editor.scss";
import { registerBlockType } from "@wordpress/blocks";
import edit from "./edit";
import save from "./save";
import metadata from "./block.json";

registerBlockType(metadata.name, { edit, save });
```

Many projects wrap this in a helper that defaults the icon, ensures the block is added to a project-wide `insertableBlocks` list, etc. **If the project has such a wrapper, use it** — discover it with `Grep "registerBlockType" --glob "src/**/*.js"` and copy the import path from a sibling block's `index.js`.

For parent/child blocks: the parent's `index.js` should import the child folder so the child registers alongside (`import "./<child>/"`).

## Edit component — the standard shape

```js
import { useEffect } from "@wordpress/element";
import { __ } from "@wordpress/i18n";
import { useBlockProps, useInnerBlocksProps } from "@wordpress/block-editor";
import InspectorPanel from "./inspector";

const Edit = (props) => {
  const { attributes: { block_id /*, ... */ }, setAttributes, clientId } = props;
  const blockProps = useBlockProps({ className: "<prefix>-<name>" });

  // If the block emits dynamic CSS keyed by id, derive a stable id from blockProps.id:
  useEffect(() => {
    const expected = `<prefix>-<name>-${blockProps.id}`;
    if (block_id !== expected) setAttributes({ block_id: expected });
  }, [blockProps.id]);

  // Parent blocks with InnerBlocks:
  const innerBlocksProps = useInnerBlocksProps(
    { className: "<prefix>-<name>__grid" },
    { allowedBlocks: ["<block-namespace>/<child>"], template: [["<block-namespace>/<child>", {}]], renderAppender: false }
  );

  return (
    <>
      <InspectorPanel {...props} />
      <div {...blockProps} id={block_id}>
        {/* content; for parents: <div {...innerBlocksProps} /> */}
      </div>
    </>
  );
};
export default Edit;
```

## Save component — the standard shape

```js
import { useBlockProps, InnerBlocks, RichText } from "@wordpress/block-editor";

const save = ({ attributes: { block_id /*, ... */ } }) => {
  const blockProps = useBlockProps.save();
  return (
    <div {...blockProps} id={block_id}>
      {/* RichText.Content tagName="..." value={...} for editable text */}
      {/* <InnerBlocks.Content /> for parent blocks */}
    </div>
  );
};
export default save;
```

## Inspector component — the standard shape

```js
import { InspectorControls, InspectorAdvancedControls } from "@wordpress/block-editor";
import { PanelBody, RangeControl, ToggleControl } from "@wordpress/components";
import { __ } from "@wordpress/i18n";

const InspectorPanel = ({ attributes, setAttributes }) => (
  <>
    <InspectorControls group="settings">
      <PanelBody title={__("Layout", "<plugin-textdomain>")} initialOpen={true}>
        <RangeControl
          label={__("Gap (px)", "<plugin-textdomain>")}
          value={attributes.gap}
          onChange={(gap) => setAttributes({ gap })}
          min={0} max={80}
          __nextHasNoMarginBottom
          __next40pxDefaultSize
        />
      </PanelBody>
    </InspectorControls>
  </>
);
```

- Always include `__nextHasNoMarginBottom` and `__next40pxDefaultSize` on `@wordpress/components` controls — without them the editor logs deprecation warnings.
- Group panels using `<InspectorControls group="settings">` (or `"styles"`, `"list-view"`).

## @wordpress/block-editor — commonly used APIs

- `useBlockProps()` / `useBlockProps.save()` — required on the root in API v3.
- `useInnerBlocksProps(blockProps, options)` — preferred form for parent blocks. Options: `allowedBlocks`, `template`, `templateLock`, `orientation`, `renderAppender`.
- `<InnerBlocks.Content />` — used in `save.js` of parent blocks.
- `<RichText tagName="..." value={...} onChange={...} placeholder="..." />` — editable text. In `save`: `<RichText.Content tagName="..." value={...} />`.
- `<InspectorControls>` / `<InspectorAdvancedControls>` / `<BlockControls>`.
- `<MediaUpload>` / `<MediaUploadCheck>` — used by image/media controls.

## @wordpress/components — commonly used controls

`PanelBody`, `ToggleControl`, `RangeControl`, `TextControl`, `TextareaControl`, `SelectControl`, `Button`, `Spinner`, `SVG`, `Path`, `G`. Add `__nextHasNoMarginBottom` and (for size) `__next40pxDefaultSize` on every form control. For anything new, fetch its component reference page before guessing prop names.

## @wordpress/element

Always import React hooks and primitives from here, **not** from `react`: `useState`, `useEffect`, `useRef`, `useMemo`, `useCallback`, `Fragment`, `createElement`. Keeps bundle and behavior consistent with the editor.

## @wordpress/i18n

`__("string", "<plugin-textdomain>")`. The textdomain must match the one declared in `block.json` and the plugin/theme header — many projects enforce this in CI.

## @wordpress/hooks

For applyFilters / addFilter / addAction — use it for filter-based extensibility points. If the project creates a local hooks instance (`createHooks()`), use that one rather than the global so filter scopes don't leak between unrelated plugins.

## When adding a brand-new block

Copy the closest existing block as a template and rename. Then update:
- `block.json` (name, title, attributes)
- `index.js` (icon if inline)
- `edit.js` (className, attribute reads/writes)
- `save.js` (markup)
- `inspector.js` (controls)

Match the project's existing icon style (inline SVG vs Dashicon string), attribute defaults, and SCSS file structure.

## Documentation URL index — WebFetch when uncertain

### Block development core
- Reference home: https://developer.wordpress.org/block-editor/reference-guides/
- block.json schema: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/
- Attributes (incl. `source`): https://developer.wordpress.org/block-editor/reference-guides/block-api/block-attributes/
- Supports: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-supports/
- Edit & save: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/
- registerBlockType: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/
- Context (provides/uses): https://developer.wordpress.org/block-editor/reference-guides/block-api/block-context/
- Variations: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-variations/
- Transforms: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-transforms/
- Deprecation (when changing attributes): https://developer.wordpress.org/block-editor/reference-guides/block-api/block-deprecation/
- Patterns: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-patterns/

### Packages
- @wordpress/block-editor: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/
- @wordpress/blocks: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-blocks/
- @wordpress/components (catalog): https://developer.wordpress.org/block-editor/reference-guides/components/
- @wordpress/element: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-element/
- @wordpress/i18n: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-i18n/
- @wordpress/hooks: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-hooks/

### Specific component pages
Append the kebab-cased component name to the components base, e.g. `panel-body/`, `toggle-control/`, `range-control/`, `text-control/`, `select-control/`, `button/`, `spinner/`.

### Editor extension points
- Filters: https://developer.wordpress.org/block-editor/reference-guides/filters/
- Slot/Fill: https://developer.wordpress.org/block-editor/reference-guides/slotfills/

### Tutorials
- Getting started: https://developer.wordpress.org/block-editor/getting-started/
- How-to guides: https://developer.wordpress.org/block-editor/how-to-guides/

## Anti-patterns to avoid

- Don't bypass the project's registration wrapper (if there is one) — keeps insertableBlocks lists and default icon/category consistent.
- Don't hardcode prefixed class strings if the project has a `getClass`-style helper — use the helper.
- Don't import from `react` — use `@wordpress/element`.
- Don't reinvent dimension/typography/range controls — use the project's existing `custom-control` (or equivalent) components if they exist.
- Don't change an attribute's shape without adding a `deprecated` entry — existing posts will show "this block contains unexpected or invalid content."
- Don't omit `useBlockProps()` / `useBlockProps.save()` — the editor needs the className/ref it returns.
- Don't omit `__nextHasNoMarginBottom` / `__next40pxDefaultSize` on `@wordpress/components` controls.
- Don't use a different textdomain than the project's declared one — many projects enforce this in CI.
- Don't pass `src/blocks/<name>` to PHP `register_block_type` — the *built* `block.json` is what gets registered (typically `build/blocks/<name>/`).
