# Guest Key

Install and activate this directory as a WordPress plugin. Requires WordPress 6.9+, PHP 7.4+, HTTPS, and application passwords enabled for the administrator.

Activation attempts to install and activate the official [WordPress MCP Adapter](https://wordpress.org/plugins/mcp-adapter/) through WordPress.org's plugin API and the native WordPress installer. An existing adapter must be version 0.7.0 or newer. MCP setup failures do not block native REST commands or browser credentials. Credential creation returns `mcp.ready` and, on failure, `mcp.error` with a code/message; `endpoint` is null when MCP is unavailable. The connection bundle and interface identify the working native access paths and the MCP setup issue. Install or update the adapter and create access again to retry. The adapter is managed in code rather than through a `Requires Plugins` header, since WordPress's declarative dependency handling requires pre-activation setup and prevents the normal temporary disable/re-enable workflow.

Click **Connect agent** in the WordPress toolbar, in the dashboard or on the frontend, to create and copy a connection bundle. Guest Key creates a native application password for the current administrator, replaces only passwords carrying Guest Key's application ID, and expires the credential after six hours. Clipboard permission failures show a dialog with an explicit copy button. The compact bundle contains the REST API URL, command endpoints and a short example, username, password, expiration, User-Agent, browser sign-in URL, and optional MCP endpoint. Agents use ordinary HTTPS requests with HTTP Basic authentication to existing WordPress and plugin REST endpoints, discovering routes and schemas through the native API index and OPTIONS requests. No MCP client setup or WordPress-specific agent skill is required for REST access. The bundle also contains the optional MCP endpoint and browser sign-in URL. MCP clients use Streamable HTTP with the same HTTP Basic authentication (or a compatible local proxy). Browser agents open the Guest Key sign-in URL and enter the same username and temporary application password in the form, then navigate normally through wp-admin and the frontend. Credentials are sent by HTTPS POST and never put in a URL.

Agents can use the command interface directly with HTTP Basic authentication, without setting up MCP:

- `GET /wp-json/guest-key/v1/help` returns command names only. Add `?command=post%20save` for a single schema, or `?command=post` for a filtered list. MCP help also accepts an array of command names to retrieve several schemas in one call (REST uses `command[]` query parameters).
- `POST /wp-json/guest-key/v1/run` accepts `{"commands":[{"command":"plugin install","input":{"slug":"example","activate":true}}]}`.
- The equivalent `?rest_route=` URLs work with plain permalinks. The copied bundle supplies the correct URLs. OPTIONS on either route publishes its argument definitions and the same input schema used by the corresponding ability.

The optional MCP endpoint is `/wp-json/guest-key/v1/mcp`. It exposes exactly two tools, `guest-key-help` and `guest-key-run`, with the same inputs as those REST endpoints. Refresh an existing MCP client's tool list after updating Guest Key. Core and plugin abilities remain discoverable and executable through `ability list`, `ability describe`, and `ability run`, including private abilities. Their native input validation and permission callbacks still apply; public REST/MCP metadata stays unchanged. No tool-list refresh is required when a new ability provider is activated: discovery reads the registry on each request.

| Commands | Purpose |
| --- | --- |
| `plugin list/install/activate/deactivate/delete` | Native plugin management; install can also activate |
| `theme install/activate/delete`, `update list/apply` | Native theme management and updates |
| `post describe/list/get/create/update/trash/restore/delete` | Registered post types, including those without REST exposure |
| `post save` | Create/update content with metadata, taxonomy assignments and a featured image |
| `post meta describe/list/get/add/update/delete` | Registered field discovery and authorized metadata operations |
| `term describe/list/get/create/update/delete/assign` | Registered taxonomies and existing term assignments |
| `option describe/get/update/delete` | Registered option schemas and exact-key native site options |
| `theme mod list/get/update/delete` | Native modifications on the active theme |
| `user meta describe/list/get/add/update/delete` | Authorized user fields, including non-REST registrations |
| `term meta describe/list/get/add/update/delete` | Authorized term fields, including non-REST taxonomies |
| `media upload` | Native media upload with title, caption and alt text |
| `file list/read/search` | Read-only browsing and source inspection under wp-content |
| `design guide` | Original design playbook; short overview and focused topic references |
| `rest request` | Any native core/plugin REST route; use OPTIONS for a route schema |
| `ability list/describe/run` | Focused discovery and execution of registered abilities |

Command names are exact strings, with arguments in a separate JSON `input` object. They are not shell strings or full WP-CLI emulation. Use command help for the actual input schemas; complex content and metadata do not require shell quoting. `post create/update` use `fields` (title, content, excerpt, slug, status, author, parent, menu_order, password, ISO 8601 date, comment_status, ping_status). Item operations use `id`; create/list use `post_type`. `post meta describe` can use `post_type` before any item exists, or `post_id` to also report per-key authorization. Metadata names and formats must be discovered rather than guessed; unregistered plugin fields may need inspection of an existing item's authorized metadata or its supported API.

Before creating or redesigning pages, run `{"commands":[{"command":"design guide"}]}` to load a short design workflow and topic index. Request a focused topic with `{"command":"design guide","input":{"section":"motion"}}`. Available sections are `overview`, `brief`, `system`, `layout`, `motion`, `images`, `wordpress`, and `review`; several can be loaded in one batch. The bundled [design playbook](guides/design/overview.md) covers visual direction, typography, color, layout, motion, authentic portfolio imagery, editable WordPress implementation and rendered-page verification. It is original guidance, independent of any theme or builder, and does not perform an automated audit or change the site. Agent and browser capabilities determine what can actually be verified. It adds no MCP tools or external service dependency, and its full text is loaded only when requested.

A batch contains 1–20 commands, executes sequentially, and stops on the first error. Always inspect `ok`. On failure, `results` contains completed commands, `failed_index` identifies the zero-based failed command, `error` contains its code/message/data, and `skipped` counts unexecuted commands. Earlier writes remain; batches are not transactions. Never blindly retry an entire write batch. A command input value `{"$ref":"0.data.id"}` refers to the first command's returned ID, so an upload or newly-created term can be used by later commands without another request. Only completed results can be referenced; references replace whole values, not substrings. The single-property `$ref` object is reserved for these references within command input.

For example, after discovering the actual post type, metadata keys, and taxonomy, upload an image and create a portfolio item in one call to `guest-key-run` or POST `/guest-key/v1/run`:

```json
{
  "commands": [
    {
      "command": "media upload",
      "input": {"filename": "project.jpg", "base64": "<file bytes>", "fields": {"alt_text": "Project exterior"}}
    },
    {
      "command": "post save",
      "input": {
        "post_type": "portfolio",
        "fields": {"title": "Downtown", "content": "<!-- wp:paragraph --><p>Our latest project.</p><!-- /wp:paragraph -->"},
        "meta": {"project_year": 2026},
        "terms": {"portfolio_category": [7]},
        "featured_media": {"$ref": "0.data.id"}
      }
    }
  ]
}
```

`post save` without `id` creates a draft by default; with `id` it updates that post and permits metadata-only updates. `meta` replaces each supplied key's stored values, retaining registered types, sanitizers, readonly declarations, and native per-key authorization. `terms` replaces assignments only for the named taxonomies and requires existing integer IDs; an empty array clears them. `featured_media` uses an existing image attachment, or 0 clears it, and requires thumbnail support. Known schema, taxonomy, and image failures are checked before writing. New-post metadata permissions can only be checked once the item has an ID; a later error reports `error.data.post_id` and `completed` steps so the caller can repair that item. No rollback is promised for plugin hooks or external side effects. Write responses return compact IDs/statuses instead of echoing large content. Post lists return IDs, titles, slugs, statuses and types with pagination; plugin lists return identifiers, names, statuses and versions. `post get` retrieves full content when needed.

For broader APIs, `rest request` accepts `path`, `method`, and `parameters`. Paths omit `/wp-json` and query strings; query/body fields go in `parameters`. GET/OPTIONS arguments use query parameters; write arguments use body parameters. The inner response passes through WordPress’s normal post-dispatch filters, including nested `_fields` selection such as `id,title.raw`. Pagination totals are retained. Application-password and Guest Key transport routes are excluded. Native REST endpoints retain their own validation and permissions. Use `ability list` with `search` and `page` to retrieve at most 50 names/labels; `ability describe` with `name` returns that ability's schema, description, and metadata; `ability run` takes `name` and its native `input`, which may be omitted for an ability without an input schema.

`option describe` reports settings registered in the current request without dumping stored values. `option get/update/delete` use an exact ASCII `key` without whitespace; update requires `value` and preserves existing autoload policy (new options do not autoload). Registered types, REST field schemas, native sanitizers and option-group capabilities apply. Unregistered options require `manage_options`. Network options are not exposed. Lifecycle and access-policy options such as plugin lists, theme selection, cron, roles, registration policy, site URLs and Guest Key records are excluded; use their dedicated APIs instead of directly changing storage.

`theme mod list` returns stored key names for the active theme; other actions take `key` and update requires `value`. Native theme-mod filters and `edit_theme_options` apply. These APIs do not run Customizer-only validation or imply builder CSS regeneration: inspect the theme’s supported values and use its save pipeline when necessary. `user meta` requires `user_id`; `term meta` requires `term_id`. Describe reports registered fields and authorization, list returns authorized stored key names, and get returns all values for one key. Add/update/delete match post-meta semantics, including schemas, readonly declarations, sanitizers, uniqueness and exact-value deletion. Native object and per-key capabilities apply. The new data commands accept ASCII key names without whitespace, preventing database collation aliases to protected records. Case variants use the registered key for schema and permission checks. Authentication passwords, session tokens, user capabilities/levels and Guest Key records cannot be read or changed through these data commands.

Source inspection is available through the same commands and three direct, authenticated GET endpoints. All paths are relative to `wp-content`; omit `path` to list or search its root. Use OPTIONS on each endpoint for its input schema.

| REST endpoint | Example query | Result |
| --- | --- | --- |
| `/guest-key/v1/files` | `path=plugins/example&recursive=true&search=.php` | Paths, entry types, readability and file sizes |
| `/guest-key/v1/files/read` | `path=plugins/example/main.php&offset=0&length=32768` | Source text, first line number and continuation byte offset |
| `/guest-key/v1/files/search` | `path=plugins/example&query=register_post_meta&filename=*.php` | Matching paths, line numbers, byte offsets and short text excerpts |

These routes require an active Guest Key administrator credential, respect expiry and demotion, and return non-cacheable responses. They have no write methods and never execute the files they read. Canonical paths must remain inside wp-content, including symlink targets. Recursive listing and search do not follow symlinks; a direct read can follow an internal symlink. Known credential filenames (`.env`, `.env.*`, `.htpasswd`, `wp-config.php` and its suffix variants) and version-control directories are excluded, including aliases to them.

Reads support UTF-8 text files up to 2 MiB, returning 32 KiB by default and at most 64 KiB per response. Use the returned `next_offset` to continue; chunks preserve complete UTF-8 characters. Searches are literal, ASCII case-insensitive by default, with optional `case_sensitive=true` and a basename glob in `filename`. Search returns one match per line, at most 100 per response; listings return at most 200 entries. Listing `offset` counts matching entries, search `offset` counts matching lines, and read `offset` counts bytes. A search match's `line_offset` can feed a later `file read` through a batch result reference.

Scans are bounded to 5,000 entries, directory depth 20, approximately three seconds, and 16 MiB of file data for search. Check `complete`, `scan.limited`, and `scan.skipped` before treating an empty search as evidence. Binary/non-UTF-8 files and excluded paths are outside the search scope; oversized, unreadable, symlinked or depth-limited content is reported as omitted. Use `next_offset` after a result limit. After a time, byte or entry limit, narrow `path` or `filename`; filesystem changes can affect pagination. Inspection helps discover hooks, field formats and save behavior, while writes still go through supported WordPress APIs.

The underlying private abilities remain registered in individual files under `abilities/`, with shared implementations under `includes/`. The backend inventory still lists their source files and schemas alongside third-party abilities. Existing grouped `manage-*` helpers remain callable through `ability run`. Native content operations fire normal PHP save hooks; they do not execute admin JavaScript or form-specific workflows, so plugin-owned derived fields may require the plugin's supported API or browser UI.

Native REST controllers preserve input validation, object-specific permissions, and compatibility checks. Administrators are still subject to their account's capabilities and host policies. Multisite requires a super administrator; ordinary changes target the credential's issuing subsite, and plugin activation supports an explicit `network_wide` flag. Theme activation respects network theme availability. The same temporary credential also authenticates directly to ordinary core and plugin REST endpoints. Each endpoint retains its native permission checks and validation. Temporary API requests cannot create additional application passwords or directly edit PHP/theme/plugin files.

Plugin/theme installation accepts WordPress.org slugs, without overwriting installed packages or accepting arbitrary ZIP URLs. Activation uses installed plugin identifiers (with or without `.php`) or theme stylesheet names. Updates use native cached update offers and upgraders; `list-updates` with `refresh=true` refreshes offers first. An optional `version` pins a plugin/theme update; core updates require `target=wordpress` and an explicit `version`. Hosts requiring interactive filesystem credentials must configure those through WordPress before using package operations.

`upload-media` accepts `filename`, base64 file bytes, and optional native attachment `fields`. Upload limits and WordPress's allowed file types apply. It cannot write to arbitrary paths or upload PHP files. Templates, reusable blocks, global styles, and navigation are edited through their database-backed WordPress APIs. There is no direct PHP/theme/plugin file editor, filesystem-write, SQL, or shell-execution ability. Plugin-owned workflows and derived fields may require their native REST registrations or a separate ability provider. Specialist abilities such as Ollie's block/pattern tools are automatically discoverable through ability list when their provider is active.

**Tools → Guest Key** opens with the create/copy action, expiry and revocation. Paste the copied details into your local agent. Connection details and the technical inventory are collapsed by default. The inventory is a compact, searchable list of entry names and sources, with its height limited to 240px. Click an entry for labels, descriptions, categories, endpoints, schemas, and metadata. Source attribution uses the registration call stack, with a clearly marked execution-callback fallback for earlier registrations. Unknown sources remain unknown. The page initializes REST integrations to include abilities registered by those integrations. It also reads the official MCP Adapter’s shared server registry, showing registered endpoints and each server’s tool, resource, and prompt definitions in a modal. The inventory updates automatically as providers register or unregister; it contains no plugin-specific catalogs, disabled private registries, or guesses based on route names. Independent MCP implementations without a shared registry are not discoverable this way. A registered server’s implementation source is shown when the adapter does not identify its registering plugin. Other servers retain their provider-specific permissions and may require additional authentication; a server that accepts native WordPress application passwords can use the same temporary credential. Search covers all displayed inventory entries and their schemas. The page offers access management, without tool selection.

Only administrators (`manage_options`) see or issue access on single sites. Only super administrators do so on multisite. A user's one Guest Key credential is bound to the issuing subsite; issuing on another subsite replaces it. Creation/revocation requires a browser cookie session and nonce. The temporary application password follows WordPress’s normal native API authentication, including standard core/plugin REST endpoints, and also supports the dedicated Guest Key browser sign-in page. Ordinary WordPress login forms remain unchanged; application passwords are API credentials, not normal account passwords. Browser sign-in creates a separate native WordPress cookie session marked with the key UUID and issuing site. Temporary browser sessions and authenticated Guest Key API requests cannot issue replacement Guest Keys or application passwords and cannot use the theme/plugin file editors. Normal admin actions such as media uploads and plugin installation remain available. Native session replacements inherit the key binding; expiry, key rotation, password deletion, demotion, and revocation end guest browser access. Rotation/revocation/deactivation also remove the native guest session tokens, preserving normal owner sessions. Multisite browser sessions remain confined to their issuing subsite. Browser access currently requires WordPress's native session storage; custom session storage remains compatible with REST and MCP access.

Expiration, key identity, and issuing site/network are checked during every native authentication, independent of WP-Cron. Administrator authorization runs after WordPress establishes the current user, with an additional REST authentication check and the existing MCP permission gate, so capability filters that resolve that user (including Yoast SEO’s) cannot restart authentication. Native API capability restrictions identify the credential from WordPress’s successful application-password authentication hook, without resolving the current user inside capability filters. Demoted administrators cannot connect. Scheduled cleanup deletes the expired password. A late event for a replaced key cannot revoke its replacement. Explicit revocation is available on the page, in the toolbar submenu, and in the success notice. Deactivation revokes Guest Key's tracked credentials.

Command integration checks run with `wp --path=/path/to/local/site --user=admin eval-file /path/to/guest-key/tests/commands.php`. They verify a non-REST portfolio workflow with image, metadata, terms and result references, bounded batches, partial failures, native permissions, and actual HTTP command authentication. Temporary content, uploads, and credentials are removed afterward.

Native data checks run with `wp --path=/path/to/local/site --user=admin eval-file /path/to/guest-key/tests/data.php`. They exercise options, active-theme modifications, user/term metadata, registered validation and sanitizers, capabilities, autoload policy, literal JSON values and protected records. REST dispatch checks use the same command with `tests/dispatch.php`; they verify GET/OPTIONS parameters, nested field selection, plugin search, and REST schema discovery.

Settings-page checks use the same command with `tests/admin-page.php`. They verify collapsed details, access scope and handoff instructions, active/expired/site-bound status, revocation visibility and disabled application-password handling without persistent fixtures.

Read-only filesystem checks use the same command with `tests/files.php` on a local/development single site with its web server running. They verify actual authenticated GET/OPTIONS requests, query booleans, path confinement, symlinks, excluded files, non-execution, UTF-8 chunking, literal search, scan budgets and expiry/demotion. Temporary directories and credentials are removed afterward; existing grants are preserved.

To reproduce an unavailable adapter without touching the installed plugins, run `tests/native-access.php` with an isolated CLI bootstrap on a local single site with its web server running:

```sh
guest_key_test_dir="$(mktemp -d)"
mkdir "$guest_key_test_dir/plugins"
cat > "$guest_key_test_dir/bootstrap.php" <<PHP
<?php
define('WP_PLUGIN_DIR', '$guest_key_test_dir/plugins');
define('DISALLOW_FILE_MODS', true);
PHP
wp --path=/path/to/local/site --require="$guest_key_test_dir/bootstrap.php" --skip-plugins --user=admin eval-file /path/to/guest-key/tests/native-access.php
rm "$guest_key_test_dir/bootstrap.php"
rmdir "$guest_key_test_dir/plugins" "$guest_key_test_dir"
```

The isolated process loads Guest Key explicitly, issues a temporary native credential despite installation failure, verifies REST over HTTP and browser session creation without an adapter, and removes its user, key and session. Existing grants and dependency notices are restored.

Content integration checks run with `wp --path=/path/to/local/site --user=admin eval-file /path/to/guest-key/tests/content.php`. They register in-process REST/non-REST custom type and taxonomy fixtures, verify content/term/meta operations and denied permissions, and remove temporary posts and terms afterward. They preserve existing Guest Key grants.

For MCP authentication and discovery checks, activate the official MCP Adapter before starting WP-CLI so its registry initializes during bootstrap. Native access without an adapter is tested separately with the isolated bootstrap above.

Read-only discovery checks run with `wp --path=/path/to/local/site eval-file /path/to/guest-key/tests/discovery.php`. They use in-process registry fixtures to verify automatic provider discovery, schemas, removal, and preservation of settings and grants, without executing tools or making HTTP requests.

Native REST integration checks run with `wp --path=/path/to/local/site --user=admin eval-file /path/to/guest-key/tests/rest.php` while the web server is running. They verify real HTTP Basic authentication, API/schema discovery, native post/meta writes, validation, denied application-password creation, expiry, demotion, rotation, and revocation. A temporary administrator and draft are removed afterward; existing grants and adapter state are preserved.

Browser integration checks run against a local single site with its web server running: `wp --path=/path/to/local/site eval-file /path/to/guest-key/tests/browser.php`. They use a temporary administrator and check frontend/backend cookies, expiry, rotation, revocation, capability restrictions, and preservation of normal sessions.

Multisite checks run with `wp --path=/path/to/local/network --user=admin eval-file /path/to/guest-key/tests/multisite-http.php` on a local/development subdirectory network with Guest Key network-active. They verify super-administrator requirements, issuing-site REST and browser boundaries, cross-site key replacement, site-local data writes, source inspection, expiry and revocation. Temporary users and a subsite are removed; existing grants and adapter activation are preserved.

The authentication regression check can run with the web server stopped: `wp --path=/path/to/local/site eval-file /path/to/guest-key/tests/authentication.php`. It uses a temporary administrator, exercises native application-password authentication and MCP REST dispatch, checks capability-filter re-entry and access restrictions, and preserves existing grants. It requires a local/development single site; an early re-entry guard fails safely instead of exhausting memory.

Guest Key disables the adapter after its last active grant ends only when Guest Key activated it. It leaves already-active, independently managed adapters alone. While Guest Key manages the adapter, its default MCP endpoint is suppressed; otherwise existing default-server configuration stays unchanged. WP-Cron cleanup and physical adapter deactivation may run after the expiry time on quiet sites; expired credentials still fail immediately. Normal WordPress updates remain the site's responsibility.

The plaintext password is returned once over the authenticated creation response and is never saved by Guest Key. WordPress stores its password hash; Guest Key stores only the UUID, expiry, site, and network. Credentials already copied to the clipboard are not cleared on revocation, but are no longer usable.

The copied bundle instructs agents to identify all programmatic HTTP and MCP requests with `User-Agent: <client>/<version> (user-directed AI agent; agent=<agent>; model=<model>)`. It includes this example:

```http
User-Agent: T3Code/0.0.46-nightly.20261005.2689 (user-directed AI agent; agent=Codex; model=gpt-6.1-sol)
```

Agents use their actual client/version, agent, and model, omitting unknown fields or `/version`. In T3 Code, the instructions point to `t3_environment_read` and `t3_thread_configuration`, with authoritative runtime information as a fallback. Agents retain the phrase `user-directed AI agent`, reuse their identifier for discovery, retries, and diagnostics, and configure MCP transport and downstream HTTP headers separately where supported. Unsupported header controls are reported honestly. The header identifies traffic and is not an authentication requirement. Interactive browser user agents and outgoing WordPress update checks remain unchanged. REST appears first; the browser sign-in form and MCP endpoint use the same credentials.

Updates use the same static JSON model as [Recently Edited Quick Links](https://github.com/jonschr/elodin-recently-edited): the bundled [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) v5.7 reads `https://raw.githubusercontent.com/jonschr/guest-key/master/update.json`, checks periodically (every 12 hours by default), and supplies WordPress's normal update notices and one-click installation. The Plugins page also offers **Check for updates**. The checker loads on every request, including cron, AJAX, REST, and WP-CLI. Its MIT license is included in `vendor/plugin-update-checker/license.txt`. The `Update URI` header prevents WordPress.org from supplying an unrelated plugin with the same slug.

Releases use matching GitHub tags and a public update feed on the master branch. The checked-in `update.json` describes version 0.1.3 and points to its GitHub tag archive. If the repository uses a different name or branch, change the feed URL in `guest-key.php` and the archive URL in `update.json`. A site can override the feed by defining `GUEST_KEY_UPDATE_URL` in `wp-config.php` before WordPress loads plugins.

To release an update:

1. Set the same new version in the plugin header, `GUEST_KEY_VERSION`, and `update.json`. Update the JSON changelog and requirements as needed.
2. Commit the complete plugin, including `vendor/plugin-update-checker`, and publish a matching tag such as `0.1.3`. Release archives exclude development tests through `.gitattributes` and retain the runtime guides and bundled updater. The archive must contain `guest-key.php` directly inside its top-level directory; the checker normalizes GitHub's versioned directory name when installing.
3. Confirm the tag ZIP is publicly downloadable, then publish `update.json` to the feed branch with `download_url` pointing to that tag. Publish the archive before advertising its version.
4. Use **Check for updates** on a staging site's Plugins page and install through WordPress. Site owners retain control of automatic updates through WordPress's normal controls.

Like any administrator credential, this grants powerful capabilities through registered abilities. Six-hour expiry governs future authenticated requests, not the lifetime of changes already made or operations already running. Plugins that permit creating other credentials or changing access policies can grant additional access; expiry is not a sandbox against a deliberately hostile administrator.
