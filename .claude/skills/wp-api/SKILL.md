---
name: wp-api
description: WordPress REST API reference — register_rest_route, register_rest_field, permission_callback, schema, WP_REST_Request / WP_REST_Response / WP_Error, authentication (cookie + nonce, application passwords). TRIGGER when the user is adding/modifying a REST endpoint or REST field, calling apiFetch / wp.apiFetch from JS, debugging REST permissions/auth, designing a REST schema, or asking how to extend a core resource (post, user, media, term). SKIP for AJAX-style endpoints (use wp-plugin-dev's AJAX section) and for general WP plugin PHP that isn't REST-related.
---

# WordPress REST API reference

The REST API is the preferred extension surface for new server endpoints in modern WordPress projects. The legacy AJAX layer (`wp_ajax_*`) is kept for backwards compatibility — for new server work, **prefer REST**: it gives you schema, caching, auth via cookies + nonces, and standard error shapes.

## Detect the project's conventions first

Before writing REST code, identify what the project already uses so new endpoints stay consistent:

1. **Namespace prefix**: read the main plugin file (`*.php` with `Plugin Name:` header in the project root) or `style.css` (theme). Whatever slug it uses (e.g. `my-plugin`) is the REST namespace prefix → `my-plugin/v1`.
2. **Existing REST registration**: `Grep "register_rest_route|register_rest_field"` to find where the project already registers REST. Match its file location, class structure, and naming style.
3. **Existing auth pattern**: check whether existing routes use `current_user_can`, custom permission callbacks, or `__return_true` (the last is a smell — flag if you see it on write endpoints).
4. **Textdomain**: the `Text Domain:` header in the main plugin/theme file. Use it in every `__()` / `esc_html__()` call inside REST code.

Refer to the project's slug, namespace, and textdomain throughout — never hardcode values from another project.

## How to use this skill

1. Identify whether you're adding a **new endpoint** (`register_rest_route`) or **extending an existing resource** with a field (`register_rest_field`).
2. Use the templates below as the starting shape, substituting the project's actual slug/namespace/textdomain.
3. **Always `WebFetch` the relevant handbook page** when designing schema, permission callbacks, or auth flow — those have the most subtle behavior and are the easiest things to get wrong.

## Standard conventions (WordPress-wide)

- **Namespace**: `<plugin-slug>/v1`. Bump to `/v2` for breaking changes — never silently change a route's contract.
- **Route prefix**: starts with `/`, no trailing slash. Use kebab-case: `/favourites`, `/items/(?P<id>\d+)`.
- **Hook**: register inside the `rest_api_init` action.
- **Permission callbacks**: required on every route. Never use `__return_true` for anything that mutates or returns user-specific data — use `current_user_can( 'capability' )` or a custom check.
- **Response**: return `WP_REST_Response` (or a plain array — WP wraps it) for success, `WP_Error` for failure. Don't `wp_send_json_*` — that's the AJAX layer.
- **Sanitize/validate**: use the route's `args` `sanitize_callback` / `validate_callback` for input; escape on output if your response embeds HTML.

## Adding a new endpoint — template

Substitute `<plugin-slug>`, `<Plugin_Namespace>`, and `<plugin-textdomain>` with the project's actual values.

```php
<?php
namespace <Plugin_Namespace>;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Rest_Items {
    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        register_rest_route(
            '<plugin-slug>/v1',
            '/items/(?P<id>\d+)',
            [
                [
                    'methods'             => 'GET',
                    'callback'            => [ $this, 'get_item' ],
                    'permission_callback' => [ $this, 'can_read' ],
                    'args'                => [
                        'id' => [
                            'required'          => true,
                            'validate_callback' => fn( $v ) => is_numeric( $v ) && (int) $v > 0,
                            'sanitize_callback' => 'absint',
                        ],
                    ],
                ],
                [
                    'methods'             => 'POST',
                    'callback'            => [ $this, 'update_item' ],
                    'permission_callback' => [ $this, 'can_edit' ],
                    'args'                => $this->update_args(),
                ],
            ]
        );
    }

    public function can_read()  { return is_user_logged_in(); }
    public function can_edit()  { return current_user_can( 'edit_posts' ); }

    public function get_item( WP_REST_Request $request ) {
        $id = $request->get_param( 'id' );
        $post = get_post( $id );
        if ( ! $post ) {
            return new WP_Error( '<plugin-slug>_item_not_found', __( 'Item not found.', '<plugin-textdomain>' ), [ 'status' => 404 ] );
        }
        return rest_ensure_response( [
            'id'    => $post->ID,
            'title' => get_the_title( $post ),
        ] );
    }

    public function update_item( WP_REST_Request $request ) { /* … */ }

    private function update_args() {
        return [
            'title' => [
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ];
    }
}

new Rest_Items();
```

Match the project's existing instantiation pattern — singleton trait, plain `new`, factory function, etc. — by copying from a sibling class.

## Adding a field to an existing resource — template

```php
register_rest_field(
    'user',  // resource: 'post', 'page', 'attachment', 'user', a CPT slug, or array
    '<plugin_slug>_favourites',
    [
        'get_callback'    => [ $this, 'get_user_favourites' ],
        'update_callback' => null,        // null = read-only
        'schema'          => [
            'description' => __( "User's favourites.", '<plugin-textdomain>' ),
            'type'        => 'array',
            'context'     => [ 'view', 'edit' ],
        ],
    ]
);
```

- Always prefix the field name with the project's slug (snake_case) so it never collides with core fields.
- `update_callback` accepts `( $value, $object, $field_name )`. Return `true` or `WP_Error`. Sanitize inside the callback.
- Provide a `schema` whenever possible — it lets the field appear in `OPTIONS` responses and feeds `apiFetch`.

## Calling REST from JS

```js
import apiFetch from '@wordpress/api-fetch';

const data = await apiFetch( {
    path: '/<plugin-slug>/v1/items/42',
    method: 'GET',
} );
```

`@wordpress/api-fetch` automatically attaches the cookie auth nonce when running inside `wp-admin`. For block editor code, **always** prefer `apiFetch` over raw `fetch` — manual `fetch` calls won't include the nonce and will fail authenticated routes.

## Authentication summary

| Context | Auth method |
|---|---|
| Logged-in user inside wp-admin | Cookie + nonce (auto for `apiFetch`) |
| External script / mobile app | Application Passwords (`Authorization: Basic`) |
| Plugin-to-plugin server-side | Direct PHP — call internal functions, not REST |
| Public read-only data | `permission_callback` returns `true`, but never for user data |

Never invent a custom token scheme — the REST API has standard auth methods. If the user wants something custom, push back and ask why the standard methods don't fit.

## Common WP_REST_Request methods

- `$request->get_param( 'name' )` — value (URL, body, or query — checked in that order).
- `$request->get_url_params()` — route-pattern matches (`(?P<id>\d+)`).
- `$request->get_query_params()` — query string.
- `$request->get_json_params()` — body when `Content-Type: application/json`.
- `$request->get_body_params()` — body when form-encoded.
- `$request->get_header( 'X-WP-Nonce' )` — headers.
- `$request->get_method()` — `GET` / `POST` / etc.

## Common WP_REST_Response patterns

```php
return rest_ensure_response( $data );                          // 200
return rest_ensure_response( $data )->set_status( 201 );       // created
return new WP_REST_Response( null, 204 );                      // no content
return new WP_Error( 'code', $message, [ 'status' => 400 ] );  // error
```

`WP_Error` codes should be prefixed with the plugin slug: `<plugin_slug>_<error_kind>` so consumers can branch on them.

## Schema basics

```php
'schema' => [
    'title'      => 'item',
    'type'       => 'object',
    'properties' => [
        'id'    => [ 'type' => 'integer', 'context' => [ 'view', 'edit' ] ],
        'title' => [ 'type' => 'string',  'context' => [ 'view', 'edit' ] ],
    ],
],
```

- `context` controls which fields appear in `?context=view` / `?context=edit` / `?context=embed`.
- A schema unlocks: `OPTIONS` introspection, automatic param validation, embed support.

## Documentation URL index — WebFetch when uncertain

### REST API Handbook
- REST API home: https://developer.wordpress.org/rest-api/
- Key concepts: https://developer.wordpress.org/rest-api/key-concepts/
- Routes & endpoints: https://developer.wordpress.org/rest-api/extending-the-rest-api/routes-and-endpoints/
- Adding custom endpoints: https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/
- Modifying responses (register_rest_field): https://developer.wordpress.org/rest-api/extending-the-rest-api/modifying-responses/
- Custom content types: https://developer.wordpress.org/rest-api/extending-the-rest-api/custom-content-types/
- Schema: https://developer.wordpress.org/rest-api/extending-the-rest-api/schema/
- Controller classes: https://developer.wordpress.org/rest-api/extending-the-rest-api/controller-classes/

### Auth
- Authentication overview: https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/
- Application Passwords: https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/#application-passwords

### Using the API
- Global parameters: https://developer.wordpress.org/rest-api/using-the-rest-api/global-parameters/
- Pagination: https://developer.wordpress.org/rest-api/using-the-rest-api/pagination/
- Linking & embedding: https://developer.wordpress.org/rest-api/using-the-rest-api/linking-and-embedding/
- Discovery: https://developer.wordpress.org/rest-api/using-the-rest-api/discovery/
- Frequently used endpoints (reference): https://developer.wordpress.org/rest-api/reference/

### Code Reference (authoritative function/class lookup)
- `register_rest_route`: https://developer.wordpress.org/reference/functions/register_rest_route/
- `register_rest_field`: https://developer.wordpress.org/reference/functions/register_rest_field/
- `rest_ensure_response`: https://developer.wordpress.org/reference/functions/rest_ensure_response/
- `WP_REST_Request`: https://developer.wordpress.org/reference/classes/wp_rest_request/
- `WP_REST_Response`: https://developer.wordpress.org/reference/classes/wp_rest_response/
- `WP_REST_Server`: https://developer.wordpress.org/reference/classes/wp_rest_server/
- `WP_Error`: https://developer.wordpress.org/reference/classes/wp_error/
- `rest_api_init` hook: https://developer.wordpress.org/reference/hooks/rest_api_init/

### JS client
- `@wordpress/api-fetch`: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-api-fetch/

## Anti-patterns to avoid

- Don't omit `permission_callback` — registering without it triggers a `_doing_it_wrong` notice and the route logs a warning every request.
- Don't use `__return_true` as `permission_callback` for anything user-specific or mutating.
- Don't reinvent auth — use cookie+nonce inside the editor and Application Passwords for external clients.
- Don't return raw `wp_send_json_*` from a REST handler — that's the AJAX path. Return `WP_REST_Response` / `WP_Error`.
- Don't use `fetch()` from editor JS — use `apiFetch` so the nonce is attached automatically.
- Don't bump the namespace casually — `/v2` is a breaking-change signal. Add new fields/routes on `/v1` when backward-compatible.
- Don't expose internal IDs/PII in unauthenticated endpoints — design `permission_callback` and `context` filtering deliberately.
- Don't bypass validation by sanitizing inside the callback when the route has `args` — use `sanitize_callback` / `validate_callback` so the framework does it before your handler runs.
