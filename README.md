# Guest Key

One-click, temporary access for Claude, Codex, and other AI agents working on live WordPress sites. Credentials expire after two hours by default and can be revoked sooner. AI task traffic travels directly between your agent and your website.

Connect through authenticated REST commands, an optional MCP endpoint, or temporary WordPress browser sign-in.

## Tools

| Command group | Purpose |
| --- | --- |
| `post` | Discover types; search across types; create, edit, trash, and restore content |
| `post save` | Save content, custom fields, taxonomy terms, and a featured image together |
| `post meta` | Discover field schemas and manage authorized metadata |
| `term` | Discover taxonomies and manage terms and assignments |
| `media upload` | Upload media with captions and alt text |
| `option`, `theme mod` | Manage supported site options and theme modifications |
| `user meta`, `term meta` | Discover and manage authorized metadata |
| `plugin`, `theme`, `update` | Native package installation, activation, and updates |
| `file list/read/search` | Read-only source inspection under wp-content |
| `rest request`, `ability` | Discover and call core/plugin routes and registered abilities |
| `design guide` | Focused WordPress design guidance |

`GET /wp-json/guest-key/v1/help` provides command names and input schemas. `POST /wp-json/guest-key/v1/run` runs up to 20 commands sequentially, stopping on failure. MCP exposes the same interface through `guest-key-help` and `guest-key-run`.

Access follows the issuing administrator’s permissions. No direct file writes, shell, or SQL console. Native installs and updates remain available. Expiration stops future requests; it does not undo changes. Build new websites locally.

Requires WordPress 6.9+, PHP 7.4+, HTTPS, and administrator application passwords. New verified trials last three days.

[Download ZIP](https://github.com/jonschr/guest-key/releases/latest/download/guest-key.zip)
