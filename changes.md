# Changes

## 0.2.5 — 2026-10-29

- Licenses and trials belong to the activating administrator. Owners can share paid access with selected administrators, and separate licenses can coexist on one site.
- Migrate existing licenses by purchase/trial email where possible; offer an explicit claim when no administrator matches.
- Read every stored metadata field on editable posts. Allow unregistered underscore custom-field writes while retaining native field authorization, readonly schemas, validation and sanitization.
- Resume file listing and search after result, byte, entry or time limits using signed cursors. Add directory-depth controls and clearer pagination/parameter help.
- Include valid editable post types in unknown-type errors and retain cross-type searches with post_type any.
- Use tool-neutral connection instructions, a read-only JSON example and an optional “[site name] edits” thread title. Align the copy icon with its button label.
- Fix stale-license refresh during key creation and preserve wrong-site credential errors before checking licensing.

**Upgrade:** Existing licenses are assigned to one administrator, not automatically shared with every administrator. Visit Guest Key settings after updating to confirm ownership or claim an unmatched license, then select administrators who should share access. Older plugin versions cannot read the new account-scoped license storage; restore the pre-upgrade database and plugin together if a rollback is needed.

## 0.2.4

- Start a three-day trial after email verification; existing trials keep their expiration.
- Put licensing first in a compact two-column settings layout, with a highlighted setup card, side-by-side key and email entry, and a larger technical inventory.
- Use visible license-key entry for TextExpander. Saved licenses show status and actions instead of a key field until removed.
- Check for matching purchases automatically in the background, including toolbar hover, without a separate purchase-check button.
- Show access expiration in the browser’s local time zone, with clearer Claude and Codex paste instructions.
- Choose access duration with a readable hours/minutes label and a 15-minute slider. Keep the 30-minute to 24-hour bounds and two-hour default.
- Use the current WordPress accent on hover with white text and animated copy feedback.
- Support post_type any for cross-type searches, retain authorization restrictions, and include registered post type names in command help.

## 0.2.3

- Add Show key and Hide key controls to license entry, so TextExpander can be used while the key is visible.
- Keep password entry as the default. Replace the input when toggling to avoid Chrome retaining Secure Input on a revealed password field.
- Disable autocomplete and text corrections for license keys; preserve separate six-digit email-code validation.

## 0.2.2

- Simplify the Copy Guest Key dropdown to a native Settings link; keep one-click copying on the toolbar action.
- Move license entry, status checks, removal and billing into the settings page.
- Close and clear the setup panel after activation. Label licensed settings License, without trial wording or an active-license renewal prompt.
- Preserve trial onboarding, expired-trial activation and keyboard navigation.

## 0.2.1

- Clarify the two-hour default in the plugin description and version-details description. Access duration remains configurable from 30 minutes to 24 hours.

## 0.2.0

- Start a free 24-hour trial after verifying your email with a six-digit code.
- Automatically activate purchases made with the verified trial email, including after trial expiry; retain manual license entry and removal.
- Manage setup, licensing and connection feedback in the Copy Guest Key toolbar dropdown, with a 24-hour dismissal.
- Choose an access duration from 30 minutes to 24 hours; the default remains two hours, existing keys retain their expiry, and trial/license expiry caps all access.
- Enforce licensing and temporary-session boundaries without exposing license keys or tokens to agents or browser UI state.

## 0.1.5

- Fix MCP dependency installation, theme installation and update error handling by using WordPress’s AJAX upgrader skin.
- Add administrator-only copyable diagnostics under Tools → Guest Key, with runtime versions, adapter status and filesystem policy.
- Retain the most recent MCP setup failure with its UTC time and error code after recovery; redact URLs, paths, authorization headers and application passwords in diagnostic reports.
- Keep static JSON updates and native REST/browser access available independently of MCP setup.

## 0.1.4

- Require the administrator role on the current site as well as manage_options for all Guest Key UI, credential creation, abilities, REST/MCP access and browser sessions.
- Hide Guest Key menus, toolbar controls, notices and assets from other roles, including custom roles with admin-like capabilities.
- End existing temporary-key access immediately when the administrator role is removed, even if capabilities remain.
- On multisite, require the administrator role on the issuing subsite in addition to super-administrator status.
- Add regression coverage for role boundaries, valid-session AJAX denial and REST/MCP/browser access after role removal.

## 0.1.3

- Reduce the technical inventory to a compact searchable list with a 240px height limit; open full descriptions, endpoints and schemas on demand.
- Include agent-identifying User-Agent instructions and a T3 Code example in copied connection details, with guidance for resolving actual client/version, agent and model.
- Preserve interactive browser headers and explain unknown metadata and unsupported header controls.

## 0.1.2

- Simplify the connection page around creating, copying and revoking six-hour access; collapse connection details and the searchable technical inventory.
- Rename the toolbar action to Connect agent and explain how to paste connection details into a local agent.
- Explain unavailable application passwords, hide inactive revocation actions and preserve accessible status updates.
- Resolve lazy REST authentication before WordPress checks application-password errors, including expired credentials on public routes.
- Fix narrow-screen inventory overflow and verify fresh installation, native workflows and multisite credential boundaries.

## 0.1.1

- Compact command help and execution over native REST and two MCP tools, with focused schemas and automatic provider ability discovery.
- Run up to 20 sequential commands with references to earlier results; combine post content, metadata, terms and featured images in one operation, with explicit partial-failure reporting.
- Preserve GET/OPTIONS parameters and nested REST field filtering; publish command schemas through native OPTIONS discovery.
- Keep native REST and browser access available when MCP setup fails, with explicit MCP readiness reporting.
- Add structured native site options, active-theme modifications, user metadata and term metadata with registered validation and protected access records.
- Load original design guidance on demand: visual direction, typography, color, layout, motion, imagery, editable WordPress implementation and rendered-page review.
- Add read-only wp-content browsing, chunked source reads and literal text search over authenticated REST and commands, with canonical path checks and bounded scans.
- Copy direct command URLs alongside the native REST credentials, browser sign-in and optional MCP endpoint.

## 0.1.0

- Native WordPress REST application-password access with credential rotation and six-hour expiry; MCP and browser sign-in remain additional options.
- Automatic official MCP Adapter setup and native API restrictions on direct file editing and additional application-password creation.
- 26 admin abilities for content, accounts, settings, media, navigation, templates, plugins, themes, and updates, including native custom post type, taxonomy, and post-meta operations without REST exposure or direct file-editing tools.
- Browser sign-in using the same temporary credential, with native sessions bound to key expiry and revocation.
- Automatic registered abilities and MCP Adapter server inventory with source attribution, schema search, and modal inspection.
