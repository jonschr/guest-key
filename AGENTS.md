# Guest Key

## Agent-facing wording

- Keep instructions, command help, guides, UI messages, and current product descriptions agent- and client-neutral. Use “your AI agent” and describe the capability or protocol needed.
- Do not assume a particular app, model, terminal, internal tool name, or provider-specific workflow. Explain the supported operation using standard HTTP, JSON, REST, or MCP terminology as appropriate.
- Name an agent or client only when necessary for an integration-specific explanation or a useful optional example. Provide alternatives or the equivalent generic workflow; never make that example a prerequisite for other clients.
- If a capability is optional, explain the fallback. If a required capability is unavailable, tell the user rather than claiming success.
- Historical release notes and QA evidence may name the tools actually used; do not rewrite that history as current instructions.

## Licensing scope

Licenses, trials, pending verification, and purchase matching are scoped to the owning WordPress administrator on the current site. Use `Guest_Key_License::option_name()` and `pending_name()` for current storage; the `OPTION`/`PENDING` constants refer only to legacy site-wide migration. Owner-controlled `shared_users` grants access without transferring license management. Authorization during native authentication must receive an explicit user ID and perform no network/capability/current-user resolution. Keep license controls visible to unlicensed administrators, but never issue credentials without their own or shared entitlement.

## Post metadata read policy

Post-level `edit_post` permission grants complete read access through `post meta get/list`, including protected and unregistered stored keys. Do not apply per-key write permissions, registration, or REST-exposure filters to these reads. Preserve `exists` and value-list response semantics.

For writes, underscore-only protection is not an authorization rule. `can_write_meta()` supplies an allowed baseline only during the specific post/key/action capability check, before existing native authorization filters. Registered/global/subtype auth callbacks, capability filters, readonly schemas, value validation and native metadata sanitizers/hooks still apply. Use the same helper in individual metadata writes, combined `post save` preflight and field descriptions. Never change protection or permissions globally, special-case plugin names, or claim generic writes run validation found only in plugin-specific forms.
