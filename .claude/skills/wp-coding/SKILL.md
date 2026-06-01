---
name: wp-coding
description: WordPress coding standards reference — PHP / JS / CSS / HTML style rules, inline documentation (DocBlock) format, accessibility standards, and the security primitives (escaping, sanitization, nonces, capability checks, i18n) that every plugin/theme file should follow. TRIGGER when the user asks about coding style, formatting, naming, lint failures, doc block / JSDoc structure, escaping vs sanitizing, which `esc_*` or `sanitize_*` function to use, nonce usage, capability checks, accessibility (a11y), text-domain / i18n correctness, or whenever a code review-style question comes up ("is this safe?", "is this idiomatic?"). SKIP when the user is asking about a specific WP API surface (use wp-plugin-dev / wp-api / wp-theme-dev / wp-block-editor) rather than the form/style/safety of code.
---

# WordPress coding standards reference

This skill is the quick reference for **how** to write WordPress code (style, safety, docs), not **what** APIs to call. For API specifics, defer to `wp-plugin-dev`, `wp-api`, `wp-theme-dev`, `wp-block-editor`.

## Detect the project's identity first

Before writing or reviewing code, identify the project's specific values so naming and i18n stay consistent:

1. **Textdomain**: read the `Text Domain:` header in the main plugin PHP file (file with `Plugin Name:` header) or theme `style.css`. If absent, use the plugin/theme folder slug.
2. **Function/hook/option/transient prefix**: typically the textdomain in snake_case (e.g. textdomain `my-plugin` → prefix `my_plugin_`). Confirm by `Grep "add_action\(.*'<prefix>"` or by looking at existing global function names.
3. **PHP namespace**: `Grep "^namespace " --type=php` in the project root. Use whatever the project already declared.
4. **JS global / localized var name**: `Grep "wp_localize_script"` to see what var name the project exposes to JS.
5. **CSS class prefix**: usually the textdomain (kebab-case) — confirm by reading existing block/template markup.

Substitute these throughout. Don't carry naming from another project.

## How to use this skill

1. Match the question to one of the sections below — naming, escaping, sanitization, nonces, i18n, doc blocks, JS/CSS style, or accessibility.
2. The cheat-sheet is enough for the common decisions ("which `esc_*` do I use here?"). For corner cases — e.g. nested `wp_kses` arguments, ARIA-specific patterns, exact lint rule IDs — `WebFetch` the URL from the index.
3. Many WP projects enforce some of this via tooling (`grunt checktextdomain`, `wp-scripts lint-js`, `wp-scripts lint-style`, `phpcs`). When in doubt, run the project's lint command rather than guessing.

## General WordPress style anchors

- **Indentation**: tabs (not spaces) for PHP, JS, CSS, JSON. WordPress core uses tab width 4. Match the existing files in the project.
- **Line endings**: LF.
- **Class name conventions** (when the project uses class-based PHP): WordPress core uses `Pascal_Snake_Case` (`WP_Query`, `Block_Base`). Files are `kebab-case.php` or `class-<name>.php` depending on convention. Match the existing project.
- **No closing `?>`** at end of pure-PHP files (trailing whitespace can break `header()` calls).
- **Yoda conditions** in comparisons: `'desktop' === $device`, not `$device === 'desktop'`. WordPress core uses these — match if the project does.

## Escaping — choose by *output context*, not data type

Escape **late** (right at the point of output), not at the boundary. The function depends on where the value lands in the HTML:

| Output context | Function |
|---|---|
| Plain text inside HTML | `esc_html()` |
| HTML attribute value | `esc_attr()` |
| `href`, `src`, etc. URL attributes | `esc_url()` (display) / `esc_url_raw()` (storage) |
| Inside `<textarea>` | `esc_textarea()` |
| Inside a `<script>` tag (JSON) | `wp_json_encode()` |
| Allowed-HTML output (post content, rich text) | `wp_kses_post()` (post-content allowlist) or `wp_kses( $html, $allowed )` |
| Translation that becomes plain text | `esc_html__()` / `esc_html_e()` |
| Translation that becomes an attribute | `esc_attr__()` / `esc_attr_e()` |

Don't double-escape. Don't use `esc_html()` on something that should be HTML (use `wp_kses_post`). Don't use `esc_attr()` for URLs (use `esc_url`).

## Sanitization — at the input boundary

Sanitize **early** (when accepting input from `$_POST` / `$_GET` / `$_REQUEST` / REST request / option update). Always `wp_unslash()` first to undo WP's magic-quotes legacy.

| Value kind | Function |
|---|---|
| Single-line text | `sanitize_text_field( wp_unslash( $val ) )` |
| Multi-line text (no HTML) | `sanitize_textarea_field( wp_unslash( $val ) )` |
| Email | `sanitize_email( $val )` |
| URL (storage) | `esc_url_raw( $val )` |
| Integer | `absint( $val )` or `(int) $val` |
| Float | `(float) $val` |
| Slug / post name | `sanitize_title( $val )`, `sanitize_key( $val )` |
| Hex color | `sanitize_hex_color( $val )` |
| HTML allowlist (rich text) | `wp_kses_post( $val )` or `wp_kses( $val, $allowed_tags )` |
| File upload | `wp_handle_upload()` (validates type + moves file) |

Match the sanitizer to the *expected shape*. Don't accept arbitrary HTML and `esc_html` it later — strip it on input with `sanitize_text_field` or `wp_kses_post`.

## Nonces — every state-changing request

- **Generate**: `wp_create_nonce( 'action_name' )` server-side; emit via `wp_localize_script` or `wp_nonce_field( 'action_name', 'security' )` in a form.
- **Verify** (AJAX): `check_ajax_referer( 'action_name', 'security' )`.
- **Verify** (admin form): `check_admin_referer( 'action_name', 'security' )`.
- **Verify** (REST): set a `permission_callback`; nonces are auto-checked when the request includes the `_wpnonce` header for cookie auth.

A nonce is **not** an authorization check — it proves intent / prevents CSRF. You still need a `current_user_can( 'capability' )` check for permissions.

## Capability checks — gate everything that mutates

```php
if ( ! current_user_can( 'edit_posts' ) ) {
    wp_send_json_error( [ 'message' => 'Forbidden' ], 403 );
}
```

Common capabilities: `manage_options` (settings pages), `edit_posts` / `edit_post` (per-post), `upload_files`, `read`. Prefer specific over `manage_options` when possible. Never gate solely on `is_user_logged_in()`.

## Internationalization (i18n)

Use the project's actual textdomain (detected above) in every gettext call:

```php
__( 'Hello world', '<plugin-textdomain>' );           // returns translated string
_e( 'Hello world', '<plugin-textdomain>' );           // echoes
esc_html__( 'Hello %s', '<plugin-textdomain>' );      // safe for HTML output
esc_attr__( 'Title', '<plugin-textdomain>' );         // safe for attributes
_n( '%d item', '%d items', $count, '<plugin-textdomain>' );  // plurals
_x( 'Post', 'noun', '<plugin-textdomain>' );          // disambiguation
sprintf( __( 'Hello %s', '<plugin-textdomain>' ), $name );   // placeholders
```

Rules:
- **Never** concatenate translated strings (`__('Hello') . $name . __('!')`). Use `sprintf` with placeholders so translators can reorder.
- **Never** translate variables (`__( $string, … )`) — gettext extractors only see literal strings.
- **Always** include the textdomain. WP.org's checktextdomain tooling will fail otherwise.
- For JS: `import { __ } from '@wordpress/i18n'`. Translations are loaded server-side via `wp_set_script_translations( 'handle', '<plugin-textdomain>', $path )`.

## PHP DocBlocks — the WP core style

Every public method, every class, every file gets a DocBlock:

```php
/**
 * Short summary in one line.
 *
 * Optional longer description, can span lines.
 *
 * @since 1.0.0
 * @access protected
 * @param string $foo Description of $foo.
 * @param array  $bar Description of $bar.
 * @return array
 */
```

Tags in this order: `@since`, `@access`, `@param`, `@return`. Align `@param` types/names. Match the version number style the project uses (semantic versioning is standard).

## JS — JSDoc when a doc is needed

```js
/**
 * Short summary.
 *
 * @param {string} name  Description.
 * @param {Object} opts
 * @param {number} opts.count Description.
 * @return {Element} Description.
 */
```

Most projects don't doc every JS function — only add a JSDoc when the function is exported, public, or non-obvious. In-block component code rarely needs them.

## CSS — BEM-style with project prefix

Standard WordPress block-style naming:
- Block: `<prefix>-<name>` (e.g. `my-plugin-hero`).
- Element: `<prefix>-<name>__<part>` (e.g. `my-plugin-hero__title`).
- Modifier: `<prefix>-<name>--<variant>` (e.g. `my-plugin-hero--centered`).

Use existing CSS variables from the project's stylesheets rather than introducing new ones; if a new token is genuinely needed, ask before adding.

## Accessibility (the things that come up most)

- **Every interactive element** needs a discernible name: visible text, `aria-label`, or `aria-labelledby`.
- **Form fields** need an associated `<label>` (or `aria-label`).
- **Color contrast** ≥ 4.5:1 for normal text, ≥ 3:1 for large text.
- **Focus indicator** must remain visible — never `outline: none` without a replacement.
- **Don't use `tabindex` > 0**. `tabindex="0"` to make non-interactive elements focusable, `tabindex="-1"` to remove from tab order.
- **Image blocks**: always provide an `alt` attribute (empty string `alt=""` for decorative images is correct, missing `alt` is not).
- **Headings** in document order — don't skip levels.
- **Live regions** (`aria-live="polite"`) for async UI updates (e.g. counter, countdown, progress-bar blocks).

## Documentation URL index

### Coding Standards (the actual handbook)
- Coding Standards home: https://developer.wordpress.org/coding-standards/
- PHP standards: https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/
- JavaScript standards: https://developer.wordpress.org/coding-standards/wordpress-coding-standards/javascript/
- CSS standards: https://developer.wordpress.org/coding-standards/wordpress-coding-standards/css/
- HTML standards: https://developer.wordpress.org/coding-standards/wordpress-coding-standards/html/
- Accessibility standards: https://developer.wordpress.org/coding-standards/accessibility-coding-standards/

### Inline Documentation
- Inline docs home: https://developer.wordpress.org/coding-standards/inline-documentation-standards/
- PHP DocBlock format: https://developer.wordpress.org/coding-standards/inline-documentation-standards/php/
- JavaScript inline docs: https://developer.wordpress.org/coding-standards/inline-documentation-standards/javascript/

### Security primitives (Code Reference, authoritative)
- Escaping: https://developer.wordpress.org/apis/security/escaping/
- Sanitizing: https://developer.wordpress.org/apis/security/sanitizing/
- Nonces: https://developer.wordpress.org/apis/security/nonces/
- Data validation: https://developer.wordpress.org/apis/security/data-validation/
- Common vulnerabilities: https://developer.wordpress.org/apis/security/common-vulnerabilities/

### Internationalization
- I18n for plugins: https://developer.wordpress.org/plugins/internationalization/
- I18n functions reference: https://developer.wordpress.org/apis/internationalization/

### Per-function quick lookup
- Function pages: `https://developer.wordpress.org/reference/functions/<name>/`
  - e.g. `esc_html/`, `esc_attr/`, `esc_url/`, `wp_kses_post/`, `sanitize_text_field/`, `wp_unslash/`, `absint/`, `wp_create_nonce/`, `check_ajax_referer/`, `current_user_can/`, `__/`, `_e/`, `_n/`, `sprintf/`.

## Anti-patterns to avoid

- Don't skip escaping "because the value came from the database" — escape on output regardless.
- Don't use `esc_html` on rich text — use `wp_kses_post`.
- Don't sanitize HTML with regex — use `wp_kses` / `wp_kses_post`.
- Don't store user input as-is and rely on later escaping — sanitize on input AND escape on output.
- Don't accept `$_POST` without `wp_unslash` before sanitization.
- Don't gate sensitive actions on `is_user_logged_in()` alone — use `current_user_can`.
- Don't translate variables or concatenate translated strings — use `sprintf` with placeholders.
- Don't omit the textdomain on any gettext call.
- Don't add a `?>` at the end of pure-PHP files.
- Don't disable WP_DEBUG in code paths to silence notices — fix the underlying issue.
- Don't use `outline: none` on focusable elements without providing a visible alternative.
