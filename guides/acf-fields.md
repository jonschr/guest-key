# ACF fields on existing posts

Use `post acf list` with `post_id` to discover applicable fields. `post acf get`, `post acf validate` and `post acf update` take that `post_id` and a stable `field_key`. Validate and update require a string `value`.

```json
{
  "commands": [
    {
      "command": "post acf update",
      "input": {
        "post_id": 6282,
        "field_key": "field_68263783c1a61",
        "value": "98%"
      }
    }
  ]
}
```

The equivalent ability is `guest-key/manage-acf-fields` with `action` set to `list`, `get`, `validate` or `update`. Use normal Guest Key authentication and command access. ACF must be active, version 6.8.11 or later, with its form-save APIs available. Public ACF REST exposure is unnecessary and field-group REST settings stay unchanged.

Currently supported: top-level text fields on existing content posts. Revisions, attachments, ACF definitions, conditional fields, nested fields and other field types are unsupported. Discovery reports restrictions; unsupported fields require the WordPress editor. Fields removed by ACF preparation or the allowed-field filter are excluded. Missing fields, inapplicable groups, readonly/disabled fields and object permission failures return explicit errors.

Validation runs required and text-type checks, `acf/validate_value` variations and `acf/validate_save_post`, including on drafts. Validation errors contain ACF's input names (`acf[field_key]`) and messages. Save uses `acf_save_post`, preserving field references and field-specific update hooks. The response contains the resulting unformatted value. WordPress metadata sanitizers also run. A storage verification failure reports current value/reference data; inspect these before retrying because hooks may have made changes.

Each update writes one field. A command batch stops at the first error and returns earlier completed results and `failed_index`; it does not roll back prior commands. Site-defined PHP hooks can have their own side effects, just as when editing through WordPress.

Generic `post meta` and `post save` metadata permissions retain their existing behavior. When ACF denies a generic metadata operation, use this explicit pathway. `auth_allowed: true` describes an earlier metadata authorization stage; later capability mapping can still deny the operation.

## Implementation evidence

Inspected installed ACF Pro 6.8.11 source:

- `includes/forms/form-post.php`: restrict input before validation and `acf_save_post`.
- `includes/acf-form-functions.php`: `acf_filter_save_values` applies location rules and `acf/form/post/allowed_field_keys`. Guest Key supplies no signed render lists and independently requires an applicable group, so that filter cannot widen access to unrelated groups.
- `includes/acf-field-functions.php`: `acf_prepare_field` applies field hiding/preparation filters. Guest Key additionally rejects disabled, readonly and conditional fields.
- `includes/validation.php`: `acf_validate_save_post` invokes form validation and the per-field validation pipeline.
- `includes/acf-value-functions.php`: ACF saves values and references through its update hooks. `acf_save_post` alone does not report persistence failure, so Guest Key observes the final native metadata value and verifies storage.

No capability guard is removed, no `do_not_allow` override is added, and failed ACF operations never fall back to raw metadata writes. This pathway uses ACF's editor/form APIs, rather than ACF's separate optional abilities provider.

See [ACF's 6.8.11 security release explanation](https://www.advancedcustomfields.com/blog/acf-6-8-11-security-release/).

Run the local regression with ACF active:

```sh
wp --user=admin eval-file tests/acf-fields.php
```

For a local test site without active ACF, set `GUEST_KEY_ACF_TEST_PLUGIN` to an existing local ACF entrypoint. The test loads it only within that CLI process, forces ACF's untrusted submitted-value context, creates temporary fixtures and removes them afterwards. It does not activate or install ACF on the site.
