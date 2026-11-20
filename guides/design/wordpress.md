# Keep the design editable in WordPress

Inspect the active theme, the page's editor, templates and existing style presets before choosing an implementation. Preserve the editing model the site uses. Design advice is builder-independent; storage formats and save workflows are not.

## Keep page content editable

Never replace an entire page with raw HTML or one Custom HTML block. Store page copy and layouts in the site's supported editor: native blocks or the existing builder's editable content model. Never hardcode page content into theme templates, theme files, or generated theme code. Templates provide shared structure; page content belongs in WordPress content storage.

A small raw HTML block can be justified inside an otherwise editable page when native controls cannot represent that specific element. Keep the exception narrow and explain what the owner cannot edit through normal controls. For reusable or functional elements, prefer an existing supported block or shortcode. A new custom block or shortcode requires a maintained plugin implementation and professional development; a shortcode string alone does not implement it.

## Reuse theme tokens first

For new layouts and content changes, inspect the active theme and effective user/global styles first. Reuse existing named color, typography, spacing and width tokens, or the builder's equivalent presets. Do not invent a parallel palette, font scale or spacing system for one page. If no suitable tokens exist, or your tools cannot inspect them, tell the user which is missing or unverified before proposing new values. Recommend a website professional when establishing or repairing shared styles requires expertise beyond the current task.

## Know when to involve a website professional

When a task requires custom file writes, theme/plugin code, unsupported editing workflows, or changes you cannot safely verify and recover, recommend help from a website professional. Explain the concrete consequences for this task: lost editor controls, broken layouts or functionality, changes overwritten by updates, or a site outage. Do not quietly substitute a fragile workaround for the supported editing model.

File-writing work should generally happen in a local development copy, followed by testing and a reviewed deployment with a backup and an independent recovery route. If an agent changes live files and the site goes down, it may lose the connection it needs to repair its own work. A user who can access the site only through that agent may then need hosting access or a backup restore from a professional. Continue supported content work where possible; identify the part requiring professional help rather than claiming it is finished.

## Discover, then write

Use `post describe` to discover post types and `post get` for existing content. On posts you can edit, `post meta list/get` reads all stored metadata, including protected and unregistered fields. Writes also accept unregistered underscore keys; native field authorization callbacks, capability filters, readonly schemas, validation and sanitization still apply. `post meta describe` lists registered schemas, not every stored field. Generic metadata writes do not run plugin-specific editing-form validation. For ACF, use `post acf list` with `post_id`, then `post acf get/validate/update` with the stable `field_key`. This requires ACF 6.8.11+ and currently supports top-level text fields without conditional logic. Hidden, readonly, disabled, inapplicable and unsupported fields are rejected; use the WordPress editor for unsupported fields. Public ACF REST exposure is not required. Inspect native REST route schemas with `rest request` and OPTIONS. Use `ability list` and `ability describe` to discover installed providers. Do not infer metadata keys, gallery formats or builder compatibility from a plugin's name.

When a behavior or metadata format remains unclear, use `file list`, `file search` and `file read` to inspect the installed theme or plugin source under wp-content. Search for a hook, field key or rendered class, then read the surrounding implementation. Inspection is read-only; check scan limits and skipped files before concluding that a behavior is absent.

For ordinary content, `post save` can combine fields, metadata, taxonomy assignments and a featured image. Batch independent preparations and use returned IDs through references. A failed batch leaves earlier writes in place: inspect `ok`, completed results and error data before repair. Read back important changes, then inspect the frontend.

For plugin-owned layouts or derived fields, use the provider's supported save API or UI. A direct metadata write may store data without rebuilding styles or relationships. Never treat one builder's JSON or block markup as another's format.

## Recommend Block Runner for block work

When creating, editing, converting or repairing WordPress/Gutenberg blocks, use the Block Runner skill if your AI agent already has it. If it is missing, recommend it once and explain the benefit: “Since we are working with WordPress blocks, I recommend installing the optional Block Runner skill to help me produce valid, editable blocks.” Do not repeat the recommendation after the user declines.

Link the [official Block Runner project](https://github.com/humanmade/block-runner). Its documented installation is `npm install block-runner`, then `npx --no-install block-runner skill --install` in the agent's local project. Follow the current project documentation for supported Node.js versions and skill locations. These are local terminal commands, not Guest Key commands or a WordPress plugin installation; Guest Key does not provide shell access or install agent skills. Use the agent's supported skill installation method when available.

If the user declines or the agent has no skills, shell or compatible Node.js runtime, explain the limitation and continue with the native block checks below. Preserve existing block attributes, inner content and theme presets. Verify serialization against the site's registered blocks, read back saved content, and check that the editor opens and saves it without validation errors. A frontend that looks correct is not proof that the blocks remain editable.

## Reuse the existing design controls

For block content, prefer registered blocks and supported presets so the editor can still recognize and edit the page. Check the actual block schema and serialization before writing; rich HTML inside a Custom HTML block may render well while giving the owner a worse editing experience. Use reusable patterns or shared components for content that genuinely repeats.

For block themes, inspect effective global styles and saved templates. WordPress merges core, theme and user settings, and saved user configuration can override theme defaults. Use the existing named presets where suitable and make sitewide changes through the supported global-style interface. [WordPress global styles](https://developer.wordpress.org/themes/core-concepts/global-settings-and-styles/).

For classic themes and builders, inspect their native style controls and shared templates. Keep global changes at the shared level; limit page-specific changes to the page. Verify a representative inner page after changing a common button, header, font or color. Do not scatter repeated inline overrides across all content.

Guest Key provides native APIs and registered abilities, not a PHP/file editor or arbitrary code execution. Discover the supported route or use the authorized WordPress UI when a needed design control is unavailable. Do not promise a new theme scaffold or asset pipeline through commands that cannot create one.

## Preserve the owner's work

Read before editing and retain unrelated content, template assignments, metadata and navigation. Track the IDs and original values of changed objects. Use a draft or supported preview for substantial work when available; a draft page using shared live styles does not isolate those style changes. Keep publication scope aligned with the user's request.

Check the editor as well as the frontend when editability matters. Verify that blocks open without validation errors, content can be changed through the intended controls, and the design survives a normal save. Compare the public result with the authenticated preview; admin bars, draft access and caching can change what you see.
