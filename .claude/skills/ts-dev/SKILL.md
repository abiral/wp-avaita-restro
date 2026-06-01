---
name: ts-dev
description: TypeScript-with-React reference for WordPress development — typing function components, props (incl. children, optional/default), hooks (useState<T>, useRef<T>, useReducer typed actions), event handlers, refs (RefObject vs MutableRefObject), generic components, common utility types (Partial, Pick, Omit, ReturnType, ComponentProps), discriminated unions, and `as const` patterns. Scoped to React development only — not general TS. TRIGGER when the user is editing a .tsx/.ts file in a React context, asking how to type a component / hook / event handler / ref / context, or asking about React+TS interop with @wordpress/element. SKIP for non-React TypeScript questions, build/tsconfig setup unrelated to React, or general JavaScript work.
---

# TypeScript for React — WordPress development reference

> **Before introducing TypeScript to a WordPress project**: check whether the project is set up for it. Run a quick audit:
> - Is there a `tsconfig.json` at the project root?
> - Does `package.json` list `typescript` as a dep, or any `@types/*` packages?
> - Are there any existing `.ts` / `.tsx` files (`Glob "**/*.{ts,tsx}"` excluding `node_modules` and `build/`)?
>
> If the answer to all three is "no", **do not** silently add TS files — that requires a `tsconfig.json`, build pipeline updates, and team agreement. `@wordpress/scripts` does support TypeScript out of the box, but you still need the config and team buy-in. Push back to the user before adding the first `.tsx`.
>
> When the project IS set up for TS, this skill auto-triggers on `.tsx`/`.ts` edits and answers React+TS questions.

## How to use this skill

1. The cheat-sheets cover what comes up daily in React+TS work.
2. For utility types, conditional types, mapped types, generic constraints — `WebFetch` the relevant TS handbook page rather than guessing. Type-level code is famously easy to get subtly wrong.
3. When typing a component that uses `@wordpress/*` packages, the WP packages ship type definitions in `node_modules/@wordpress/<pkg>/build-types/` — check those for source-of-truth signatures rather than guessing.

## Typing components

### Function component with no children

```tsx
type ButtonProps = {
  label: string;
  onClick: () => void;
  disabled?: boolean;
};

const Button = ({ label, onClick, disabled = false }: ButtonProps) => (
  <button onClick={onClick} disabled={disabled}>{label}</button>
);
```

- Prefer `type` over `interface` for props unless you need declaration merging.
- Prefer the implicit `JSX.Element` return — only annotate the return type when it actually changes (e.g. returning `null | JSX.Element`).
- **Don't use `React.FC<Props>`** in modern React+TS — it adds an implicit `children` (in older versions) and obscures generics. The plain-arg form above is preferred by the React+TS community.

### Component with children

```tsx
import type { ReactNode } from "react"; // or "@wordpress/element" in WordPress projects

type CardProps = {
  title: string;
  children: ReactNode;          // anything renderable
};
```

`ReactNode` covers strings, numbers, elements, arrays, fragments, null, false. Use `ReactElement` only when you specifically need an element (rare). Use `ComponentType<P>` for "a component that takes these props."

### Component with default props

Default in the destructure (TS infers correctly):

```tsx
const Box = ({ padding = 16 }: { padding?: number }) => /* ... */;
```

Don't use the legacy `Component.defaultProps` static — it doesn't compose with TS inference well in modern setups.

### Generic component

```tsx
type ListProps<T> = {
  items: T[];
  render: (item: T) => ReactNode;
};

const List = <T,>(props: ListProps<T>) => (
  <ul>{props.items.map((item, i) => <li key={i}>{props.render(item)}</li>)}</ul>
);
```

The `<T,>` (with comma) is needed in `.tsx` so the parser doesn't read `<T>` as a JSX tag. In `.ts` files, plain `<T>` works.

### Polymorphic component (`as` prop)

```tsx
type AsProp<C extends React.ElementType> = { as?: C };
type PolyProps<C extends React.ElementType, P = {}> = P & AsProp<C> &
  Omit<React.ComponentPropsWithoutRef<C>, keyof P | "as">;

const Box = <C extends React.ElementType = "div">(
  { as, ...rest }: PolyProps<C, { padding?: number }>
) => {
  const Tag = as ?? "div";
  return <Tag {...rest} />;
};
```

This pattern is fiddly — copy from a known-good source rather than reinventing. The Radix UI repo and `react-polymorphic-types` library are good references.

## Typing hooks

### useState

```tsx
const [count, setCount] = useState(0);              // inferred: number
const [user, setUser] = useState<User | null>(null); // explicit when initial value is null
const [items, setItems] = useState<Item[]>([]);     // explicit so [] isn't inferred as never[]
```

### useRef

Two flavors — pick deliberately:

```tsx
// DOM ref — read-only-ish, set by React via the ref attribute
const inputRef = useRef<HTMLInputElement>(null);
<input ref={inputRef} />
inputRef.current?.focus();

// Mutable instance ref — you write to .current
const timerRef = useRef<number | null>(null);
timerRef.current = window.setTimeout(/* ... */);
```

For the DOM-ref form, the initial value **must** be `null` and the type is `RefObject<T>` (`current` is read-only). For the mutable form, the type is `MutableRefObject<T>` (`current` is writable).

### useReducer

```tsx
type State = { count: number };
type Action = { type: "INCREMENT"; by: number } | { type: "RESET" };

const reducer = (state: State, action: Action): State => {
  switch (action.type) {
    case "INCREMENT": return { count: state.count + action.by };
    case "RESET":     return { count: 0 };
  }
};

const [state, dispatch] = useReducer(reducer, { count: 0 });
```

Discriminated unions on `action.type` give you exhaustive narrowing in the switch.

### useContext

```tsx
type Theme = "light" | "dark";
const ThemeContext = createContext<Theme | undefined>(undefined);

const useTheme = () => {
  const ctx = useContext(ThemeContext);
  if (ctx === undefined) throw new Error("useTheme outside Provider");
  return ctx;
};
```

The `undefined` default + custom hook with the throw is a standard pattern — it surfaces missing-Provider bugs at runtime instead of silently using a wrong default.

### useCallback / useMemo

```tsx
const onSubmit = useCallback((value: string) => {
  /* ... */
}, []);

const sorted = useMemo<Item[]>(() => items.slice().sort(cmp), [items]);
```

TS infers most of this. Annotate the return only when inference picks something too wide (e.g. `string` instead of a literal).

## Typing events

```tsx
const onChange = (e: React.ChangeEvent<HTMLInputElement>) => {
  setValue(e.target.value);
};

const onClick = (e: React.MouseEvent<HTMLButtonElement>) => { /* */ };
const onKeyDown = (e: React.KeyboardEvent<HTMLDivElement>) => { /* */ };
const onSubmit = (e: React.FormEvent<HTMLFormElement>) => { e.preventDefault(); };
```

The element generic (`HTMLInputElement` etc.) determines `e.target` / `e.currentTarget` type. **`e.currentTarget` is the listener's element; `e.target` may be a child** — use `currentTarget` when you want the typed element you attached the handler to.

## Typing common patterns

### `as const` for narrow literal inference

```ts
const sizes = ["sm", "md", "lg"] as const;
type Size = typeof sizes[number]; // "sm" | "md" | "lg"
```

Without `as const`, the array is `string[]` and you lose the literal union.

### Discriminated unions

```ts
type Result =
  | { ok: true; value: number }
  | { ok: false; error: string };

const handle = (r: Result) => {
  if (r.ok) {
    r.value; // narrowed to number
  } else {
    r.error; // narrowed to string
  }
};
```

The discriminant (`ok` here) must be a literal type. This is the cleanest way to model "either success or error" without `null`/`undefined`.

### Narrowing

```ts
function fmt(x: string | number) {
  if (typeof x === "string") return x.toUpperCase(); // narrowed to string
  return x.toFixed(2);                                // narrowed to number
}

function isUser(v: unknown): v is User {
  return typeof v === "object" && v !== null && "id" in v;
}
```

Type predicates (`v is User`) are how you tell TS that a runtime check narrows the type. Use sparingly — they're trusted, not verified.

### Utility types you'll reach for often

| Type | Purpose |
|---|---|
| `Partial<T>` | All fields optional |
| `Required<T>` | All fields required |
| `Readonly<T>` | All fields readonly |
| `Pick<T, K>` | Keep only listed keys |
| `Omit<T, K>` | Remove listed keys |
| `Record<K, V>` | Object with keys K and values V |
| `ReturnType<F>` | The return type of a function |
| `Parameters<F>` | The parameter tuple of a function |
| `Awaited<T>` | Unwrap a Promise |
| `NonNullable<T>` | Exclude `null \| undefined` |
| `keyof T` | Union of T's keys |
| `T[K]` | Index access |
| `React.ComponentProps<typeof X>` | Get the props of a component |
| `React.ComponentPropsWithoutRef<"button">` | Native element props minus ref |

### Component-prop reuse

```tsx
import type { ComponentProps } from "react";

type ButtonProps = ComponentProps<"button"> & { variant?: "primary" | "ghost" };
const Button = ({ variant, ...rest }: ButtonProps) => (
  <button data-variant={variant} {...rest} />
);
```

Inheriting from `ComponentProps<"button">` gives you all native attributes (`type`, `disabled`, `aria-*`, etc.) for free.

## Common bugs and how to catch them

| Symptom | Cause | Fix |
|---|---|---|
| `useState<[]>` infers `never[]` | TS infers from `[]` literally | Annotate: `useState<Item[]>([])` |
| `ref.current is possibly null` errors | DOM refs start as `null` | Optional chain: `ref.current?.focus()` |
| `Object is possibly undefined` after a context lookup | `createContext<T \| undefined>` default | Custom hook that throws if undefined |
| `Type ... is missing the following properties from type ...` | Forgot a required prop | Add the prop or mark optional with `?` |
| `JSX element type ... has no construct or call signatures` | Importing a non-component as a component | Check the export shape |
| Generic component looks like JSX | `<T>` parsed as JSX in `.tsx` | Use `<T,>` with the trailing comma |
| `as` prop polymorphism is "any" | Lost the generic in spread | Use the polymorphic pattern above |

## Documentation URL index — WebFetch when uncertain

### TypeScript handbook
- Handbook home: https://www.typescriptlang.org/docs/
- Everyday Types: https://www.typescriptlang.org/docs/handbook/2/everyday-types.html
- Narrowing: https://www.typescriptlang.org/docs/handbook/2/narrowing.html
- Generics: https://www.typescriptlang.org/docs/handbook/2/generics.html
- Type Manipulation (utility types, mapped types): https://www.typescriptlang.org/docs/handbook/2/type-manipulation.html
- Utility Types reference: https://www.typescriptlang.org/docs/handbook/utility-types.html
- Modules: https://www.typescriptlang.org/docs/handbook/2/modules.html
- TSConfig reference (when adding TS to a project): https://www.typescriptlang.org/tsconfig

### React + TypeScript
- React TS Cheatsheet (community-maintained, comprehensive): https://react-typescript-cheatsheet.netlify.app/
- React docs — TypeScript page: https://react.dev/learn/typescript

### React API (the typed APIs are documented here too)
- React reference: https://react.dev/reference/react

## Anti-patterns to avoid

- **Don't add `.ts`/`.tsx` files to a project that lacks a `tsconfig.json`** — see the note at the top. Mixed JS+TS without setup will silently fail to type-check.
- Don't use `any`. If you genuinely need an escape hatch, use `unknown` and narrow.
- Don't use `React.FC<Props>` — see the components section.
- Don't annotate things TS already infers correctly (return types of simple components, `useState<number>(0)`).
- Don't suppress errors with `// @ts-ignore` — use `// @ts-expect-error` with a comment, so you find out when the error goes away.
- Don't write a function as `(...) => any`. Type the return.
- Don't cast with `as` to silence an error you don't understand. Casts lie to the compiler — fix the underlying type instead.
- Don't use enums for string unions — `type Color = "red" | "blue"` is simpler, has no runtime cost, and narrows better.
- Don't `import React from "react"` in modern setups (React 17+ JSX transform makes it unnecessary). In WordPress projects, you'd import from `@wordpress/element` if you needed it.
