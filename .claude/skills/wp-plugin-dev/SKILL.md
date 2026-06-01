---
name: wp-plugin-dev
description: WordPress plugin (PHP) development reference — autoloaders, singleton patterns, abstract block bases, AJAX bases, asset enqueueing, REST field registration, block category filters, and the WP core APIs they wrap (register_block_type, register_rest_field, wp_enqueue_script/style, wp_localize_script, register_setting, add_action/add_filter, wp_send_json_*, check_ajax_referer, get_option/update_option, transients, esc_*/sanitize_* helpers, __()/_e()/esc_html__). TRIGGER when editing any PHP file in a WordPress plugin (the main plugin file, class/, inc/, ajax/, core/, trait/, src/, etc.), adding/modifying a hook/filter, writing a render_callback for a dynamic block, adding a REST endpoint or AJAX handler, enqueuing scripts or styles, or asking about WP core PHP functions, plugin coding standards, escaping/sanitization, or i18n. SKIP for block editor JS authoring (use wp-block-editor) and @wordpress/data store work (use wp-data-store).
---

# WordPress plugin (PHP) development reference

The PHP side of a WordPress plugin typically registers blocks server-side, exposes REST/AJAX endpoints, enqueues assets, and registers post types / taxonomies / settings / hooks. This file documents WordPress-standard patterns and points at upstream docs to fetch when the cheat-sheet runs out.

## Detect the project's structure first

Before adding or modifying PHP code, see what conventions the project already uses:

1. **Main plugin file**: locate the file with the `Plugin Name:` header (`Grep "Plugin Name:" --type=php`). Read the header to learn the **textdomain**, **plugin slug**, and **defined constants** (e.g. `<PLUGIN>_PATH`, `<PLUGIN>_URL`).
2. **Namespace / no namespace**: `Grep "^namespace " --type=php` in the project root. Modern projects use a PSR-style namespace; older ones use prefixed function/class names. Match what's there.
3. **Autoloader**: `Grep "spl_autoload_register|composer/autoload" --type=php`. If a custom autoloader is in place, read it and follow its file-naming rules. If `vendor/autoload.php` is included, use Composer conventions.
4. **Class layout**: `ls` the project root for directories like `class/`, `inc/`, `includes/`, `src/`, `admin/`, `public/`, `ajax/`, `trait/`. Place new files where similar ones already live.
5. **Singleton or factory pattern**: `Grep "get_instance|trait Singleton"`. Many WP plugins use a singleton trait — if so, follow it for new services.
6. **Block base / AJAX base / REST class**: `Grep "extends Block_Base|extends Ajax|class Rest"` to find existing abstracts. Extend them rather than building parallel patterns.
7. **Hook prefix**: usually `<plugin_slug>_` (snake_case). Confirm by reading existing `add_action` / `add_filter` calls.

Substitute these values throughout the templates below — do **not** carry naming from another project.

## How to use this skill

1. **For project-specific patterns** (autoloader rules, singleton, abstracts, naming, file layout): use what you discovered above. Match the existing pattern; don't introduce parallel ones.
2. **For WP core API details** (function signatures, return shapes, hook callback args, sanitizer choice): `WebFetch` the matching URL from the index at the bottom **before** writing code. WP function signatures are easy to misremember and the Code Reference is authoritative.
3. **For "is there a built-in for this?"** — search the WP Code Reference (`https://developer.wordpress.org/reference/`) by function or class name; almost always there is.

## Common WordPress plugin layout

The exact directories vary by project, but most modern plugins follow some version of this:

```
my-plugin.php               # plugin header, namespace, autoloader, init
trait/singleton.php         # Singleton trait (one instance per concrete class)
inc/                        # Misc services (helpers, asset registration, REST registration)
class/                      # Domain classes — typically one file per block / per service
ajax/                       # AJAX handlers
admin/                      # Admin-only screens / settings
public/                     # Front-end-only code
src/                        # Block editor JS source
build/                      # Webpack/wp-scripts output (block.json + index.js + index.css per block)
languages/                  # .pot file + translations
```

`Glob` the project root and adapt — some use `includes/`, some put everything in `src/`, some use Composer + `app/`. Don't assume.

## Standard plugin conventions

- **Constants**: define `<PLUGIN>_PATH` and `<PLUGIN>_URL` at the top of the main plugin file. Always reference these — never re-derive paths via `__DIR__` in nested files.
- **Textdomain**: declared in the plugin header and matched in every gettext call. Many projects enforce this in CI.
- **Hook/filter/AJAX prefix**: `<plugin_slug>_` (snake_case PHP) matching the JS-side global (camelCase) if there is one.
- **`ABSPATH` guard**: every PHP file starts with `if ( ! defined( 'ABSPATH' ) ) { exit; }` after the namespace declaration.
- **Class-exists guard**: when not using an autoloader, wrap class declarations in `if ( ! class_exists( '<Namespace>\\<Class>' ) ) { … }`.
- **No closing `?>`** at end of pure-PHP files.

## Singleton pattern (when the project uses one)

```php
namespace <Plugin_Namespace>;

trait Singleton {
    private static $instances = [];
    public static function get_instance() {
        $class = static::class;
        if ( ! isset( self::$instances[ $class ] ) ) {
            self::$instances[ $class ] = new static();
        }
        return self::$instances[ $class ];
    }
}

class My_Service {
    use Singleton;
    public function __construct() { /* hooks */ }
}

My_Service::get_instance();
```

Keying by `static::class` keeps one instance **per concrete class** — important when abstract bases are used with multiple subclasses. Don't add `private __construct()` — `get_instance()` calls `new static()` directly.

## Server-side block class — typical pattern

When a project has a `Block_Base` abstract, every block class extends it:

```php
namespace <Plugin_Namespace>\Classes;

class My_Block extends Block_Base {
    use \<Plugin_Namespace>\Singleton;
    public $slug = 'my-block';

    // Optional: dynamic (server-rendered) blocks implement render().
    public function render( $attrs, $content ) {
        // Return the HTML string. Use esc_*/wp_kses_post on output.
    }

    // Optional: emit per-block inline CSS via project's CSS processor (if any).
    public function prepare_scripts() { /* ... */ }
}

My_Block::get_instance();
```

Without an abstract: register the block directly:

```php
add_action( 'init', function () {
    register_block_type(
        plugin_dir_path( __FILE__ ) . 'build/blocks/my-block',
        [
            'render_callback' => function ( $attrs, $content ) { /* ... */ },
        ]
    );
} );
```

Pass the **build** directory (where the compiled `block.json` lives), not the `src/` directory.

## Asset enqueueing

Two key hooks:
- `enqueue_block_assets` — runs on **both** front-end and editor. Use for shared block styles/scripts (vendor libs, plugin-wide CSS).
- `enqueue_block_editor_assets` — editor only. Use for `wp_localize_script` to expose nonces/config to JS.

```php
add_action( 'enqueue_block_editor_assets', function () {
    wp_localize_script(
        'my-plugin-editor',
        'MY_PLUGIN_VAR',
        [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'my_plugin_ajax_nonce' ),
        ]
    );
} );
```

Per-block JS/CSS is auto-enqueued by `register_block_type` reading the built `block.json` — don't double-enqueue. If a block needs an additional vendor script, add it inside that block's PHP class or a central `Script` class with a clear conditional.

## AJAX — the pattern

```php
add_action( 'wp_ajax_<plugin_slug>_my_action', function () {
    check_ajax_referer( '<plugin_slug>_ajax_nonce', 'security' );
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( [ 'message' => 'Forbidden' ], 403 );
    }
    $data = sanitize_text_field( wp_unslash( $_POST['data'] ?? '' ) );
    // ...
    wp_send_json_success( [ 'result' => $data ] );
} );
```

If the project has an `Ajax\Base`-style abstract that auto-routes `wp_ajax_*` actions to methods, follow that pattern instead — it usually centralizes nonce verification and capability checks.

REST is preferred over AJAX for new endpoints (better caching, schema, auth) — see `wp-api`. Only use AJAX if you're extending an existing handler or have a specific reason.

## REST — see wp-api

For brand-new REST endpoints, use `register_rest_route` inside a `rest_api_init` callback, namespace under `<plugin-slug>/v1`, and always set a `permission_callback`. Defer to the `wp-api` skill for the full pattern.

## WP coding standards (PHP side)

- **Escape on output** every variable: `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()`, `esc_textarea()`. Never echo a variable raw.
- **Sanitize on input** every superglobal/user value: `sanitize_text_field()`, `sanitize_email()`, `absint()`, `wp_unslash()` before sanitization.
- **Nonces** on every state-changing AJAX/REST/form: `wp_create_nonce()`, `wp_verify_nonce()`, `check_ajax_referer()`, `check_admin_referer()`.
- **i18n**: `__( 'string', '<plugin-textdomain>' )`, `_e()`, `esc_html__()`, `esc_attr__()`, `_n()` (plurals), `_x()` (context). Never concatenate translated strings.
- **DocBlocks** with `@since`, `@param`, `@return` on every public method.
- **Yoda conditions** when comparing: `'desktop' === $device`. (Match the project's existing style.)
- **Prefix everything global**: function names, action/filter names, JS globals, CSS classes, post meta keys, option names, transient keys.
- **No closing `?>`** at end of pure-PHP files.

## Common WP APIs to use (don't reinvent)

When in doubt, search the Code Reference. Frequently relevant:

- **Hooks core**: `add_action`, `add_filter`, `do_action`, `apply_filters`, `remove_action`. Always pass `priority` and `accepted_args` when needed.
- **Block registration**: `register_block_type`, `register_block_pattern`, `WP_Block_Patterns_Registry`, `parse_blocks`, `render_block`.
- **Enqueue**: `wp_enqueue_script`, `wp_enqueue_style`, `wp_register_script`, `wp_localize_script`, `wp_add_inline_style`, `wp_add_inline_script`, `wp_set_script_translations`.
- **Options & transients**: `get_option`, `update_option`, `delete_option`, `get_transient`, `set_transient`, `delete_transient`. Prefer transients for cacheable data; prefix the key.
- **HTTP**: `wp_remote_get`, `wp_remote_post`, `wp_remote_retrieve_body`. Check `is_wp_error()` on the result.
- **Capabilities**: `current_user_can`, `wp_get_current_user`, `is_user_logged_in`. Always gate admin/AJAX/REST handlers.
- **REST**: `register_rest_route`, `register_rest_field`, `rest_ensure_response`. Always set `permission_callback`.
- **WP_Query**: prefer over raw SQL. Reset with `wp_reset_postdata()` after custom loops.
- **Custom post types / taxonomies**: `register_post_type`, `register_taxonomy`. Hook on `init`. Set `'show_in_rest' => true` if the editor needs to interact with them.

## Documentation URL index — WebFetch when uncertain

### Top-level developer portal
- Developer home: https://developer.wordpress.org/

### Plugin Handbook
- Plugin Handbook: https://developer.wordpress.org/plugins/
- Plugin basics: https://developer.wordpress.org/plugins/plugin-basics/
- Hooks (actions/filters): https://developer.wordpress.org/plugins/hooks/
- Settings API: https://developer.wordpress.org/plugins/settings/
- Metadata (post meta, term meta, user meta): https://developer.wordpress.org/plugins/metadata/
- Custom post types: https://developer.wordpress.org/plugins/post-types/
- Taxonomies: https://developer.wordpress.org/plugins/taxonomies/
- Users (roles, capabilities): https://developer.wordpress.org/plugins/users/
- HTTP API: https://developer.wordpress.org/plugins/http-api/
- Cron: https://developer.wordpress.org/plugins/cron/
- Internationalization: https://developer.wordpress.org/plugins/internationalization/
- Security (nonces, escaping, sanitizing, capability checks): https://developer.wordpress.org/plugins/security/
- AJAX in plugins: https://developer.wordpress.org/plugins/javascript/ajax/

### REST API Handbook
- REST API home: https://developer.wordpress.org/rest-api/
- Adding endpoints: https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/
- Modifying responses (register_rest_field): https://developer.wordpress.org/rest-api/extending-the-rest-api/modifying-responses/
- Authentication: https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/
- Schema: https://developer.wordpress.org/rest-api/extending-the-rest-api/schema/

### Code Reference (authoritative function/class lookup)
- Reference home: https://developer.wordpress.org/reference/
- Function pages: https://developer.wordpress.org/reference/functions/<function_name>/
  - e.g. `register_block_type/`, `wp_enqueue_script/`, `wp_localize_script/`, `register_rest_field/`, `register_rest_route/`, `add_action/`, `apply_filters/`, `wp_send_json_success/`, `check_ajax_referer/`, `wp_create_nonce/`, `sanitize_text_field/`, `esc_html/`, `wp_kses_post/`, `get_option/`, `set_transient/`, `parse_blocks/`, `render_block/`, `__/`, `_e/`.
- Class pages: https://developer.wordpress.org/reference/classes/<class_name>/
  - e.g. `wp_query/`, `wp_error/`, `wp_block_patterns_registry/`, `wp_rest_request/`, `wp_rest_response/`.
- Hook pages: https://developer.wordpress.org/reference/hooks/<hook_name>/
  - e.g. `init/`, `wp_enqueue_scripts/`, `enqueue_block_assets/`, `enqueue_block_editor_assets/`, `rest_api_init/`, `block_categories_all/`.

### Coding Standards
- PHP standards: https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/
- HTML standards: https://developer.wordpress.org/coding-standards/wordpress-coding-standards/html/
- CSS standards: https://developer.wordpress.org/coding-standards/wordpress-coding-standards/css/
- JavaScript standards: https://developer.wordpress.org/coding-standards/wordpress-coding-standards/javascript/
- Inline docs (PHP): https://developer.wordpress.org/coding-standards/inline-documentation-standards/php/
- Accessibility: https://developer.wordpress.org/coding-standards/accessibility-coding-standards/

### Common Reference Topics
- Internationalization (PHP): https://developer.wordpress.org/apis/internationalization/
- Settings API: https://developer.wordpress.org/apis/settings/
- Options API: https://developer.wordpress.org/apis/options/
- Transients API: https://developer.wordpress.org/apis/transients/
- HTTP API: https://developer.wordpress.org/apis/http/
- Shortcode API: https://developer.wordpress.org/apis/shortcode/
- Database (wpdb): https://developer.wordpress.org/reference/classes/wpdb/

## Anti-patterns to avoid

- Don't add `require_once`/`include` for project classes when an autoloader is in place — duplicates create double-load bugs.
- Don't write `private function __construct()` on a singleton if the trait calls `new static()` directly — match the trait's expectations.
- Don't hardcode plugin paths/URLs — use the project's defined constants (e.g. `<PLUGIN>_PATH`, `<PLUGIN>_URL`).
- Don't pass `src/blocks/<slug>` to `register_block_type` — the built `block.json` lives in `build/`.
- Don't echo unescaped variables. Don't trust `$_REQUEST` / `$_POST` without `sanitize_*` + `wp_unslash`.
- Don't add a state-changing AJAX/REST endpoint without a `permission_callback` (REST) or nonce + capability check (AJAX).
- Don't use a different textdomain than the project's declared one.
- Don't prefix new hooks/filters/options/transients with anything other than the project's prefix.
- Don't add `?>` at the end of pure-PHP files — trailing whitespace breaks `header()` calls.
- Don't reach for raw `$wpdb` queries when `WP_Query` / `get_posts` / `get_post_meta` works.
