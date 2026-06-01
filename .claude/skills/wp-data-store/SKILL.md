---
name: wp-data-store
description: WordPress @wordpress/data store API reference — useSelect, useDispatch, select, dispatch, and the core stores (core, core/editor, core/block-editor, core/edit-post, core/edit-site, core/notices). TRIGGER when the user imports from @wordpress/data, calls useSelect/useDispatch/select/dispatch, references a store key like 'core/block-editor' or 'core/editor', or asks how to read/write editor state. SKIP for plain React state (useState/useReducer) or REST API calls that don't go through @wordpress/data.
---

# WordPress @wordpress/data reference

`@wordpress/data` is Gutenberg's Redux-style state layer. This file documents the standard patterns plus an index of upstream docs to fetch when the cheat-sheet isn't enough.

## Detect the project's conventions first

Before writing data-store code, see what the project already does:

1. **Existing `useSelect` / `useDispatch` calls**: `Grep "useSelect|useDispatch" --type=js` to find canonical examples. Copy the import path, dep-array conventions, and selector patterns from a sibling file.
2. **Existing helpers**: many WP projects wrap common store reads in helpers (e.g. `getPreviewDevice()`, `getParentAttrs(props)`). `Glob "**/helpers/**/*.js"` and `Grep "select\(\""` to find them — prefer these over reaching into stores directly so the project stays consistent.
3. **Custom store**: `Grep "createReduxStore|createReduxStore"` to see if the project registers its own store. If so, use the project's store key and its action/selector names.

## How to use this skill

1. Decide whether you're **reading** state (`useSelect` / `select`) or **writing** it (`useDispatch` / `dispatch`).
2. Identify which **store** — the project's own store, or a core one (`core`, `core/editor`, `core/block-editor`, `core/edit-post`, `core/edit-site`, `core/notices`).
3. For exact selector/action signatures and recent additions, **WebFetch** the per-store reference page from the index below before writing code. Selector arg order and return shapes are easy to misremember.

## Reading state

```js
import { useSelect } from "@wordpress/data";

const featuredImage = useSelect(
  (select) => select("core/editor").getEditedPostAttribute("featured_media"),
  []
);
```

- `useSelect(mapSelect, deps)` re-runs when the deps change OR when the selectors it touches emit. Always pass a dep array; missing deps = stale closures.
- `select(storeName).someSelector(...)` is the imperative form — fine outside React render (effects, event handlers, helpers).
- For multi-value reads, return an object; downstream comparison can use `@wordpress/is-shallow-equal` to avoid spurious re-renders.

## Writing state

```js
import { useDispatch } from "@wordpress/data";

const { editPost } = useDispatch("core/editor");
editPost({ meta: { my_field: "value" } });
```

- `useDispatch(storeName)` returns the actions object for that store.
- `dispatch(storeName).someAction(...)` is the imperative form — fine outside render.
- Many core dispatchers return promises — `await` when chaining.

## Core stores commonly used in block editor projects

- **`core`** — entities (posts, users, media, terms, taxonomies). Common: `getCurrentUser()`, `getMedia(id)`, `getEntityRecord("postType", "post", id)`, `getEntityRecords(...)`.
- **`core/editor`** — current post + (WP 6.5+) `getDeviceType` / `setDeviceType`. Replaces the experimental APIs on `core/edit-post` / `core/edit-site` for preview-device detection.
- **`core/block-editor`** — block tree:
  - `getBlocks(clientId)` — children of a block.
  - `getBlock(clientId)` — a specific block.
  - `getBlockRootClientId(clientId)` — find a block's parent.
  - `getBlocksByClientId(clientId)` — look up blocks by id.
  - Dispatchers: `insertBlocks`, `updateBlockAttributes`, `removeBlock`.
- **`core/edit-post`** — classic post editor. `__experimentalGetPreviewDeviceType` / `__experimentalSetPreviewDeviceType` are the pre-WP-6.5 fallback for preview device.
- **`core/edit-site`** — site editor (FSE). Same experimental fallback pattern.
- **`core/notices`** — `createNotice`, `removeNotice` for editor-wide notices.

## Common patterns

### Cross-version preview-device detection

WP 6.5+ moved preview device to `core/editor.getDeviceType` / `setDeviceType`. Earlier versions had it on `core/edit-post` / `core/edit-site` as `__experimentalGetPreviewDeviceType`. The standard way to handle both:

```js
import { select } from "@wordpress/data";

const getPreviewDevice = () => {
  // WP 6.5+
  const editor = select("core/editor");
  if (editor && typeof editor.getDeviceType === "function") {
    return editor.getDeviceType();
  }
  // Pre-6.5 fallback
  const editPost = select("core/edit-post");
  if (editPost && typeof editPost.__experimentalGetPreviewDeviceType === "function") {
    return editPost.__experimentalGetPreviewDeviceType();
  }
  const editSite = select("core/edit-site");
  if (editSite && typeof editSite.__experimentalGetPreviewDeviceType === "function") {
    return editSite.__experimentalGetPreviewDeviceType();
  }
  return "Desktop";
};
```

If the project already has a helper like this, use it — don't duplicate the fallback chain.

### Reading parent block attributes from a child

```js
import { select } from "@wordpress/data";

const getParentAttrs = ({ clientId }) => {
  const parentId = select("core/block-editor").getBlockRootClientId(clientId);
  if (!parentId) return false;
  const parent = select("core/block-editor").getBlock(parentId);
  return parent ? parent.attributes : false;
};
```

Used by child blocks (e.g. team-member, tab, faq-item, icon-box-item) to inherit values from their container.

### Counting / capping inner blocks

```js
import { select } from "@wordpress/data";

const getInnerBlocksCount = (clientId) =>
  select("core/block-editor").getBlocks(clientId).length;
```

Combine with a filter (via `@wordpress/hooks`) if the limit needs to be customizable per-block.

## Registering your own store

Block attributes carry per-block state, and global UI state can go through window globals or React Context. A custom data store is appropriate when:
- Multiple unrelated components need to read the same async-fetched data, or
- You want time-travel debugging / Redux DevTools support, or
- You need cross-block coordination beyond what block context covers.

```js
import { createReduxStore, register } from "@wordpress/data";

const store = createReduxStore("<plugin-slug>/<feature>", {
  reducer(state = { foo: 0 }, action) { /* ... */ },
  actions: { setFoo: (foo) => ({ type: "SET_FOO", foo }) },
  selectors: { getFoo: (state) => state.foo },
  resolvers: { /* async loaders */ },
});
register(store);
```

Use the project's slug as the store-key prefix to avoid collisions with other plugins.

## Documentation URL index — WebFetch when uncertain

### Core API
- @wordpress/data overview: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-data/
- Data module reference: https://developer.wordpress.org/block-editor/reference-guides/data/

### Per-store reference (selectors + actions)
- core: https://developer.wordpress.org/block-editor/reference-guides/data/data-core/
- core/editor: https://developer.wordpress.org/block-editor/reference-guides/data/data-core-editor/
- core/block-editor: https://developer.wordpress.org/block-editor/reference-guides/data/data-core-block-editor/
- core/blocks: https://developer.wordpress.org/block-editor/reference-guides/data/data-core-blocks/
- core/edit-post: https://developer.wordpress.org/block-editor/reference-guides/data/data-core-edit-post/
- core/edit-site: https://developer.wordpress.org/block-editor/reference-guides/data/data-core-edit-site/
- core/notices: https://developer.wordpress.org/block-editor/reference-guides/data/data-core-notices/
- core/preferences: https://developer.wordpress.org/block-editor/reference-guides/data/data-core-preferences/
- core/keyboard-shortcuts: https://developer.wordpress.org/block-editor/reference-guides/data/data-core-keyboard-shortcuts/
- core/viewport: https://developer.wordpress.org/block-editor/reference-guides/data/data-core-viewport/

## Anti-patterns to avoid

- Don't call `__experimentalGetPreviewDeviceType` / `__experimentalSetPreviewDeviceType` directly from a block — wrap them so the WP 6.5 migration stays in one place.
- Don't put expensive work inside `useSelect`'s mapper — it runs on every store change. Compute, then memoize.
- Don't forget the dependency array on `useSelect`.
- Don't call `dispatch(...)` directly inside render — use `useDispatch` and trigger from an effect or handler.
- Don't reach into a core store's underscore-prefixed selectors except in fallback paths; they're not part of the public API and may change without notice.
- Don't reinvent core entity fetching with raw `fetch` — `select("core").getEntityRecord(...)` is cached and resolves automatically.
