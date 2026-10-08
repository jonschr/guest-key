# Keep the design editable in WordPress

Inspect the active theme, the page's editor, templates and existing style presets before choosing an implementation. Preserve the editing model the site uses. Design advice is builder-independent; storage formats and save workflows are not.

## Discover, then write

Use `post describe` to discover post types, `post get` for existing content and `post meta describe` for registered fields. Inspect native REST route schemas with `rest request` and OPTIONS. Use `ability list` and `ability describe` to discover installed providers. Do not infer metadata keys, gallery formats or builder compatibility from a plugin's name.

When a behavior or metadata format remains unclear, use `file list`, `file search` and `file read` to inspect the installed theme or plugin source under wp-content. Search for a hook, field key or rendered class, then read the surrounding implementation. Inspection is read-only; check scan limits and skipped files before concluding that a behavior is absent.

For ordinary content, `post save` can combine fields, metadata, taxonomy assignments and a featured image. Batch independent preparations and use returned IDs through references. A failed batch leaves earlier writes in place: inspect `ok`, completed results and error data before repair. Read back important changes, then inspect the frontend.

For plugin-owned layouts or derived fields, use the provider's supported save API or UI. A direct metadata write may store data without rebuilding styles or relationships. Never treat one builder's JSON or block markup as another's format.

## Reuse the existing design controls

For block content, prefer registered blocks and supported presets so the editor can still recognize and edit the page. Check the actual block schema and serialization before writing; rich HTML inside a Custom HTML block may render well while giving the owner a worse editing experience. Use reusable patterns or shared components for content that genuinely repeats.

For block themes, inspect effective global styles and saved templates. WordPress merges core, theme and user settings, and saved user configuration can override theme defaults. Use the existing named presets where suitable and make sitewide changes through the supported global-style interface. [WordPress global styles](https://developer.wordpress.org/themes/core-concepts/global-settings-and-styles/).

For classic themes and builders, inspect their native style controls and shared templates. Keep global changes at the shared level; limit page-specific changes to the page. Verify a representative inner page after changing a common button, header, font or color. Do not scatter repeated inline overrides across all content.

Guest Key provides native APIs and registered abilities, not a PHP/file editor or arbitrary code execution. Discover the supported route or use the authorized WordPress UI when a needed design control is unavailable. Do not promise a new theme scaffold or asset pipeline through commands that cannot create one.

## Preserve the owner's work

Read before editing and retain unrelated content, template assignments, metadata and navigation. Track the IDs and original values of changed objects. Use a draft or supported preview for substantial work when available; a draft page using shared live styles does not isolate those style changes. Keep publication scope aligned with the user's request.

Check the editor as well as the frontend when editability matters. Verify that blocks open without validation errors, content can be changed through the intended controls, and the design survives a normal save. Compare the public result with the authenticated preview; admin bars, draft access and caching can change what you see.
