---
name: wp-theme-dev
description: WordPress theme development reference — block themes (theme.json, templates/, parts/, patterns/), classic themes (style.css header, template hierarchy, functions.php, the_content/the_loop), Customizer, theme supports, and where the line is between theme work and plugin work. TRIGGER when the user is editing or asking about theme files (style.css with the WP header, theme.json, templates/*.html, parts/*.html, functions.php in a theme context, header.php / footer.php / index.php / single.php / archive.php / page.php / 404.php / search.php), block patterns, the Site Editor, full-site editing, theme.json schema, the Customizer, the_loop / WP_Query in a template, get_template_part, body_class, wp_head/wp_footer, or "should this go in a theme or a plugin?". SKIP when the work is plugin code (use wp-plugin-dev), block authoring (use wp-block-editor), or REST work (use wp-api).
---

# WordPress theme development reference

> **Before adding theme files to a project**: confirm the project actually IS a theme. Quick check:
> - A theme has `style.css` at the root with a `Theme Name:` header.
> - A plugin has a `*.php` file at the root with a `Plugin Name:` header (and no `Theme Name:` style.css).
>
> If you're in a plugin repo and the user asks for theme-style files (`style.css` with a WP header, `theme.json`, `templates/*.html`), push back — those belong in a separate theme repo, not in a plugin.

## Detect the theme's identity first

Before editing, identify what kind of theme this is:

1. **Block vs classic**: does the project have a `theme.json` at the root and a `templates/index.html`? If yes, it's a **block theme**. If it only has PHP templates (`index.php`, `single.php`, etc.), it's a **classic theme**. If both, it's **hybrid**.
2. **Textdomain**: `Text Domain:` header in `style.css`. Use it in every gettext call.
3. **Theme slug / folder name**: typically matches the textdomain.
4. **Parent vs child**: `Template:` header in `style.css` (if present) names the parent theme. Child themes need `get_stylesheet_directory_uri()` for active-theme paths and `get_template_directory_uri()` for parent-theme paths.

## How to use this skill

1. Determine whether the user is asking about **block themes** (modern, theme.json + HTML templates, FSE) or **classic themes** (PHP template files, `functions.php`, the_loop). The vocabulary and file shapes differ significantly.
2. Match the question to a section below. The cheat-sheet covers the common decisions.
3. For schema details (especially `theme.json`) and template-tag / hook signatures, **`WebFetch` the matching URL from the index** — `theme.json` evolves between WP releases and the schema is too large to embed inline.

## Block theme vs classic theme — when to use which

| Block theme | Classic theme |
|---|---|
| WP 5.9+, fully editable in Site Editor | Pre-FSE, edited via Customizer + code |
| `theme.json` defines styles + presets | `style.css` + CSS files define styles |
| HTML files in `templates/` and `parts/` | PHP files (`index.php`, `single.php`, etc.) |
| Patterns drive layout | The Loop drives layout |
| New themes — default to this | Existing classic codebase — extend, don't rewrite |

## Block theme structure (modern, recommended)

```
my-theme/
  style.css                 # WP header (Theme Name, Version, Text Domain, etc.) — minimal CSS
  theme.json                # tokens, presets, layout, supports, per-block styles
  functions.php             # minimal: enqueue, theme_supports, register_block_styles
  templates/                # full-page templates (index.html, single.html, page.html, 404.html, …)
  parts/                    # reusable parts (header.html, footer.html, sidebar.html)
  patterns/                 # reusable block patterns (.php with header comment + block markup)
  styles/                   # optional alt style variations (.json)
  assets/                   # images, fonts
  screenshot.png            # 1200x900
  readme.txt
```

### style.css header (required)

```css
/*
Theme Name:   My Theme
Theme URI:    https://example.com/my-theme
Author:       …
Description:  …
Version:      1.0.0
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 7.4
License:      GPL-2.0-or-later
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
Text Domain:  my-theme
Tags:         block-themes, full-site-editing
*/
```

### theme.json — the central config

Defines design tokens (colors, font sizes, spacing), layout (content/wide widths), supports (which UI controls appear), and per-block style overrides. Always set `$schema` so editor tooling validates.

```json
{
  "$schema": "https://schemas.wp.org/trunk/theme.json",
  "version": 3,
  "settings": {
    "appearanceTools": true,
    "color": {
      "palette": [
        { "slug": "primary", "color": "#a08050", "name": "Primary" }
      ]
    },
    "typography": {
      "fontSizes": [
        { "slug": "small",  "size": "0.875rem", "name": "Small" },
        { "slug": "medium", "size": "1rem",     "name": "Medium" }
      ]
    },
    "layout": { "contentSize": "720px", "wideSize": "1200px" }
  },
  "styles": {
    "color": { "background": "var(--wp--preset--color--primary)" }
  }
}
```

`theme.json` evolves quickly — fetch the schema reference before adding non-trivial sections.

### Templates and parts

- Files in `templates/` are full-page; files in `parts/` are reusable fragments. Both are HTML with block comments (`<!-- wp:… -->`).
- The template hierarchy (which template wins for which URL) is the same as classic themes — `single-{post-type}.html` > `single.html` > `index.html`.

### Patterns

A pattern is a `.php` file in `patterns/` with a header comment and block markup:

```php
<?php
/**
 * Title: Hero
 * Slug: my-theme/hero
 * Categories: featured
 * Block Types: core/template-part/header
 */
?>
<!-- wp:cover {"url":"<?php echo esc_url( get_template_directory_uri() . '/assets/hero.jpg' ); ?>"} -->
<!-- /wp:cover -->
```

Patterns are auto-registered from this directory. Don't call `register_block_pattern` for them.

## Classic theme structure (legacy)

```
my-theme/
  style.css                 # header + main CSS
  index.php                 # required fallback template
  functions.php             # bootstrap (theme_supports, enqueue, register_nav_menus, …)
  header.php / footer.php   # included by templates via get_header()/get_footer()
  single.php / page.php / archive.php / 404.php / search.php / category.php
  template-parts/
  inc/                      # PHP includes
  assets/
  screenshot.png
```

### Template hierarchy (most-specific wins)

- Single post: `single-{post-type}-{slug}.php` → `single-{post-type}.php` → `single.php` → `singular.php` → `index.php`.
- Page: `page-{slug}.php` → `page-{id}.php` → `page.php` → `singular.php` → `index.php`.
- Archive: `archive-{post-type}.php` → `archive.php` → `index.php`.
- Taxonomy: `taxonomy-{tax}-{term}.php` → `taxonomy-{tax}.php` → `taxonomy.php` → `archive.php` → `index.php`.

### functions.php essentials

```php
add_action( 'after_setup_theme', function () {
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'html5', [ 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption' ] );
    add_theme_support( 'editor-styles' );
    add_editor_style( 'editor.css' );
    register_nav_menus( [ 'primary' => __( 'Primary Menu', 'my-theme' ) ] );
    load_theme_textdomain( 'my-theme', get_template_directory() . '/languages' );
} );

add_action( 'wp_enqueue_scripts', function () {
    wp_enqueue_style(
        'my-theme-style',
        get_stylesheet_uri(),
        [],
        wp_get_theme()->get( 'Version' )
    );
} );
```

### The Loop — the canonical pattern

```php
if ( have_posts() ) :
    while ( have_posts() ) : the_post();
        get_template_part( 'template-parts/content', get_post_type() );
    endwhile;
    the_posts_navigation();
else :
    get_template_part( 'template-parts/content', 'none' );
endif;
```

Don't run a custom `WP_Query` inside the main template unless replacing the loop entirely — modify the main query via `pre_get_posts` instead.

## Customizer (classic + hybrid themes)

Register settings + sections + controls in a `customize_register` callback:

```php
add_action( 'customize_register', function ( $wp_customize ) {
    $wp_customize->add_setting( 'my_theme_accent_color', [
        'default'           => '#a08050',
        'sanitize_callback' => 'sanitize_hex_color',
        'transport'         => 'postMessage',
    ] );
    $wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'my_theme_accent_color', [
        'label'   => __( 'Accent Color', 'my-theme' ),
        'section' => 'colors',
    ] ) );
} );
```

Block themes generally don't need the Customizer — `theme.json` + the Site Editor cover what it used to.

## Plugin vs theme — where does it go?

| Concern | Theme | Plugin |
|---|---|---|
| Visual design / typography / colors | ✓ | |
| Layout / templates | ✓ | |
| Custom blocks | | ✓ |
| Post types / taxonomies | | ✓ (data outlives theme) |
| Custom fields, settings, options | | ✓ |
| Shortcodes (legacy) | | ✓ |
| Cron jobs, REST endpoints | | ✓ |
| Site-wide functionality (analytics, SEO) | | ✓ |

Rule of thumb: **content/data lives in plugins, presentation lives in themes**. If switching themes would lose user data or break the site, the code belongs in a plugin.

## Documentation URL index — WebFetch when uncertain

### Theme Handbook
- Theme Handbook home: https://developer.wordpress.org/themes/
- Getting started: https://developer.wordpress.org/themes/getting-started/
- Block themes overview: https://developer.wordpress.org/themes/block-themes/
- Block templates: https://developer.wordpress.org/themes/block-templates/
- Block template parts: https://developer.wordpress.org/themes/templates/template-parts/
- Patterns: https://developer.wordpress.org/themes/patterns/
- Classic themes overview: https://developer.wordpress.org/themes/classic-themes/
- Template hierarchy: https://developer.wordpress.org/themes/basics/template-hierarchy/
- Template tags: https://developer.wordpress.org/themes/basics/template-tags/
- The Loop: https://developer.wordpress.org/themes/basics/the-loop/
- Theme functions: https://developer.wordpress.org/themes/basics/theme-functions/
- Conditional tags: https://developer.wordpress.org/themes/basics/conditional-tags/
- Including assets: https://developer.wordpress.org/themes/basics/including-css-javascript/
- Internationalization: https://developer.wordpress.org/themes/functionality/internationalization/

### theme.json
- Global Settings & Styles overview: https://developer.wordpress.org/themes/global-settings-and-styles/
- Settings reference: https://developer.wordpress.org/themes/global-settings-and-styles/settings/
- Styles reference: https://developer.wordpress.org/themes/global-settings-and-styles/styles/
- Schema (live): https://schemas.wp.org/trunk/theme.json

### Customizer
- Customize API: https://developer.wordpress.org/themes/customize-api/
- Customizer objects: https://developer.wordpress.org/themes/customize-api/customizer-objects/

### Code Reference (authoritative function/class lookup)
- Function pages: https://developer.wordpress.org/reference/functions/<name>/
  - e.g. `add_theme_support/`, `register_nav_menus/`, `wp_enqueue_style/`, `get_template_directory/`, `get_stylesheet_uri/`, `get_header/`, `get_footer/`, `get_template_part/`, `the_post/`, `have_posts/`, `the_content/`, `the_title/`, `body_class/`, `post_class/`, `wp_head/`, `wp_footer/`, `bloginfo/`, `dynamic_sidebar/`, `register_sidebar/`, `register_block_pattern/`, `register_block_pattern_category/`.
- Class pages: https://developer.wordpress.org/reference/classes/<name>/
  - e.g. `wp_query/`, `wp_customize_manager/`, `wp_customize_setting/`, `wp_customize_control/`.

### Theme review (if planning a .org submission)
- Theme review handbook: https://developer.wordpress.org/themes/getting-started/

## Anti-patterns to avoid

- Don't put theme files in a plugin repo — they belong in a separate theme directory.
- Don't put data-y code (CPTs, settings, REST endpoints) in a theme — they should be plugins so users keep them on theme switches.
- Don't enqueue assets via `<link>`/`<script>` tags in templates — use `wp_enqueue_style` / `wp_enqueue_script` on `wp_enqueue_scripts`.
- Don't run a custom `WP_Query` inside the main template — modify the main query via `pre_get_posts`.
- Don't forget `wp_head()` and `wp_footer()` in classic theme `header.php` / `footer.php` — many plugins (and core) rely on those hooks firing.
- Don't escape output of template tags that already escape (`the_title()` does, `get_the_title()` does NOT) — read each tag's docs.
- Don't echo translated strings unescaped — use `esc_html_e()` / `esc_attr_e()`.
- Don't hardcode URLs — use `get_template_directory_uri()` (parent) or `get_stylesheet_directory_uri()` (active theme, child-aware).
- Don't ship a block theme without `theme.json` — it's the entire styling layer.
- Don't rely on the Customizer for new block themes — `theme.json` + Site Editor is the modern path.
