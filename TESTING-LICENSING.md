## 0.2.4 release validation — 2026-10-29

Production connector 0.2.1/schema 4 is deployed and healthy. Current plugins request `trial_days: 3` in the email challenge; its signature fixes the 72-hour duration through completion/retry. Older plugins retain their supported one-day issuance. Migration 004 preserves existing trial expiration, verification evidence, purchase rows and runtime restrictions. A restored production backup was migrated and compared before production migration. No existing trials were extended.

Passed on the isolated release site: automatic licensing 20, paid licensing 29, license expiration 9, duration 39, authentication 25, browser 38, roles 41, command help/execution 55, content 59, dispatch 16, settings 22 and diagnostics 13 (366 checks). Six JavaScript checks passed; PHP syntax passed across 121 files, including bundled dependencies. The nine isolated service tests include real PostgreSQL and HTTP, 72-hour issuance, legacy issuance, email wording, signed duration/retry behavior, invalid duration rejection and the complete migration.

The initial authentication run lacked an entitled fixture; it failed without issuing access. With the existing isolated paid setup and adapter initialized before bootstrap, all 25 authentication checks passed. The first backup-restore preflight hit a private-file permission boundary; reading the root-only backup through stdin fixed the preflight without broadening permissions. Both disposable preflight databases were removed.

All recent settings changes are included: license first/highlight, side-by-side visible key and email entry, no saved-key input until removal, compact connection text, local expiration, readable 15-minute slider, current WordPress hover accent, animated copy feedback and background purchase matching. Previous browser screenshots record these states; final package/upgrade verification follows before publication is complete.

## Side-by-side license entry — October 29, 2026 (development only)

- License entry is on the left; trial email/code entry is on the right, stacking when space is limited. Key entry is a normal text field with autocomplete, spellcheck, and autocapitalization disabled; the Show/Hide toggle is removed.
- Stored paid licenses continue to show status/actions only, including after reload; removing the license reveals a fresh empty key field.
- 22 settings and 6 JavaScript checks passed. Browser checked text/email types, horizontal alignment, activation, reload, and removal using a disposable fixture that never stores keys or changes real licensing. Fixture and its boolean state file removed. Screenshot: `68-side-by-side-license-entry.png`.
- This replaces the earlier masked-by-default UI; the previous native TextExpander test already verified ordinary text inputs work.

## Local copy confirmation — October 28, 2026 (development only)

- Confirmation names Claude and Codex; expiration uses browser-local time, a short zone, and tomorrow when crossing midnight. The connection status and clipboard fallback share the same formatting.
- 22 settings checks and 5 JavaScript checks passed; JavaScript checks run in America/Chicago, America/Los_Angeles, and UTC.
- Browser confirmed the copied message with a credential-free AJAX fixture on the disposable release QA site. Fixture removed; no credentials issued or licensing records changed. Screenshot: `66-local-copy-confirmation.png`.

# Access duration slider — development

The number field is now a native range slider with visible 30-minute/24-hour endpoints and a live selected value; the explanatory paragraph was removed. Existing one-minute precision and server bounds remain unchanged. Chrome verified Home/End at 30 and 1440, live unit labels, arrow-key adjustment, and native form saving on isolated QA; the original QA duration was restored. Settings 20 and purchase-check 4 tests passed. Screenshot 64 records the desktop slider. These changes are not released yet.

# Native TextExpander verification

On 2026-10-28, Chrome on the isolated QA site verified the actual existing `zgues` shortcut using native macOS typing. With Show key enabled, TextExpander expanded it to the expected 36-character owner license (compared privately; the key was not logged). Hide key restored password masking. The field was then cleared and verified empty; Activate license was never clicked. Screenshot 62 records masked entry before clearing. This completes the previously pending foreground TextExpander check.

# Cross-type post searches and help discovery — development

130 checks passed: content 59, commands 55, REST dispatch 16. Tests cover `post_type: "any"` across non-public and non-REST types, mixed per-type author restrictions before pagination/counting, excluded types without edit permissions, query-filter cleanup, invalid-type discovery guidance, rejecting `any` for creation, dynamic type names/labels in default and focused help, and authenticated REST help/list calls. Existing failed-batch tests retain `failed_index` and `skipped`. The isolated QA license, adapter state, temporary users/content, and access grants were restored after testing. These changes are development-only.

# Compact connection and duration cards — development

The connection card uses one paragraph, an icon-bearing Copy Guest Key button, and an initially empty live status region. Access duration uses an inline number/units/Save row and one help line. The existing 20 settings checks passed; Chrome desktop and 390px views confirmed the icon is white and controls do not cause horizontal overflow. Screenshot 60 records the updated development page. Access creation and duration enforcement are unchanged; this UI is not released yet.

# Automatic purchase checks on hover — development

Four JavaScript checks passed using the actual admin script: expired-trial startup/hover/focus checks, in-flight request sharing and the 15-second cooldown, paid-state polling stop, manual activation waiting for an in-flight check, quiet outage retry, and hidden/unregistered/paid-site guards. Settings 20 and automatic licensing 18 regression checks passed. Chrome with an isolated response fixture verified that no-match hover preserves the entered license and focus, the manual purchase button is absent, and an expired trial switches to the licensed card without navigation when a purchase is found. Screenshot 59 records that simulated success state. The fixture was removed; no QA license or grant was saved. These changes remain development-only.

# Toolbar and two-column settings — development

108 checks passed: settings page 20, roles 41, licensing 29 and automatic licensing 18. Chrome on the isolated QA site verified successful native clipboard copying with checkmark/Copied! feedback, Settings navigation, keyboard ArrowDown focus, toolbar revocation and hiding the revoke link afterward, inventory filtering and schema inspection. Desktop columns measured the same height. At 390px, columns stack with no horizontal page overflow. Hover computed as #2271b1 with #fff text. Temporary QA licensing and access were removed afterward. Screenshots 55–58 record the desktop, copy feedback, dropdown and mobile layouts. No live release has been published for these changes.

# License entry — 0.2.3

Show/Hide key keeps password entry by default and replaces the input when toggling, because Chrome retains password-history flags. CSS masking was rejected after checking Chromium Secure Input handling. Chrome verified password entry by default, unmasked text entry after Show key, focus transfer, repeated Show/Hide toggles preserving the entered value, and disabled autocomplete and text corrections. Settings-page 18 and licensing 29 regression checks passed. Screenshot 54 records the masked default. Native TextExpander expansion was subsequently verified; see the native verification entry above. No licensing/provider logic changed.

# Toolbar/settings verification — 0.2.2

106 checks passed: settings page 18, roles 41, licensing 29 and automatic licensing 18. Chrome on the isolated QA site verified a real perpetual owner-license activation, status check and instance removal; the licensed toolbar contains only the native Settings link and its target opens the settings page. QA-only provider fixtures covered active and expired trial settings, expired-trial activation, immediate post-activation keyboard focus, and clearing all license-entry fields from the closed setup panel. Screenshots 49–52 record these states. Licensing/provider logic is unchanged; no connector deployment is required.

# Local licensing verification — 0.2.0 candidate

## Public webhook and native-update verification — 2026-10-28

Configured the live merchant webhook for `license_key_created` and `license_key_updated` at `https://api.guest-key.com/v1/lemon/webhook`. Lemon Squeezy limits its signing-secret field to 40 characters; used 40 random hexadecimal characters (160 bits), while retaining the separate 32-byte encryption key. Both secrets are private and backed up. A signed wrong-product/test-mode event returned 200 without entering the production index; an unsigned event returned 401. No real charge, live subscription change or simulated billing cycle was performed.

In a separate schema-3 sandbox database, a new Single Site test checkout generated license 1721934 for the verified QA email. The actual merchant-delivered signed `license_key_created` populated the encrypted purchase index; subsequent actual activation/removal notifications updated its status. The UI's purchase check claimed, validated and activated the key without key entry. The existing verified trial/token was preserved, both disposable clocks were advanced into expiry, and the service confirmed `expired`. Returning to the UI automatically activated the same purchase despite expiry; no trial extension was granted. Manual key entry and normal removal also succeeded. The final provider response confirmed zero activations on this new sandbox license. The temporary sandbox webhook was deleted; the live webhook remains. Production's purchase index remained empty throughout sandbox testing.

Both trial and paid UI-copied two-hour credentials passed five real public HTTPS requests each: authenticate, discover tooling, create/edit a draft and move it to Trash. Final revocation returned 401. Private keys/tokens, QA identity mapping, mail configuration and clock fixtures are outside the shipping package. The original QA site's separate Unlimited activation was left intact. The live product confirmation and receipt instructions now describe automatic linking through **Copy Guest Key → License & trial**, retaining manual entry as a fallback; the live confirmation text persisted after reload.

The committed archive has exactly the same shipping-file bytes as the candidate used for fresh installation and upgrade. A temporary public HTTPS feed advertised that archive to the actual 0.1.5 updater: it discovered 0.2.0, populated WordPress's native update transient and completed `wp plugin update guest-key` successfully. The resulting plugin loaded its licensing class with the two-hour default and no entitlement or leftover grants. Screenshots 39–47 record live webhook setup, test checkout, automatic active/expired upgrades, manual activation, cleanup and live onboarding copy. The temporary checkout fields initially did not render while the tab was in the background; activating the tab loaded Stripe's fields normally. This was browser automation friction, not a product payment failure.

## Release preflight and deployed connector — 2026-10-28

Connector 0.2.0/schema 3 is now deployed at `api.guest-key.com`; public/private health passed. The original trial-signing secret was preserved. A schema-1 database/config/code backup was restored into a disposable database before migration, and the paired rollback was exercised successfully when the initial dependency permissions prevented startup. Corrected dependency access was checked as the service user before the successful retry. Production has private Resend and live-product Lemon configuration. Merchant webhook registration and actual merchant delivery remain pending because the Chrome merchant session expired again; no plugin tag, release or update feed has been published.

Built an explicit shipping ZIP with the license class, runtime guides and bundled updater, excluding QA harnesses, tests and private files. It installed and activated on a fresh WordPress 6.9.10 site. An actual 0.1.5 → packaged 0.2.0 upgrade preserved the existing grant and an unrelated native application password, retained the two-hour default, and began unlicensed. The fixture credentials were revoked afterward. The existing QA site passed licensing 29, automatic licensing 18, duration 39, expiration 9, roles 41, authentication 25, browser 38 and settings-page 15 checks; PHP/JavaScript syntax and whitespace checks passed.

The fresh release site was temporarily shared over public HTTPS. Through the production connector, the plugin published its real proof, Resend delivered a code, and the native toolbar UI verified it. Production stored a verified trial with exactly 86400 seconds of access. Its UI-copied two-hour credential passed five actual public HTTPS checks: authentication, tooling discovery, native draft creation, editing and Trash. The credential was revoked and the production-trial option retained privately before switching this disposable installation to a separate sandbox connector/database and actual sandbox provider identities. Production service data was not used for sandbox purchase matching. A transient unavailable Quick Tunnel and a failed local proof request were not counted as passing tests; a foreground tunnel and the successful actual production flow supplied the evidence above. Screenshots `37-resend-production-delivery.jpg` and `38-production-trial-active.jpg` record delivery and the active UI.

## Provider access and sandbox cleanup — 2026-10-28

Confirmed working merchant sessions in Chrome for Lemon Squeezy and Resend. Resend shows `guest-key.com` verified and the existing sending-only API key; its matching private local mail configuration is present with mode 0600. No additional key or DNS change is currently needed. Production still has no email or Lemon webhook configuration; authenticated browser access alone does not complete deployment or external webhook verification.

Released the orphan Single Site sandbox activation (license 1719751) through the merchant UI. The exact disposable QA-site instance was deactivated; the list now shows **Inactive (0/1)**. Screenshot `35-sandbox-activation-cleared.jpg` records the result with license keys masked. The QA site's separate Unlimited activation remains unchanged. No production deployment, migration, webhook registration, plugin publication, commit or push occurred.

## Configurable access duration — 2026-10-27 (America/Chicago)

Guest Key settings now has a native access-duration form: whole minutes from 30 to 1440, default 120. HTML constraints and server-side validation enforce both boundaries. The administrator's normal browser session and a valid nonce are required; temporary agent sessions cannot change it. The saved value affects newly issued credentials only. Every grant snapshots its selected lifetime, both REST/MCP and browser authentication enforce that snapshot, and existing grants without the new field retain their two-hour maximum. All access remains capped by confirmed trial/license entitlement.

`tests/duration.php` passed 39 checks, including actual HTTP form submission; minimum/maximum bypass attempts; CSRF rejection; malformed option fallback; 30-minute and 24-hour issuance; REST and browser access past the old two-hour cutoff; original duration enforcement after changing the setting; absolute 24-hour cutoff; old-key compatibility; agent-session denial; and license expiration caps. The suite restores original duration/license state and removes its temporary administrator, native passwords and browser sessions. Existing authentication (25), browser (38), and settings-page (15) regression checks also passed. Native browser saves at 90 and 120 minutes were visually confirmed; input underflow/overflow flags reject 29 and 1441. The QA installation remains set to 120 minutes with no active Guest Key grants. Screenshot `33-duration-settings-detail.jpg` captures the finished native control.

During testing, the disposable QA site's WordPress files were already 7.1.3 while its database remained at the earlier schema. This caused wp-admin to redirect to the database-upgrade screen. Completed its native `wp core update-db` (60717 → 61833), then the duration suite passed. No source or production site was upgraded as part of this change.

## Toolbar and automatic-license pass — 2026-10-27 (America/Chicago)

Implemented a toolbar-anchored dropdown under **Copy Guest Key** for setup/licensing and copy/revoke feedback. Incomplete setup opens automatically unless Close was used within the previous 24 hours in that browser/site/admin scope. Reload suppression and native pointer hover reopening passed. Storage inspection showed a deadline approximately 24 hours after Close. Desktop keyboard/focus behavior and 390-by-844 CSS-pixel mobile tapping were inspected; the mobile panel stayed within 8px gutters without horizontal overflow. Physical phone/keyboard and Safari were not tested.

The fresh QA site completed another real service email-code verification (privately captured mail, actual site proof). In a separate database `guest_key_dropdown_20261027`, a locally signed purchase replay assembled from the real Unlimited sandbox License API response populated the encrypted index. The plugin claimed, validated and activated it without manual key entry. The same flow succeeded with an intentionally expired trial clock fixture; the fixture did not extend trial access. Removal then allowed an actual Single Site test key to be entered in the new UI. Signature rejection, replay/order protection, verified identity, expired identity, cross-site denial and store/product/mode isolation are covered by real PostgreSQL/HTTP service tests. These are local signed replay tests, not real merchant webhook delivery.

Checks passed: service `npm test` (9 tests); plugin licensing 29; automatic licensing 18; expiration 9; settings page 15; temporary browser 38/32 HTTP; roles 41/10 HTTP. The current UI-issued paid credential additionally passed 5 real HTTP checks for authentication, tooling discovery, native draft creation/editing/Trash. Final revocation returned 401. Existing site grants and normal sessions survived regression checks. PHP/JavaScript syntax and whitespace checks passed.

Clipboard friction: the first automation read returned an older copied bundle despite the new success feedback. The browser's clipboard methods were automation wrappers. A repeated settings-button copy produced the current credential and passed all 5 HTTP checks. No product defect was established and no speculative clipboard code change was made; this is not a complete real-browser/Safari clipboard certification.

Screenshots 15–28 in the private QA review directory show the evolving dropdown, verified trial, expired trial, automatic/ manual paid activation, mobile tapping, anchored copy/revoke feedback and final license controls. The gallery focuses on the final UI states. Temporary agent credentials are revoked. The QA site is restored to a real Unlimited test license. One Single Site test activation originally required merchant-session cleanup after an expired login: while taking a final unconfigured screenshot, its local option was cleared without first refreshing the private fixture backup. This was a QA fixture error, not an application removal flow; merchant cleanup was completed on 2026-10-28 as recorded above. No real billing, production deployment, migration, release, commit or push occurred.

The automatic-activation feature is implemented locally. Release still requires schema 002→003 migration, private provider configuration, merchant webhook registration and an actual delivered signed event against a public test/staging connector. Historical purchase import is separate. Jon's waived annual renewal/refund/immediate-revocation tests remain out of scope.

## Fresh-site visual pass — 2026-10-27 (America/Chicago)

Created a fresh Cove WordPress 6.9.10 site at `https://guest-key-qa-20261027.localhost:8453/`, using a copy of the 0.2.0 candidate and a separate local PostgreSQL database `guest_key_visual_20261027`. The original test site's trial/license and the production service were not changed. A private loopback-only trial runner uses the real service code and real site-proof callback, allowing only this exact local origin. Verification mail is captured privately, not delivered through Resend during this pass. Only a QA must-use plugin maps the known sandbox product/variant IDs to the live IDs in actual Lemon Squeezy responses; it never changes provider validity, limits, or expiration, and is not part of the distributed plugin.

Captured 15 screenshots covering fresh installation, trial signup, code sent, wrong-code feedback, verified active trial, copied two-hour access, revocation, frontend connection, mobile active trial, invalid-license feedback, mobile and desktop paid activation, paid connection, license management, and final credential cleanup. The active trial converted to the existing real Single Site sandbox license through the visible license-entry UI. Native browser controls exercised license refresh and closing the panel. The site remains licensed for review, consuming one Single Site sandbox activation; temporary agent credentials were revoked. The original test site's license/grants remain unchanged.

Twelve actual HTTP checks passed on this separate WordPress installation: trial and paid credentials authenticate, discover command tooling, create a draft, edit that draft, and move it to Trash; previous rotated/revoked trial credentials return 401. The copied native REST URLs also work with the fresh site's plain permalinks. Two QA posts remain in Trash. Desktop and 390-by-844 CSS-pixel mobile views had no horizontal overflow; trial/license panels and controls fit the viewport. Physical phone/keyboard-open testing was not performed.

Browser friction: T3 snapshots timed out twice. Chrome completed the flow, but 1Password intercepted pasting the license key with a trust confirmation, which blocked browser commands until cleared. Key entry then succeeded. No browser extension settings were changed. Product improvements identified: show a license-management heading once access is active, and focus the Connect action after trial verification rather than the paid-key field. No UI source changes were made as part of this visual pass.

Private screenshots, HTTP receipts and harness live in `~/Cove/Sites/guest-key-qa-20261027.localhost/review/`, outside the public site. Screenshots contain no license or application-password values. Private mail, one-time login URLs, signing secret and connection bundles must not be included in release archives or the screenshot gallery. The isolated trial runner was stopped after testing, returning port 8791 to its prior unused state; the paid QA site remains reviewable. Production is unchanged. No deployment, release, commit or push occurred.

Release scope: Jon explicitly waived further annual-renewal/refund workflow testing and immediate remote-revocation testing; these are not release blockers.

At this earlier point, automatic license activation was feasible but not implemented; the newer toolbar/automatic-license pass above supersedes this proposal. The real Lemon Squeezy validation response includes the buyer email, and its keyed hash matched this site's verified trial identity. Current service routes/schema implement trials only. The proposed flow is: create a one-use site-bound purchase intent after email/site verification; attach its opaque reference to checkout; receive signed purchase/license webhooks; associate the paid license with the verified email and intended site; let that site claim/activate it without exposing a key in the UI. Separate email ownership verification is needed for existing purchasers/new installations, independent of the one-trial limit. Signed webhooks and encrypted paid-license storage must be added; importing historical purchases additionally needs server-side merchant API access. AI content traffic continues directly between the agent and WordPress.

## Follow-up — 2026-10-27 (America/Chicago)

Lemon Squeezy merchant test mode is now enabled. Its built-in **Copy to Test Mode** action created sandbox Guest Key product 1479179 from live product 1457777; no live prices, variants or license limits were edited. Sandbox Single Site variant 2305965 is $99/year with one activation; Unlimited variant 2305966 is $299/year with unlimited activations. Both generate license keys.

Completed both actual sandbox checkouts with the provider's documented test card. Orders 3491853 (Single Site) and 3491854 (Unlimited) issued subscription licenses; no real charge was made. Keys are saved mode 600 outside the public site and source repository.

An isolated local harness passed 17 checks over 16 sequential real License API requests: unmodified production code rejects sandbox product IDs before consuming an activation; trial-to-paid activation; single-site limit; idempotent re-entry; deactivation and slot reuse; unlimited activation on three simultaneous site identities; validation; secret omission from UI state; cleanup and restoration of the original local licensing/grants. Only known sandbox product/variant IDs were mapped to their live equivalents in real API responses during this harness process. No provider success/failure, activation limit or expiration was fabricated; shipping plugin code and its production identity checks were unchanged. Extra sites were provider activation identities, not separate WordPress installations.

Also cancelled Single Site at the billing-period end and confirmed the real API kept the key valid with a 2027-10-28 expiration; resuming restored the active subscription and null license expiration. Both sandbox subscriptions remain active and all test activations were released. Annual renewal, immediate cancellation/expiration, refunds, and a second actual WordPress installation remain unverified with the real provider. The dashboard's Simulate event control sends webhook simulations; no simulation was triggered or counted as a billing-cycle test.

Private harness, redacted result receipt and checkout screenshots: `~/Cove/Sites/mcp-access.localhost/guest-key-release-review/`. Do not include the private keys or signed order URLs in a release archive.

Fresh local checks before this follow-up also passed: 8 service tests, 29 plugin licensing checks, 9 HTTP expiration checks, PHP syntax for 77 project files and JavaScript syntax. Production remains service 0.1.0/schema 1; no production deployment, migration, plugin release, commit or push was performed.

## Earlier local verification

Verified on the Cove `mcp-access` site, with a separate local Guest Key service/database. Production service/schema and the public plugin release were not changed.

The browser flow was exercised in wp-admin and on the frontend: initial Connect agent notice, email/code entry, wrong-code feedback, successful verification, creation/copying and revocation of a native credential, expired-trial checkout/key controls, and rejection of a deliberately invalid key through the real Lemon Squeezy validation API. A new email on the same previously-trialed domain was rejected before another email was sent. The trial clock starts only after verification. A real Resend delivery test was received by Jon; a real verification email was also accepted by Resend and its code completed the local signup flow.

Local source tests:

- `tests/licensing.php`: 29 checks, using simulated Lemon Squeezy responses. Includes active/expired/renewed keys, single-site activation limits, repeat activation without consuming another slot, unrelated products, the perpetual owner override, failed/expired instance removal, offline cache bounds, malformed data, private option access and copied-site denial.
- `tests/license-expiration.php`: 9 checks, including actual HTTP denial of an existing REST key and browser session at license expiry, lifetime caps and unconditional update-checker registration.
- `tests/admin-page.php`: 15 settings/inventory/accessibility checks.
- `tests/native-access.php`: 10 isolated missing-adapter checks, with actual native REST and browser access. Temporary administrator/credentials removed.
- `tests/browser.php`: 38 checks across 32 HTTP requests, including all five new licensing AJAX actions being denied to temporary browser sessions.
- `tests/roles.php`: 41 checks across 10 HTTP requests, including all new licensing actions denied to a signed-in non-administrator with a valid nonce.
- Service `npm test`: 8 passing tests covering real PostgreSQL/HTTP, six-digit verification and its attempt limit, persistent send limits, parallel completion, idempotent retries, failed mail, expired/consumed codes, SSRF/DNS rebinding guards, sanitized delivery errors and a schema upgrade preserving existing trial identities/expiration.

The frontend expired-state notice fit desktop rendering and an isolated 390px headless rendering (342px notice with 24px gutters). Inputs use 16px text and submit controls have a 44px minimum height. The collaborative browser's resize setting did not change its actual viewport, so that live mobile viewport was not counted as a passing mobile test. No physical phone was tested.

No real paid subscription was purchased or renewed. Successful paid-license scenarios use provider fixtures. The valid owner key has not been entered into the local plugin. No production migration, new release, commit or push was performed.

Local review: `https://mcp-access.localhost:8453/`, signed in as an administrator. Use the toolbar's **License & trial** item. The local trial is active; local-only test records were cleaned up, and all temporary WordPress users/credentials were removed. The local service runs on loopback port 8791; `npm run dev` starts it again if stopped. Private configuration is under `~/Library/Application Support/Guest Key Service/local`.
