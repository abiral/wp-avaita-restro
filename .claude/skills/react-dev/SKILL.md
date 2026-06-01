---
name: react-dev
description: React API reference for WordPress block-editor development — hooks (useState, useEffect, useRef, useMemo, useCallback, useReducer, useContext, useId, useLayoutEffect, useImperativeHandle, useTransition, useDeferredValue), built-in components (Fragment, Suspense, StrictMode), and core patterns (refs, context, lifting state, controlled vs uncontrolled, list keys, effect cleanup). TRIGGER when the user is asking about a React hook, a JSX/component pattern, effect dependencies, refs, context, render behavior (re-renders, batching, key warnings), or working in any .jsx / .tsx file inside src/. SKIP for project-specific block-editor APIs (use wp-block-editor) or @wordpress/data state (use wp-data-store) — this skill covers React itself, not Gutenberg's wrappers around it.
---

# React API reference (for WordPress block development)

This skill is the React API reference for WordPress block-editor projects. For everything Gutenberg-specific (block lifecycle, where to import from, editor-aware patterns), defer to `wp-block-editor`.

> **Critical convention for WordPress projects**: WordPress codebases use React **via `@wordpress/element`**, not via the `react` package directly. Always import hooks, `Fragment`, `createElement`, etc. from `@wordpress/element`. The package is a thin re-export of React, so everything you read at react.dev applies — but the import path must be `@wordpress/element` to stay consistent with the editor and avoid pulling in a duplicate React. (If you're in a non-WP React project, use `react` instead — verify by checking `package.json` dependencies.)

## How to use this skill

1. The cheat-sheets below cover the common cases — they list each hook's signature, the typical bug it causes, and the recommended pattern.
2. For exact rules of an API (effect ordering, batching semantics, Suspense interaction, the new `use` hook in newer React versions), `WebFetch` the matching page from the index. React's docs are detailed and the subtle parts (effect deps, refs vs state, render-during-render) are worth reading rather than guessing.
3. Modern WordPress block code uses function components exclusively — don't introduce class components.

## Imports — the WordPress pattern

```js
// ✅ Correct in WordPress projects
import { useState, useEffect, useRef, useMemo, useCallback, Fragment } from "@wordpress/element";

// ❌ Wrong in WordPress projects
import { useState } from "react";
import React from "react";
```

If you see a `from "react"` import in a WordPress project's PR, flag it — it should be `@wordpress/element`. (Check `package.json` to confirm `@wordpress/element` is a dependency before flagging — non-WP React projects legitimately import from `react`.)

## Hooks cheat-sheet

### useState

```js
const [count, setCount] = useState(0);
const [user, setUser] = useState(() => loadUser()); // lazy initializer
setCount(c => c + 1); // updater form — use when next value depends on previous
```

Key things:
- **Lazy initializer** (`useState(() => …)`) only runs once on mount. Use it when initial computation is expensive.
- **Updater form** (`setX(prev => …)`) avoids stale-closure bugs inside event handlers and effects.
- React batches state updates inside event handlers, effects, and async (React 18+). Multiple `setX` calls in a row produce one re-render.
- Don't mutate state — replace it. `arr.push(x); setArr(arr)` is a bug; use `setArr([...arr, x])`.

### useEffect

```js
useEffect(() => {
  const id = setInterval(tick, 1000);
  return () => clearInterval(id); // cleanup runs on unmount + before next effect
}, [tick]);
```

Key things:
- **Dependency array is a contract**: list every external value the effect closes over. Missing deps → stale closures. Extra deps → effect runs too often.
- **Cleanup runs before each new effect**, not just on unmount. If your effect subscribes, unsubscribe in cleanup.
- **Effects run after paint**. If you must mutate the DOM before paint (e.g. measure layout), use `useLayoutEffect`.
- Effects run **twice in dev** under React StrictMode — the second run helps surface missing cleanups. Don't suppress this; fix the cleanup.
- Don't put data-fetching directly in an effect for editor-context code — use `@wordpress/data`'s `useSelect` instead (see `wp-data-store`).

### useRef

```js
const inputRef = useRef(null);                 // DOM ref
const renderCount = useRef(0);                 // mutable value, no re-render on change

<input ref={inputRef} />
useEffect(() => { inputRef.current?.focus(); }, []);
```

Key things:
- Mutating `ref.current` does NOT trigger a re-render. Use it for: DOM nodes, timers/interval IDs, "previous value" tracking, anything where re-render isn't wanted.
- Don't read/write `ref.current` during render — only in effects, event handlers, or after refs are attached.

### useMemo

```js
const filtered = useMemo(
  () => items.filter(matches),
  [items, matches]
);
```

Memoize **expensive** computations or **referentially-stable** values passed to memoized children. Don't memoize cheap operations — `useMemo` itself has overhead. The dependency-array rules are the same as `useEffect`.

### useCallback

```js
const handleClick = useCallback(() => doThing(id), [id]);
```

Same as `useMemo` but for functions. Use it when:
- The function is passed as a dep to `useEffect` / `useMemo`,
- Or to a memoized child component (`React.memo`).

If neither applies, plain inline functions are fine — `useCallback` is an optimization, not a default.

### useReducer

```js
const [state, dispatch] = useReducer(reducer, initialState);
dispatch({ type: 'INCREMENT', amount: 1 });

function reducer(state, action) {
  switch (action.type) {
    case 'INCREMENT': return { ...state, count: state.count + action.amount };
    default: return state;
  }
}
```

Use over `useState` when:
- State has multiple sub-values that update together,
- Update logic is non-trivial,
- Or you want to centralize state transitions for testability.

### useContext

```js
const ThemeContext = createContext('light');

function App() {
  return <ThemeContext.Provider value="dark"><Child /></ThemeContext.Provider>;
}

function Child() {
  const theme = useContext(ThemeContext);
}
```

Key things:
- Every consumer **re-renders** when the provider's `value` changes. Don't put a fresh-on-every-render object as `value` unless you want every consumer to re-render — wrap in `useMemo`.
- For Gutenberg-style state shared across blocks, prefer `block.json`'s `providesContext`/`usesContext` (see `wp-block-editor`) over React Context — it persists in the saved markup.

### useId

```js
const id = useId();
<input id={id} aria-describedby={`${id}-help`} />
```

Generates a stable, unique-per-render ID safe for SSR. Use for `<label htmlFor>` ↔ `<input id>` pairs and ARIA wiring. Don't use for keys (use list data IDs instead).

### useLayoutEffect

Same shape as `useEffect`, but runs synchronously **before paint**. Use only when you need to measure DOM and mutate before the user sees the in-between state. Has a perceptible cost — prefer `useEffect` unless you need the timing guarantee.

### useImperativeHandle

```js
const Input = forwardRef((props, ref) => {
  const inputRef = useRef(null);
  useImperativeHandle(ref, () => ({
    focus: () => inputRef.current.focus(),
  }));
  return <input ref={inputRef} {...props} />;
});
```

Customize what a parent ref points at. Rarely needed — most cases are better served by passing a ref through directly with `forwardRef`.

### useTransition / useDeferredValue (React 18+)

`useTransition` marks a state update as non-urgent so the UI can interrupt it for higher-priority updates (typing, clicks). `useDeferredValue` defers a value derived from a prop. Useful for expensive list filters that block typing — overkill for most editor blocks.

## Built-in components

- **`<Fragment>` / `<>...</>`** — group children without adding a DOM node.
- **`<StrictMode>`** — opt-in dev checks: double-renders effects and state updaters to surface unsafe patterns. The block editor wraps blocks in StrictMode in dev — your effects must be idempotent.
- **`<Suspense fallback={...}>`** — show a fallback while a child is loading. Mostly relevant if you use `lazy()` or a Suspense-aware data layer.
- **`React.memo(Component)`** — skip re-render when props are shallow-equal. Only useful when the parent re-renders frequently and the child is expensive.
- **`forwardRef(render)`** — accept a `ref` from a parent and forward it to a DOM node or imperative handle.

## Patterns that come up in WordPress block code

### Setting one piece of state from another piece of state

Don't. Derive it during render:

```js
// ❌
const [items, setItems] = useState([]);
const [count, setCount] = useState(0);
useEffect(() => setCount(items.length), [items]);

// ✅
const count = items.length;
```

If derivation is expensive, wrap in `useMemo`. Effects-that-only-set-state are almost always a code smell.

### List keys

Always provide a stable, unique `key` when rendering a list. Use the data's ID, not the array index — index keys cause subtle bugs when items reorder or insert at the front.

```js
// ✅ stable
items.map(item => <Row key={item.id} item={item} />)
// ❌ index — breaks on reorder
items.map((item, i) => <Row key={i} item={item} />)
```

### Controlled vs uncontrolled inputs

- **Controlled**: `value` + `onChange` — React owns the input value. The default for inspector controls.
- **Uncontrolled**: `defaultValue` + `ref` — DOM owns the value, you read it via ref. Use only when you genuinely don't need to react to every keystroke.

### Lifting state up

When two siblings need the same state, put it in their nearest common ancestor and pass via props. For Gutenberg parent/child blocks, prefer block context (`providesContext`/`usesContext`) over React state — block context survives saving.

### Conditional rendering

```js
{isOpen && <Panel />}                 // shows when truthy
{count > 0 ? <List /> : <Empty />}   // either/or
{items.length ? <List /> : null}     // explicit null is fine
```

Watch out: `0 && <X />` renders `0` (a falsy number that React still renders). Use `Boolean(items.length)` or `items.length > 0` to be safe.

## Common bugs and how to catch them

| Symptom | Cause | Fix |
|---|---|---|
| State updates "don't take" inside a handler | Stale closure over old state | Use updater form `setX(prev => …)` |
| Effect runs every render | Object/array dep is a fresh ref each render | `useMemo` the dep, or move it inside the effect |
| Effect doesn't run when expected | Missing dep | Add it. Don't suppress the lint warning. |
| Memoized component re-renders anyway | Inline object/function prop is a new ref | `useMemo` / `useCallback` the prop |
| List items lose state on reorder | Using index as `key` | Use a stable ID |
| Infinite re-render loop | Effect calls `setState` with deps that change every render | Stabilize the dep, or move logic out of effect |
| Warning: "can't perform state update on unmounted component" | Effect resolved a promise after unmount | Cancel/ignore in cleanup |
| Two renders / double-fired effects in dev | StrictMode | Make effects idempotent + add cleanup |

## Documentation URL index — WebFetch when uncertain

### React reference
- Reference home: https://react.dev/reference/react
- Hooks index: https://react.dev/reference/react/hooks
  - useState: https://react.dev/reference/react/useState
  - useEffect: https://react.dev/reference/react/useEffect
  - useRef: https://react.dev/reference/react/useRef
  - useMemo: https://react.dev/reference/react/useMemo
  - useCallback: https://react.dev/reference/react/useCallback
  - useReducer: https://react.dev/reference/react/useReducer
  - useContext: https://react.dev/reference/react/useContext
  - useId: https://react.dev/reference/react/useId
  - useLayoutEffect: https://react.dev/reference/react/useLayoutEffect
  - useImperativeHandle: https://react.dev/reference/react/useImperativeHandle
  - useTransition: https://react.dev/reference/react/useTransition
  - useDeferredValue: https://react.dev/reference/react/useDeferredValue
  - useSyncExternalStore: https://react.dev/reference/react/useSyncExternalStore
  - useDebugValue: https://react.dev/reference/react/useDebugValue
- Components: https://react.dev/reference/react/components
  - Fragment: https://react.dev/reference/react/Fragment
  - Suspense: https://react.dev/reference/react/Suspense
  - StrictMode: https://react.dev/reference/react/StrictMode
  - Profiler: https://react.dev/reference/react/Profiler
- APIs: https://react.dev/reference/react/apis
  - createContext: https://react.dev/reference/react/createContext
  - forwardRef: https://react.dev/reference/react/forwardRef
  - memo: https://react.dev/reference/react/memo
  - lazy: https://react.dev/reference/react/lazy
  - startTransition: https://react.dev/reference/react/startTransition

### Learn React (concepts)
- Learn home: https://react.dev/learn
- Thinking in React: https://react.dev/learn/thinking-in-react
- Synchronizing with Effects: https://react.dev/learn/synchronizing-with-effects
- You Might Not Need an Effect: https://react.dev/learn/you-might-not-need-an-effect
- Manipulating the DOM with Refs: https://react.dev/learn/manipulating-the-dom-with-refs
- Passing Data Deeply with Context: https://react.dev/learn/passing-data-deeply-with-context
- Rules of React: https://react.dev/reference/rules

### Bridging to WordPress
- @wordpress/element (WP's re-export of React): https://developer.wordpress.org/block-editor/reference-guides/packages/packages-element/

## Anti-patterns to avoid in WordPress block code

- Don't `import React from "react"` or `import { … } from "react"` — use `@wordpress/element`.
- Don't introduce class components — function components are the modern default.
- Don't use array index as a list `key` when items can be added/reordered/removed.
- Don't suppress the `react-hooks/exhaustive-deps` lint rule. Fix the deps.
- Don't read/write `ref.current` during render — only in effects/handlers.
- Don't use an effect to sync derived state — derive it in render (or `useMemo`).
- Don't put a fresh-each-render object/function into context `value` or memo deps without stabilizing it.
- Don't mutate state. Always create a new object/array.
- Don't fetch data with `useEffect` in editor-context code — use `useSelect` from `@wordpress/data`.
