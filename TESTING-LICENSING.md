# 0.2.11 release validation — 2026-11-17

The final source passed 28 JavaScript and 265 PHP checks: visibility/restricted settings 36, ownership/sharing 31, licensing 29, settings 22, roles 44, commands 58, diagnostics 13 and authentication 32. Coverage includes the 1.5-second debounce, mutually exclusive radio choices, hidden-to-shared save ordering, restricted-page contents and handler denial, own-license activation, purchased-owner protection, last-saved shared access, legacy sharing, credential revocation and Block Runner guide delivery. Test users, content, credentials and global options were cleaned up or restored. The authentication suite used temporary native adapter activation and restored its prior state afterward.

All 135 PHP files passed syntax checks; JavaScript syntax and diff whitespace checks passed. Four unused localized strings were removed. Customer identifiers and internal operational details were removed from this public validation record. Older release notes remain intact. No new browser, Safari, older-WordPress or customer-site verification was performed for this release.

# Mutually exclusive administrator access choices — development, 2026-11-17

Sharing and hiding use one native radio group per administrator. A hidden administrator can switch directly to shared access; the 1.5-second debounced save unhides them before submitting the shared selection. Purchased-license owners remain locked to enabled access. Existing visible/unshared administrators remain unselected until an owner makes a choice, preserving existing access rather than silently hiding or granting it. All 28 JavaScript checks passed, including independent radio grouping and hidden-to-shared request ordering. PHP/JavaScript syntax and whitespace checks passed. This change has not been browser-verified or released.

# Shared owner controls and restricted settings — development, 2026-11-17

Hidden administrators without their own site-bound paid license receive only the contact/purchase/license-activation page. The contact is the WordPress account email of the last license owner to manage that user, with another current owner as fallback for legacy or removed-owner records. Trial entitlement does not override hiding. Purchased-license owners always retain toolbar and full settings access, including renewal; other owners cannot hide them. Their individual licenses remain separate. Owners now manage one site-wide shared access list, with the last saved selection applying. Before the first save, legacy per-owner shares remain effective and their combined selection appears in settings.

Passed 26 JavaScript, 36 visibility/restricted-settings, 31 ownership/sharing, 29 licensing, 22 settings-page, 44 role and 32 authentication checks. Checks cover restricted page contents and direct handler denial, hidden-user own-license activation with mocked provider responses, paid-owner protection, existing-trial denial, failed activation, last-owner-save behavior, existing credential revocation and legacy sharing. Authentication initially stopped because the local adapter was inactive; temporary native activation allowed all 32 checks to pass, then its previous activation/ownership state was restored. Native test users/content and global sharing/contact fixtures were cleaned up, with original options/grants preserved. No browser verification or customer-site update was performed for this change.

# 0.2.10 release validation — 2026-11-17

On the Cove main development site, shared-browser verification confirmed real clipboard contents exactly match the displayed diagnostics, with zero diagnostic AJAX requests. Browser tests using the actual admin script and an in-memory paid-license/AJAX fixture confirmed 500 ms debouncing, one sharing request for rapid multi-user selections, zero requests for reversed visibility toggles, preserved focus, hidden-user sharing restrictions and failed-save restoration. The fixture did not persist licenses or administrator changes, and the test tab was closed.

All 23 JavaScript checks, 22 native settings-page checks and 13 native diagnostics checks passed. Earlier in this task, eight isolated installer checks and a real native installation of the official MCP Adapter 0.7.1 ZIP passed. Existing grants and saved diagnostics were preserved/restored by the native suites. No customer site was updated; older WordPress versions and Safari were not tested.

# MCP Adapter installation — development, 2026-11-17

Verified the official WordPress.org listing and live plugin API report MCP Adapter 0.7.1, requiring WordPress 6.9 and PHP 7.4. The existing metadata lookup returned a valid versioned URL locally, so the exact response behind reported customer-site failures was not reproduced. Guest Key now supplies WordPress.org’s canonical `https://downloads.wordpress.org/plugin/mcp-adapter.zip` directly to the native installer, removing the metadata lookup and versioned-filename-only rejection. Existing installed-version checks and activation/ownership behavior remain unchanged.

Eight isolated installer checks passed, covering filesystem credentials, the fixed official download source, independence from plugin-info metadata, download failure details and package-operation error handling. A real download and native installation into a temporary plugin directory on WordPress 7.1.4 succeeded; its headers identified MCP Adapter 0.7.1 / WordPress 6.9 / PHP 7.4. The downloaded adapter was not activated. Temporary package files were removed. PHP syntax and diff whitespace checks passed. No customer site was changed, and no older WordPress installation or interactive browser flow was tested.

# 0.2.9 release validation — 2026-11-11

Copied instructions now place an agent-neutral User-Agent with a real client/service information URL before command discovery. Browser user agents stay unchanged, tools without custom headers retain their fallback, and the bundle contains no hosting-specific troubleshooting workflow.

On the Cove main development site, 42 browser checks and 25 native REST checks passed across 52 local HTTP requests. This includes the copied bundle's service URL, ordering and client-neutrality assertions, temporary browser sessions and credential expiry/rotation/revocation. Temporary test accounts/content were removed and existing administrator grants preserved. PHP syntax passed across 135 files; release header, runtime version, changelog and static feed agree on 0.2.9. The full unrelated licensing/package/metadata suites were not repeated for this instruction-only change. No customer site or connector deployment is part of this release.

# Release 0.2.8 — 2026-11-05

The exact packaged ZIP installed successfully over 0.2.7 on the isolated Cove release site and passed 547 PHP assertions: metadata argument types/authorization 12, toolbar visibility 28, metadata writes 31, reads 28, content 59, commands 58, authentication 32, roles 44, ownership/sharing 30, licensing 29, automatic licensing 20, settings 22, activation redirects 14, browser sessions 41, diagnostics 13, and native admin/MCP workflows 86. All 17 JavaScript tests passed. All 135 PHP files passed syntax checks; diff whitespace checks passed.

The new visibility tests exercise the actual authenticated AJAX hide/unhide endpoint, invalid nonce and non-admin rejection, removal of shares across multiple owners, persistence of an independently owned license, toolbar node removal/restoration and continued settings access. UI tests cover automatic saving, disabled sharing for hidden users and failed-save restoration. Metadata regressions reproduce numeric-string ID failure and retain explicit field denial at minimum/ordinary/maximum priorities. End-to-end MCP checks update a document-style file reference, preserve its existing protected field reference, read back the result and retain an explicit registered field restriction. Existing page editing and native media uploads pass.

All generated users, posts, terms, media, credentials, MU fixtures and test package were removed by test cleanup. Provider responses were simulated. No production connector deployment, account permission expansion or customer-site update was performed. The local reproduction did not establish the cause of the original customer-reported failure. No interactive visual browser pass was performed.

Version header, constant, changes.md and update.json agree on 0.2.8. Shipping bytes match the staged source and exclude tests, internal QA notes and repository instructions. Private release-0.2.8 evidence retains the artifact hash, source manifest, suite logs and publication verification.

# Metadata authorization investigation — development, 2026-11-05

A numeric-string WP_Post ID reproduced a scoped-baseline mismatch: WordPress passes an integer object ID to auth_post_meta callbacks, while Guest Key compared it strictly against the unnormalized caller ID. Normalize the scoped post ID and key before selecting/installing the temporary baseline. Preserve explicit authorization even at PHP_INT_MIN by running the baseline before existing callbacks at that priority. Temporary observers record argument types and final field authorization without changing filter results; the existing guest_key_content_denied code now includes post_access, metadata_authorization, capability or readonly stages. Individual metadata commands, descriptions and combined post saves use the shared implementation. No permission expansion or plugin allowlist was added.

220 local checks passed: the new numeric-string/ACF-reference regression 12, existing metadata writes 31, metadata reads 28, content 59, commands 58 and authentication 32. Tests retain explicit field denials at minimum/default/maximum priorities, capability/post denial, schema validation, sanitizers and temporary-hook cleanup. The first full content run found an inactive optional MCP adapter left by earlier fixture cleanup; enabling the existing dependency in a separate bootstrap restored all 59 checks. Temporary posts were removed. Prior toolbar-visibility work remains uncommitted alongside this investigation.

The local regression established a Guest Key defect. It did not establish the cause of the original customer-reported failure; customer data and live authenticated reproduction were outside this validation.

# Release 0.2.7 — 2026-11-04

The packaged candidate passed 201 PHP assertions on the isolated Cove release site: activation redirects 14, settings 22, diagnostics 13, licensing 29, automatic licensing 20, administrator ownership/sharing 30, authentication 32 and temporary browser sessions 41. All 14 JavaScript tests passed, including automatic sharing and removal, failed-save restoration, focus preservation without rebuilding controls, grouped billing links with new-tab semantics, inactive-license feedback, expired/pending/outage states, automatic purchase matching, and purchase-check concurrency.

The exact shipping ZIP installed successfully over 0.2.6. Five real HTTPS checks passed against its installed code: dashboard activation redirects to settings, the settings page renders without Connection details, authenticated AJAX enables and removes a share, and an invalid nonce is rejected. The temporary browser-session suite initially encountered WordPress's database-upgrade redirect because Cove's core files had advanced; the isolated database was upgraded through WordPress and all 41 checks passed on rerun. No plugin change was needed for that fixture issue.

All 133 PHP files passed syntax validation; JavaScript execution and git diff --check passed. Header, constant, changes.md and update.json agree on 0.2.7. The archive excludes tests, internal QA notes and repository instructions. Provider responses were simulated in licensing tests. The extra HTTP workflow used a local fixture entitlement; no production purchase, email, billing mutation or customer-site change was made. Generated credentials, session, recipient and license fixture were removed. No connector deployment or database migration is required. Private release-0.2.7 evidence contains the exact artifact digest, suite logs and HTTP/publication checks. Verification was programmatic; no interactive visual browser pass was performed.

# Release 0.2.6 — 2026-10-30

868 PHP checks passed: 812 assertions across the full single-site suite (including 53 operations-policy and 80 admin/MCP checks), 41 across both multisite suites, 10 missing-adapter access checks and 5 installer-failure checks. All six JavaScript tests passed. Added release cases for missing/failing cron callbacks and safe profile edits through native plain-permalink REST transport. Temporary fixture traffic used the identified agent header; unmocked external provider traffic was blocked. Multisite fixture state left by the previous destructive suite was cleared only in its isolated integration database before rerunning.

The 0.2.6 shipping ZIP installed and activated on a separate fresh WordPress installation, where all 53 operations-policy checks passed again. The actual published 0.2.5 ZIP discovered the local candidate feed through its registered Plugin Update Checker and updated successfully using the native WordPress updater. Eleven post-upgrade checks passed: version bootstrap; preserved trial identity/expiration, temporary key/lifetime and unrelated native application password; authentication with the old key; denial of role/password/email changes and account creation using that key; new cron/theme/plugin commands; and revocation. The initial upgrade assertion compared cache timestamps too strictly; it was corrected to verify trial identity/expiry, which are the actual preservation requirement. All upgrade credentials and trial fixtures were removed.

Header, constant, changes.md and update.json agree on 0.2.6. The shipping archive excludes tests, internal QA notes and repository instructions.2.6. Account restrictions cover standard WordPress capabilities, native REST (including batches), Guest Key forwarding and browser account screens; this does not sandbox arbitrary third-party PHP. No production checkout was repeated and no connector deployment is required. No customer site was edited.

# Operations and account policy — development, 2026-10-30

Added cron list/schedules/schedule/run/delete and theme list/get commands to the existing shared REST/MCP/Abilities surface. Cron events expose exact timestamp/hook/signature/arguments and recurrence, due status and paginated listing. Mutations use native scheduling APIs. Running consumes the selected occurrence, reschedules recurring events before the callback as WP-Cron does, rejects events without registered callbacks, and logs callback failures without claiming rollback. Raw cron/plugin/theme lifecycle option writes remain protected; package and theme-mod APIs remain available.

Term-meta get/list reads now use term-level edit authorization and include every stored key/value, including underscore, unregistered, authorization-denied, Guest Key-prefixed and non-ASCII keys. Existing list keys and get exists/values semantics remain; list additionally returns a values map. Describe includes all registered term fields. Write authorization, schemas/sanitizers and user-meta protections are unchanged.

User REST writes allow only basic profile fields (name, first_name, last_name, nickname, description, url, locale). Creation, deletion, roles, password, email, arbitrary meta and authentication mutations are denied by shared dispatch and a temporary-session REST guard. Error responses are normalized to WP_REST_Response so native batch handling returns a 403 instead of mishandling WP_Error. Temporary API/browser credentials additionally lose native create/promote/delete/remove/add user capabilities; browser sessions cannot use account-edit screens. Ordinary administrator sessions remain unaffected. This protects supported WordPress user workflows, not arbitrary third-party PHP that bypasses native permissions; package installation and registered cron callbacks remain trusted administrator operations.

500 checks passed on the isolated Cove fixture: 48 operations-policy checks, 80 real admin/MCP checks, and 372 existing checks (data 71, commands 58, authentication 32, browser 41, roles 43, content 59, dispatch 16, settings 22, ownership/sharing 30). Coverage includes future events, pagination, exact-argument selection, one-time execution/removal, recurrence preservation, invalid recurrence, missing events, anonymous denial, term read completeness and unchanged writes, direct native user REST, forwarded REST, registered abilities, native REST batches with user batching enabled by the temporary fixture, basic profile edits and browser screen denials. Fixtures, events, metadata, users, test credentials and temporary package were removed. The fixture required WordPress's normal database upgrade before browser checks after its core files had advanced; no customer site was modified. New/changed PHP syntax and git diff --check passed. No version bump, commit, push or publication has been performed for this work.

# Release 0.2.5 — 2026-10-29

Release verification passed on fresh isolated Cove WordPress 6.9.10 installations using PHP 8.5.12. The 21 main suites passed 752 assertions: licensing 29, automatic purchase matching 20, administrator ownership/sharing/migration 30, settings 22, authentication 32, temporary browser 41, duration 39, license expiration 9, roles 43, native REST 25, metadata reads 28, metadata writes 31, content 59, commands 58, file inspection 73, native data 71, dispatch 16, diagnostics 13, discovery 11, integration 29 and admin abilities 73. Two isolated multisite suites passed 19 and 22 checks. Missing-adapter native access passed 10 and installer failure handling passed 5: 808 PHP assertions total. All 6 JavaScript tests passed. Provider calls were simulated; unmocked external HTTP was blocked on the release fixtures.

Release checks found and fixed stale-license refresh attempting to reacquire the credential lock. Refresh now finishes before rotation; entitlement is rechecked inside the rotation lock before issuing access. New regressions verify that a stale paid cache refreshes successfully and unlicensed administrators receive onboarding guidance instead of an internal busy message. Native authentication checks credential expiration and site/network scope before consulting account-scoped licensing, preserving the established wrong-site error. Tests were corrected to initialize/restore adapter state and use isolated, complete package fixtures; non-admin AJAX checks now include share and claim actions.

The shipping archive installed and activated on a fresh site and passed 10 package-level checks, including real HTTPS protected-field writes/reads, cursor continuation, neutral instructions, revocation and unlicensed onboarding. The actual published 0.2.4 ZIP discovered the candidate JSON feed through its already-registered Plugin Update Checker and successfully updated via WordPress's native plugin updater. Nine post-upgrade checks verified 0.2.5 bootstrap, email-matched trial migration without changing its token/expiry/check time, no automatic sharing, preservation of an existing temporary key and unrelated application password, actual HTTPS authentication and protected-field writes using the old key, and successful revocation. All fixture credentials and trial records were removed. That is 20 additional feed/package checks.

Version header, runtime constant, changes.md and update.json agree on 0.2.5. The archive includes runtime guides and the bundled updater while excluding tests, repository instructions, internal QA evidence and Git metadata. Production trial/email/provider checkout was not repeated; this release requires no connector or database deployment. Customer-site writes were not tested. Earlier development notes below describe intermediate policies superseded by this release.

## Generic custom-field writes — development, 2026-10-29

Updated the earlier read-only expansion policy in response to the owner's revised write policy. Underscore-only metadata protection no longer blocks Guest Key writes on editable posts. A shared can_write_meta helper supplies an allowed baseline at the earliest priority of the native field auth hook only while checking the specific current user, post, field and operation. Existing global/subtype authorization callbacks, map_meta_cap/user_has_cap filters and post-type permissions still run. Registered fields' auth callbacks (including denial callbacks supplied by their registration), readonly schemas, type/schema validation and native sanitizers/metadata hooks remain enforced. The temporary baseline is removed in finally and never alters global is_protected_meta behavior. Individual add/update/delete, combined post save preflight and describe.can_edit use the same policy. New-post combined saves no longer reject unregistered underscore fields before creation; their post/field permission check still occurs once the new object exists. No plugin names or metadata keys are allowlisted.

Generic APIs cannot reproduce plugin-specific form validation, ACF editing workflows or derived-data rebuilds automatically. Metadata hooks run; arbitrary editing-form handlers do not. Documentation and command help now distinguish those layers. This allows raw Elodin Schema-style unregistered protected fields and ACF-style value/reference pairs without integration changes; it does not claim all plugin field rules can be inferred or guarantee every registered field is writable.

The new write regression first reproduced the existing failure on a protected custom field, then passed 31 checks after the change. Tests cover protected create/add/update/delete, multiple/empty/structured values and escaping, combined existing/new post saves, ACF-style reference pairs, global/subtype auth denials, custom capability denial, registered schema and sanitizer enforcement, readonly fields, actual describe.can_edit state, missing post permissions and removal of the temporary permission override. All 28 updated read checks, 59 content checks, 58 command checks, and 32 authentication checks also passed: 208 assertions total. Registered REST and MCP routes now both allow unregistered protected writes/deletion and both retain registered denial callbacks. All eight PHP files changed by this task passed syntax checks. Temporary posts, users, credentials, entitlement fixtures and MCP dependency state were restored. No live customer writes, plugin updates, or release publication were performed. Production behavior was assessed from the previously read source, not tested with a live save.

## Resumable file inspection and command conventions — development, 2026-10-29

Confirmed the reported scan-budget gap: legacy offset paging counted returned matches, so scans stopped by the 16 MiB, 5,000-entry or three-second limits could not continue. File list/search now return signed, stateless next_cursor checkpoints for all four boundaries (results, bytes, entries, time), including a source-line checkpoint when a search pauses inside a file. Repeat the same filters with cursor; limit may change. Tokens expire after one hour and are bound to the current administrator, site and filters. Existing offset paging and recursive flags remain supported. Optional depth uses the same levels for listing/search: 1 for immediate children, 2 for the next level, maximum 21. Known credentials/VCS exclusions, canonical path checks, symlink restrictions, file-size budgets and authorization remain enforced. Pagination is not a filesystem snapshot; callers must inspect skipped counts on every page.

Default and focused command help explain post id versus metadata post_id/key versus option key, wp-content-relative file paths, cursor/offset/page conventions, and small output requests. Guest Key's command runner does not truncate batches. A real local HTTPS response containing three full 64 KiB file chunks decoded intact; the reported client cutoff remains unconfirmed without its original response/client details. Guidance warns against replaying writes after clipped client output. No command arguments were renamed.

All 73 filesystem checks and 58 command checks passed (131 total). Coverage includes byte/entry continuation without rereading completed files, result continuation inside a file, cursor traversal across directory boundaries, a deterministic expired walker deadline followed by successful continuation, tampered/expired/other-user/wrong-filter cursors, depth bounds, legacy offset/recursive behavior, native HTTPS GET cursor encoding, batch response integrity, authorization, source confinement and fixture cleanup. Syntax passed for all nine PHP files touched by this task; git diff --check passed. Temporary content, users, credentials and local entitlement fixtures were removed; existing grants and dependency state were preserved. No customer-site changes or plugin ZIP publication were performed. The affected client's display/transport limits and real production load were not tested.

## Complete post-metadata reads — development, 2026-10-29

Intentional policy: permission to edit a post authorizes reading all its stored metadata through Guest Key. The shared `post_meta` implementation no longer filters list/get by per-key write capabilities. Protected (`_`) and unregistered keys require no registration, allowlist, or REST exposure for reads. Existing list maps, get `key/exists/values`, unserialized structured values, empty values, and missing-key distinctions are preserved. Post-level authorization and revision-parent resolution remain in place. Add/update/delete per-key checks, registered schema validation and native sanitization are unchanged. Command/ability descriptions, the design discovery guide, README, and repository instructions now document the read/write distinction.

A new regression suite reproduced the denied protected-field read before the fix. After the fix, all 30 metadata-specific checks passed, covering protected custom fields, ordinary and auth-denied registered fields, multiple values, structured and empty arrays, empty strings, zero values, missing keys, denied post editing, unchanged mutations, schema validation, and sanitization. All 59 existing content checks passed after correcting their obsolete hidden-field expectation. All 30 authentication/transport checks passed, including protected-field get/list through the registered REST and MCP routes and denied protected writes through both. Total: 119 assertions. Temporary content/accounts/credentials were removed; no customer data or provider activation was modified. PHP syntax and diff checks passed. This policy change has not been published in a plugin ZIP.

## Administrator-owned licenses and sharing — development, 2026-10-29

Existing license products/site limits are retained. New activations and trial verification belong only to the administrator performing them. Paid owners can select other administrators who share their entitlement; multiple licenses can coexist on one WordPress site, and overlapping shares are combined. Each owner controls only their own license and sharing list. Duplicate entry of a key already managed on this site directs the user to sharing rather than consuming a second activation. Credential issuance and early native REST/browser authentication resolve the issuing user’s own/shared entitlement explicitly, without network/capability recursion. Trial/paid caches, verification requests, purchase matching, and scheduled refreshes are account-scoped. License mutations use a shared lock to prevent concurrent cross-owner changes from losing state.

On an ordinary administrator visit after upgrade, legacy trial email or the provider purchase email is matched to a current administrator. Tokens, provider activation IDs, start/expiry timestamps and site binding are preserved; no other account is automatically shared. Unmatched records show an explicit claim action. Matching legacy verification requests are also moved separately. Legacy records remain intact if ownership persistence fails. Older plugin versions cannot read the new per-account records; a downgrade requires an explicit reviewed ownership/storage rollback, not silently restoring a site-wide license for everyone.

279 PHP assertions passed across ownership/sharing (28), paid licensing (29), automatic purchase linking (20), settings (22), authentication (25), temporary browser sessions (41), duration (39), expiration (9), roles (41), and native REST (25). Six existing JavaScript checks passed. Tests now give temporary administrators explicit local entitlements rather than assuming a site-wide license. Focused sharing tests exercised real HTTPS authentication before/after overlapping-share removal: removing the final share immediately blocked an existing key. Browser-session tests also denied both new licensing actions to temporary agents. PHP syntax passed for all 22 changed/new PHP files at verification time. WordPress’s post-authentication role-denial behavior was retained after an initial REST regression exposed a premature entitlement rejection.

Desktop Chrome verified that selected sharing checkboxes persist after saving/reload, shared recipients see the owner’s display name and no remove/share controls, and unlicensed administrators retain Copy Guest Key but are routed to licensing. Temporary preview accounts, options, HTTP fixture and generated keys were removed; the original administrator browser session and inactive MCP state were restored. No real provider activation was made during these checks. Multisite-specific regression suites were updated for scoped fixtures but have not been executed for this change. No plugin ZIP/release has been published.

## Copy button icon alignment — development, 2026-10-29

Override the WordPress button Dashicon line-height with `1` inside Guest Key's existing 18px icon box. Core's taller button-icon line-height overflowed that box and shifted the glyph down despite flex centering. Desktop Chrome verified an 18px rendered line-height and identical icon/button vertical centers. Diff checks passed; this one-property CSS fix has not been published.

## Thread naming hint — development, 2026-10-29

The copied Guest Key now asks the agent to name its thread/conversation “[site name] edits” when supported. The title comes directly from the WordPress site name (decoded plain text), with the site hostname as the fallback for an empty name. Agents without rename controls suggest the title and continue; no client-specific command is assumed. PHP syntax and the focused instruction-template checks passed. This wording is not yet in the published ZIP.

## Post-type discovery follow-up — development, 2026-10-29

Downloaded the public latest ZIP and verified it is 0.2.4 with the existing `post_type: "any"` implementation and discovery guidance. Five focused checks passed on the installed 0.2.4 release: standard/non-REST custom cross-type listing, pagination, explicit plural names, help discovery, and unknown-type guidance. Seven focused checks passed in development, adding valid editable type names/labels directly to invalid-type errors for both listing and describe. Existing content/command regression assertions now check the structured `post_types` error data. No changes to successful listing, authorization, pagination, or the public ZIP.

The initial broader content-suite run passed its any/search/authorization checks but reached an unavailable MCP inventory fixture and failed there; its finally block cleaned up fixtures and preserved access grants. Focused dispatcher checks then completed on both installations without requiring MCP or issuing credentials. The tester's installed version and exact failing request are not available, so the reported customer-site failure has not been reproduced. PHP syntax and diff checks passed. Error-detail changes remain unpublished.

## Agent-neutral wording policy — development, 2026-10-29

Audited shipping connection instructions, guides, help, UI, README, and current update description. Current user-facing wording uses generic AI-agent terminology. Historical changelog entries and QA records retain their original descriptions. The repository guidance now requires neutral instructions, capability-based alternatives, and optional integration-specific examples. All 22 existing settings checks passed; PHP syntax, update-feed JSON, and diff whitespace validation passed. These wording changes remain unpublished.

## Tool-neutral connection instructions — development, 2026-10-29

Replaced coding-client-specific setup and internal T3 tool references with three HTTP steps: discover commands, read the schema, and send a read-only JSON command. Browser sign-in and MCP remain optional; unsupported user-agent customization does not block connection. Commands are explicitly JSON values, not shell commands, and failed-batch guidance retains the warning about completed writes.

Sixteen focused template checks passed against both MCP-available and MCP-unavailable branches using example credentials. The generated starter body decodes as valid JSON and uses `post list` with `post_type: "any"`. PHP syntax and diff whitespace checks passed. Updated existing browser/command assertions for the new wording; their full HTTP suites were not rerun for this copy change. No credentials were issued, licensing records changed, or external provider requests sent by the template checks. This change has not been published in a plugin ZIP.

## 0.2.4 release validation — 2026-10-29

Production connector 0.2.1/schema 4 is deployed and healthy. Current plugins request `trial_days: 3` in the email challenge; its signature fixes the 72-hour duration through completion/retry. Older plugins retain their supported one-day issuance. Migration 004 preserves existing trial expiration, verification evidence, purchase rows and runtime restrictions. A restored production backup was migrated and compared before production migration. No existing trials were extended.

Passed on the isolated release site: automatic licensing 20, paid licensing 29, license expiration 9, duration 39, authentication 25, browser 38, roles 41, command help/execution 55, content 59, dispatch 16, settings 22 and diagnostics 13 (366 checks). Six JavaScript checks passed; PHP syntax passed across 121 files, including bundled dependencies. The nine isolated service tests include real PostgreSQL and HTTP, 72-hour issuance, legacy issuance, email wording, signed duration/retry behavior, invalid duration rejection and the complete migration.

The initial authentication run lacked an entitled fixture; it failed without issuing access. With the existing isolated paid setup and adapter initialized before bootstrap, all 25 authentication checks passed. Disposable validation databases were removed after the preflight.

All recent settings changes are included: license first/highlight, side-by-side visible key and email entry, no saved-key input until removal, compact connection text, local expiration, readable 15-minute slider, current WordPress hover accent, animated copy feedback and background purchase matching. Previous browser screenshots record these states. Release 0.2.4 is published at commit f62e753; feed commit 73412cf points to the versioned installable ZIP. Both the versioned and latest/guest-key.zip URLs were downloaded anonymously and matched SHA256 `1f078f2a00f3eec8d973b5b9ce510b88ac4324b965a1aa771e02c1878857a6a9`. The packaged plugin passed settings 22, automatic licensing 20 and paid licensing 29 checks. The disposable site was returned to 0.2.3, the native update checker found 0.2.4 from the public HTTPS feed, and the normal WordPress upgrader installed it. Every installed archive file matched the anonymously downloaded ZIP; settings 22 and automatic licensing 20 passed again afterward. No provider activation or customer licensing record was changed during package/upgrade verification.

## Side-by-side license entry — October 29, 2026 (development only)

- License entry is on the left; trial email/code entry is on the right, stacking when space is limited. Key entry is a normal text field with autocomplete, spellcheck, and autocapitalization disabled; the Show/Hide toggle is removed.
- Stored paid licenses continue to show status/actions only, including after reload; removing the license reveals a fresh empty key field.
- 22 settings and 6 JavaScript checks passed. Browser checked text/email types, horizontal alignment, activation, reload, and removal using a disposable fixture that never stores keys or changes real licensing. Fixture and its boolean state file removed.
- This replaces the earlier masked-by-default UI; the previous native TextExpander test already verified ordinary text inputs work.

## Local copy confirmation — October 28, 2026 (development only)

- Confirmation names Claude and Codex; expiration uses browser-local time, a short zone, and tomorrow when crossing midnight. The connection status and clipboard fallback share the same formatting.
- 22 settings checks and 5 JavaScript checks passed; JavaScript checks run in America/Chicago, America/Los_Angeles, and UTC.
- Browser confirmed the copied message with a credential-free AJAX fixture on the disposable release QA site. Fixture removed; no credentials issued or licensing records changed.

# Access duration slider — development

The number field is now a native range slider with visible 30-minute/24-hour endpoints and a live selected value; the explanatory paragraph was removed. Existing one-minute precision and server bounds remain unchanged. Chrome verified Home/End at 30 and 1440, live unit labels, arrow-key adjustment, and native form saving on isolated QA; the original QA duration was restored. Settings 20 and purchase-check 4 tests passed. These changes are not released yet.

# Native TextExpander verification

On 2026-10-28, Chrome on an isolated test site verified TextExpander expansion into the visible license field using native macOS typing. Password masking was restored and the field cleared afterward; the license was not activated during this check.

# Cross-type post searches and help discovery — development

130 checks passed: content 59, commands 55, REST dispatch 16. Tests cover `post_type: "any"` across non-public and non-REST types, mixed per-type author restrictions before pagination/counting, excluded types without edit permissions, query-filter cleanup, invalid-type discovery guidance, rejecting `any` for creation, dynamic type names/labels in default and focused help, and authenticated REST help/list calls. Existing failed-batch tests retain `failed_index` and `skipped`. The isolated QA license, adapter state, temporary users/content, and access grants were restored after testing. These changes are development-only.

# Compact connection and duration cards — development

The connection card uses one paragraph, an icon-bearing Copy Guest Key button, and an initially empty live status region. Access duration uses an inline number/units/Save row and one help line. The existing 20 settings checks passed; Chrome desktop and 390px views confirmed the icon is white and controls do not cause horizontal overflow. Access creation and duration enforcement are unchanged; this UI is not released yet.

# Automatic purchase checks on hover — development

Four JavaScript checks passed using the actual admin script: expired-trial startup/hover/focus checks, in-flight request sharing and the 15-second cooldown, paid-state polling stop, manual activation waiting for an in-flight check, quiet outage retry, and hidden/unregistered/paid-site guards. Settings 20 and automatic licensing 18 regression checks passed. Chrome with an isolated response fixture verified that no-match hover preserves the entered license and focus, the manual purchase button is absent, and an expired trial switches to the licensed card without navigation when a purchase is found. The fixture was removed; no QA license or grant was saved. These changes remain development-only.

# Toolbar and two-column settings — development

108 checks passed: settings page 20, roles 41, licensing 29 and automatic licensing 18. Chrome on the isolated QA site verified successful native clipboard copying with checkmark/Copied! feedback, Settings navigation, keyboard ArrowDown focus, toolbar revocation and hiding the revoke link afterward, inventory filtering and schema inspection. Desktop columns measured the same height. At 390px, columns stack with no horizontal page overflow. Hover computed as #2271b1 with #fff text. Temporary QA licensing and access were removed afterward. No live release has been published for these changes.

# License entry — 0.2.3

Show/Hide key keeps password entry by default and replaces the input when toggling, because Chrome retains password-history flags. CSS masking was rejected after checking Chromium Secure Input handling. Chrome verified password entry by default, unmasked text entry after Show key, focus transfer, repeated Show/Hide toggles preserving the entered value, and disabled autocomplete and text corrections. Settings-page 18 and licensing 29 regression checks passed. Native TextExpander expansion was subsequently verified; see the native verification entry above. No licensing/provider logic changed.

# Toolbar/settings verification — 0.2.2

106 checks passed: settings page 18, roles 41, licensing 29 and automatic licensing 18. Chrome on the isolated QA site verified a real perpetual owner-license activation, status check and instance removal; the licensed toolbar contains only the native Settings link and its target opens the settings page. QA-only provider fixtures covered active and expired trial settings, expired-trial activation, immediate post-activation keyboard focus, and clearing all license-entry fields from the closed setup panel. Licensing/provider logic is unchanged; no connector deployment is required.

# Local licensing verification — 0.2.0 candidate

## Webhook and native-update verification — 2026-10-28

Signed webhook acceptance and unsigned-request rejection were verified. An isolated provider sandbox exercised purchase matching, automatic activation after trial expiry, manual activation and license removal. These checks used test-mode purchases and did not represent a real charge or billing-cycle test.

Trial and paid UI-copied credentials each passed five real public HTTPS checks: authentication, command discovery, draft creation, editing and Trash. Revocation subsequently returned 401. A published 0.1.5 installation discovered the candidate update feed and completed the native upgrade to 0.2.0. Shipping-file bytes matched the candidate tested for installation and upgrade.

## Fresh installation and service verification — 2026-10-28

The candidate installed and activated on fresh WordPress 6.9.10. An upgrade from 0.1.5 preserved an existing grant and unrelated application password, retained the two-hour default, and began without a license entitlement. Regression checks passed: licensing 29, automatic licensing 18, duration 39, expiration 9, roles 41, authentication 25, browser 38, and settings 15. PHP and JavaScript syntax and whitespace checks passed.

A real service verification email and site-proof flow established a trial. Its UI-copied credential passed authentication, discovery and draft create/edit/Trash checks over HTTPS. Test credentials were revoked after verification.

## Configurable access duration — 2026-10-27

The native duration form and server enforce whole minutes from 30 to 1440, defaulting to 120. Normal administrator sessions and a valid nonce are required. Each grant snapshots its lifetime; trial/license expiry still caps access.

The duration suite passed 39 checks, including real form submissions, bounds, malformed values, CSRF rejection, long and short credential lifetimes, legacy grants, and temporary-session denial. Authentication 25, browser 38 and settings 15 checks also passed. Original options and test accounts, credentials and sessions were restored or removed.

## Toolbar and automatic-license verification — 2026-10-27

Desktop and 390-pixel mobile browser checks exercised trial signup, wrong-code feedback, verified access, copying, revocation, expired-trial controls, manual license activation and removal. Physical mobile devices and Safari were not tested.

Automatic purchase matching passed with isolated signed replay fixtures, including an expired trial without extending its entitlement. These replay checks did not establish real merchant webhook delivery. Service tests passed 9 checks; plugin checks passed licensing 29, automatic licensing 18, expiration 9, settings 15, browser 38 and roles 41. Existing grants and normal sessions survived regression tests.

An initial clipboard automation read returned stale contents; a repeated copy passed five HTTP checks. No product defect was established by that observation, and it did not certify Safari clipboard behavior.

## Provider sandbox verification — 2026-10-27

Two sandbox product variants were exercised without modifying live products or making real charges. An isolated harness passed 17 checks over 16 sequential real License API requests: product validation, trial-to-paid activation, site limits, repeat activation, deactivation, slot reuse, multiple site identities, validation and omission of secrets from UI state. Sandbox IDs were mapped only inside the harness; shipping product checks were unchanged. Test activations were released.

Cancellation at the end of the billing period retained validity, and resuming restored the subscription state. Annual renewal, immediate cancellation/expiration and refund workflows were not verified with the real provider. A simulated webhook was not counted as a billing-cycle test.

## Earlier local verification

A separate local service and WordPress fixture exercised verification, invalid codes, credential creation/copy/revocation, expired-trial controls and rejection of an invalid license. Verification preceded trial activation, and trial reuse was rejected. Real email delivery and code completion were also verified.

Local checks passed: licensing 29, expiration 9, settings 15, missing-adapter native access 10, browser 38, roles 41, and service 8. The service tests covered verification attempt limits, persistent send limits, parallel completion, idempotent retries, mail failure, expired codes, request-origin protections, sanitized errors and schema migration. Test fixtures were removed or restored. Paid success paths in this earlier pass used simulated provider responses.
