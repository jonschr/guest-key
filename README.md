# Guest Key

Install and activate this directory as a WordPress plugin. Requires WordPress 6.9+, PHP 7.4+, HTTPS, and application passwords enabled for the administrator.

Activation installs and activates the official [WordPress MCP Adapter](https://wordpress.org/plugins/mcp-adapter/) through WordPress.org's plugin API and the native WordPress installer. An existing adapter must be version 0.7.0 or newer. Hosts that restrict plugin installation receive a setup notice; install the adapter manually and retry creating access. The adapter is managed in code rather than through a `Requires Plugins` header, since WordPress's declarative dependency handling requires pre-activation setup and prevents the normal temporary disable/re-enable workflow.

Click **MCP access** in the WordPress toolbar, in the dashboard or on the frontend, to create and copy a connection bundle. Guest Key creates a native application password for the current administrator, replaces only passwords carrying Guest Key's application ID, and expires the credential after six hours. Clipboard permission failures show a dialog with an explicit copy button. The bundle contains the MCP endpoint, username, password, and expiration. The agent must support Streamable HTTP with HTTP Basic authentication (or a compatible local proxy).

The endpoint is `/wp-json/guest-key/v1/mcp` (or the equivalent `?rest_route=` URL when using plain permalinks). Every registered WordPress ability is exposed directly as a tool, including private abilities. Existing ability input validation and permission callbacks remain in force; public REST/MCP exposure metadata is never changed. MCP resource/prompt annotations do not prevent an ability from being callable as a Guest Key tool. Installing the adapter does not itself register administrative operations.

Guest Key ships 23 private admin abilities, each registered in a separate file under `abilities/`. Shared helpers in `includes/` handle native requests and package operations. The backend inventory lists these alongside core and third-party abilities; **Inspect ability** opens a modal with its source file, schemas, and metadata. MCP tool names replace the namespace slash with a hyphen (e.g. `guest-key-manage-content`).

| Area | Abilities |
| --- | --- |
| Content and taxonomy | `manage-content`, `manage-taxonomies`, `manage-comments` |
| Accounts and site configuration | `manage-users`, `manage-settings` |
| Media | `manage-media`, `upload-media` |
| Site Editor and layout | `manage-navigation`, `manage-widgets`, `manage-templates`, `manage-appearance` |
| Plugins | `list-plugins`, `install-plugin`, `activate-plugin`, `deactivate-plugin`, `delete-plugin` |
| Themes | `install-theme`, `activate-theme`, `delete-theme` (list themes through `manage-appearance`) |
| Updates | `list-updates`, `apply-update` for plugins, themes, and WordPress core |
| API discovery | `get-admin-api`, `read-admin-info` |

Grouped `manage-*` abilities accept a native REST `path`, an HTTP `method` (default `GET`), and a `parameters` object. `get-admin-api` lists supported routes and their field schemas, optionally filtered by ability or search text. Content and taxonomy routes include REST-enabled custom types. Responses include `status`, `data`, and pagination totals when supplied by WordPress. For example, call `guest-key-manage-content` with `{"method":"POST","path":"/wp/v2/pages","parameters":{"title":"About","status":"draft","content":"<!-- wp:paragraph --><p>About us.</p><!-- /wp:paragraph -->"}}` to create a draft page.

Native REST controllers preserve input validation, object-specific permissions, and compatibility checks. Administrators are still subject to their account's capabilities and host policies. Multisite requires a super administrator; ordinary changes target the credential's issuing subsite, and plugin activation supports an explicit `network_wide` flag. Theme activation respects network theme availability. These abilities do not make the credential usable directly on core REST endpoints, and application-password routes remain excluded.

Plugin/theme installation accepts WordPress.org slugs, without overwriting installed packages or accepting arbitrary ZIP URLs. Activation uses installed plugin identifiers (with or without `.php`) or theme stylesheet names. Updates use native cached update offers and upgraders; `list-updates` with `refresh=true` refreshes offers first. An optional `version` pins a plugin/theme update; core updates require `target=wordpress` and an explicit `version`. Hosts requiring interactive filesystem credentials must configure those through WordPress before using package operations.

`upload-media` accepts `filename`, base64 file bytes, and optional native attachment `fields`. Upload limits and WordPress's allowed file types apply. It cannot write to arbitrary paths or upload PHP files. Templates, reusable blocks, global styles, and navigation are edited through their database-backed WordPress APIs. There is no direct PHP/theme/plugin file editor, filesystem-write, SQL, or shell-execution ability. Plugin settings and metadata need to be exposed through their native REST registrations or a separate ability provider. Specialist abilities such as Ollie's block/pattern tools are automatically included when their provider is active. Refresh the agent's tool list after activating an ability provider.

**Tools → Guest Key** lists registered abilities, their labels, descriptions, categories, source plugins/themes/core, schemas, and metadata. Source attribution uses the registration call stack, with a clearly marked execution-callback fallback for earlier registrations. Unknown sources remain unknown. The page initializes REST integrations to include abilities registered by those integrations. It offers search and access management, without tool selection.

Only administrators (`manage_options`) see or issue access on single sites. Only super administrators do so on multisite. A user's one Guest Key credential is bound to the issuing subsite; issuing on another subsite replaces it. Creation/revocation requires a browser cookie session and nonce. Agent credentials are restricted to this site's Guest Key endpoint and cannot call WordPress's password-creation REST endpoints.

Expiration and site/endpoint restrictions are checked during every authentication, independent of WP-Cron. Administrator permissions are checked at the MCP permission gate after WordPress establishes the current user, so capability filters that resolve that user (including Yoast SEO's) cannot restart authentication. Demoted administrators cannot connect. Scheduled cleanup deletes the expired password. A late event for a replaced key cannot revoke its replacement. Explicit revocation is available on the page, in the toolbar submenu, and in the success notice. Deactivation revokes Guest Key's tracked credentials.

The authentication regression check can run with the web server stopped: `wp --path=/path/to/local/site eval-file /path/to/guest-key/tests/authentication.php`. It uses a temporary administrator, exercises native application-password authentication and MCP REST dispatch, checks capability-filter re-entry and access restrictions, and preserves existing grants. It requires a local/development single site; an early re-entry guard fails safely instead of exhausting memory.

Guest Key disables the adapter after its last active grant ends only when Guest Key activated it. It leaves already-active, independently managed adapters alone. While Guest Key manages the adapter, its default MCP endpoint is suppressed; otherwise existing default-server configuration stays unchanged. WP-Cron cleanup and physical adapter deactivation may run after the expiry time on quiet sites; expired credentials still fail immediately. Normal WordPress updates remain the site's responsibility.

The plaintext password is returned once over the authenticated creation response and is never saved by Guest Key. WordPress stores its password hash; Guest Key stores only the UUID, expiry, site, and network. Credentials already copied to the clipboard are not cleared on revocation, but are no longer usable.

The copied bundle asks agents to identify programmatic MCP requests with `User-Agent: <client>/<version> (user-directed AI agent; agent=<agent>; model=<model>) GuestKey/<plugin-version> WordPress/<wordpress-version>`. Guest Key fills in its own version and the site's current WordPress version at creation time; the agent supplies its actual client and model, omitting unknown fields. This follows the browsing skill's identification convention while keeping interactive browser user agents unchanged. The header is a client instruction, not an authentication requirement; clients that cannot configure HTTP headers should report that limitation. Outgoing update checks retain their existing user agent.

Updates use the same static JSON model as [Recently Edited Quick Links](https://github.com/jonschr/elodin-recently-edited): the bundled [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) v5.7 reads `https://raw.githubusercontent.com/jonschr/guest-key/master/update.json`, checks periodically (every 12 hours by default), and supplies WordPress's normal update notices and one-click installation. The Plugins page also offers **Check for updates**. The checker loads on every request, including cron, AJAX, REST, and WP-CLI. Its MIT license is included in `vendor/plugin-update-checker/license.txt`. The `Update URI` header prevents WordPress.org from supplying an unrelated plugin with the same slug.

The GitHub repository, public feed, and version tag must be published before remote updates can work. The checked-in `update.json` describes version 0.1.0 and points to its GitHub tag archive. If the repository uses a different name or branch, change the feed URL in `guest-key.php` and the archive URL in `update.json`. A site can override the feed by defining `GUEST_KEY_UPDATE_URL` in `wp-config.php` before WordPress loads plugins.

To release an update:

1. Set the same new version in the plugin header, `GUEST_KEY_VERSION`, and `update.json`. Update the JSON changelog and requirements as needed.
2. Commit the complete plugin, including `vendor/plugin-update-checker`, and publish a matching tag such as `0.1.1`. The archive must contain `guest-key.php` directly inside its top-level directory; the checker normalizes GitHub's versioned directory name when installing.
3. Confirm the tag ZIP is publicly downloadable, then publish `update.json` to the feed branch with `download_url` pointing to that tag. Publish the archive before advertising its version.
4. Use **Check for updates** on a staging site's Plugins page and install through WordPress. Site owners retain control of automatic updates through WordPress's normal controls.

Like any administrator credential, this grants powerful capabilities through registered abilities. Six-hour expiry governs future authenticated requests, not the lifetime of changes already made or operations already running. Plugins that permit creating other credentials or changing access policies can grant additional access; expiry is not a sandbox against a deliberately hostile administrator.
