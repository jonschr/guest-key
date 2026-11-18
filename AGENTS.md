# Guest Key

## Agent-facing wording

- Keep instructions, command help, guides, UI messages, and current product descriptions agent- and client-neutral. Use “your AI agent” and describe the capability or protocol needed.
- Do not assume a particular app, model, terminal, internal tool name, or provider-specific workflow. Explain the supported operation using standard HTTP, JSON, REST, or MCP terminology as appropriate.
- Name an agent or client only when necessary for an integration-specific explanation or a useful optional example. Provide alternatives or the equivalent generic workflow; never make that example a prerequisite for other clients.
- If a capability is optional, explain the fallback. If a required capability is unavailable, tell the user rather than claiming success.
- Historical release notes and QA evidence may name the tools actually used; do not rewrite that history as current instructions.

## Free-edition access policy

Access and toolbar visibility are site scoped. The installing WordPress administrator starts as the protected controller; an explicit ownership transfer changes the protected controller. Fresh activation records the installer as controller and initially enables only them. Everyone using Guest Key, including the controller, must retain the WordPress administrator role on the current site.

The controller and administrators with enabled shared access may grant and revoke access for other administrators or themselves, but never revoke the controller. Access-list changes require a normal administrator session; temporary Guest Key API and browser sessions must not change access grants. Hidden administrators see a controller-contact message, without payment or license entry. Keep the Plugins list and recovery page available.

Upgrades preserve existing access and choose the earliest identifiable prior license owner as controller. If ownership cannot be identified, require an explicit claim through a normal WordPress administrator session; do not silently assign control. Preserve controller and access choices on reactivation. Never issue credentials to administrators without enabled access. Authorization during native authentication must receive an explicit user ID and perform no network/capability/current-user resolution.

The free edition has no commercial licensing, trials, email verification or checkout flow. Add no telemetry, phone-home statistics or provider licensing requests.

The current owner can use **Change owner** in a normal administrator session to transfer ownership to another eligible administrator. The new owner receives access; the previous owner keeps shared access and can subsequently be hidden. Shared administrators and temporary sessions cannot transfer ownership.

If the controlling administrator is deleted or loses their administrator role on the site, reset Guest Key to unconfigured: clear shared access and revoke temporary credentials for that site. Another administrator may explicitly set it up again. Retain inert legacy licensing records until after rollout; never re-migrate them during this reset.

## Post metadata read policy

Post-level `edit_post` permission grants complete read access through `post meta get/list`, including protected and unregistered stored keys. Do not apply per-key write permissions, registration, or REST-exposure filters to these reads. Preserve `exists` and value-list response semantics.

For writes, underscore-only protection is not an authorization rule. `can_write_meta()` supplies an allowed baseline only during the specific post/key/action capability check, before existing native authorization filters. Registered/global/subtype auth callbacks, capability filters, readonly schemas, value validation and native metadata sanitizers/hooks still apply. Use the same helper in individual metadata writes, combined `post save` preflight and field descriptions. Never change protection or permissions globally, special-case plugin names, or claim generic writes run validation found only in plugin-specific forms.

## Operations and account boundaries

Expose cron through native scheduling APIs and normal-administrator theme/plugin management through native package APIs. Temporary Guest Key API and browser sessions must not change plugin/theme/core packages, including deactivating or replacing Guest Key, or change the configured access duration. Media uploads remain available. File list/read/search excludes the canonical Guest Key implementation directory and aliases, while retaining traversal to bundled Markdown guides/skills and the public README. Keep raw lifecycle options protected. Cron run consumes one selected occurrence and preserves recurrence; it executes existing registered callbacks, not arbitrary PHP.

Guest Key must not create/delete user accounts or alter roles, passwords, email/authentication or membership permissions. API profile edits are limited to name, first_name, last_name, nickname, description, url and locale. Enforce the shared REST policy in both Guest Key dispatch and direct temporary-session requests, including nested native batches; enforce native account capabilities for temporary browser sessions. Preserve user-meta authentication/permission/session protections. Do not imply this is a sandbox for arbitrary installed plugin code.

Term-level edit permission grants complete term-meta get/list reads, including protected, unregistered and auth-denied fields. Keep keys and missing/empty/multiple-value semantics; list also includes values. Reads require no per-key write capability. Term-meta writes retain existing authorization, registration, schemas and sanitization.
