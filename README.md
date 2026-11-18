# Guest Key

Get your AI agent working on your WordPress site in one click. Guest Key gives it temporary access to edit content, manage media, and handle site administration without sharing your WordPress password.

[Download Guest Key](https://github.com/jonschr/guest-key/releases/latest/download/guest-key.zip)

## Connect in one click

Click **Copy Guest Key**, paste the connection details into your AI agent, and get to work. Your agent can connect through native WordPress REST commands, an optional MCP endpoint, or a temporary browser sign-in. Use an agent that supports one of those methods; no particular AI provider or app is required.

AI task traffic travels directly between your agent and your website.

## Give access a clear finish line

Guest Keys expire after two hours by default. Choose a duration from 30 minutes to 24 hours, or revoke access sooner. Creating a new key replaces the previous one, and access cannot outlast the account’s trial or license entitlement.

Expiration stops future requests. It does not undo changes already made to the site.

## WordPress workflows, without cowboy coding

Guest Key helps your agent do the work you would do through WordPress: update a page, upload an image, change a supported setting, or organize the media library. It works through native APIs, registered abilities, and the WordPress interface, following the issuing administrator’s permissions and the plugin’s access restrictions.

There is no direct file-writing tool, shell, or SQL console. Your agent can inspect theme and other plugin source to understand the site, then use supported WordPress operations to make changes. Guest Key’s own implementation files are excluded; its public Markdown guides and skill instructions remain readable. Build new websites and custom code locally.

Temporary Guest Key credentials and browser sessions cannot install, update, delete, activate or deactivate plugins, switch or modify themes, update WordPress core, or change the access duration. Media uploads remain available. Normal administrator sessions retain site maintenance and MCP Adapter setup. These restrictions govern supported WordPress operations; they do not sandbox arbitrary third-party plugin code.

Work that requires writing code files should generally happen locally with a website professional, testing and an independent recovery route. A live-site outage can cut off an agent’s access before it can repair the problem.

## Choose who can use it

On a new installation, the first successful paid-license activation hides Guest Key from the other administrators and identifies the activating owner as their contact. Existing installations keep their current visibility settings, and later activations do not reset an owner’s choices. Administrators with their own purchased licenses always retain access.

Licenses and verified trials belong to the administrator who activates them. License owners can enable shared access for other administrators on the same site. When several owners manage access, the last saved selection applies; each owner keeps control of their own license and always retains Guest Key access.

Hidden administrators see a contact, purchase, and license-entry page. Activating their own purchased license gives them full controls alongside the other owners. An administrator needs their own entitlement or shared access before Guest Key will issue a credential.

## Tools

| Command group | Purpose |
| --- | --- |
| `post` | Discover types; search across types; create, edit, trash, and restore content |
| `post save` | Save content, custom fields, taxonomy terms, and a featured image together |
| `post meta` | Read and edit custom fields, including underscore keys; honor declared field rules |
| `term` | Discover taxonomies and manage terms and assignments |
| `media upload` | Upload media with captions and alt text |
| `option`, `theme mod` | Manage supported site options and theme modifications |
| `user meta` | Manage authorized metadata while protecting roles, authentication and sessions |
| `term meta` | Read every stored term field; retain per-key write authorization |
| `cron` | List events/schedules, schedule tasks, run or delete selected occurrences |
| `plugin`, `theme`, `update` | Discover package operations; mutations require a normal administrator session and are denied to temporary access |
| `file list/read/search` | Read-only source inspection under wp-content |
| `rest request`, `ability` | Discover and call core/plugin routes and registered abilities |
| `design guide` | Focused WordPress design guidance |

## Get started

Requires WordPress 6.9+, PHP 7.4+, HTTPS, and administrator application passwords. New verified trials last three days.

Install and activate Guest Key, open its settings, and verify your email for a trial or enter your license key. Then click **Copy Guest Key** and paste it into your AI agent.

Guest Key installs the optional official MCP Adapter through WordPress’s native installer. If automatic installation is blocked, install and activate MCP Adapter from Plugins → Add New, then retry. Native REST commands and temporary browser sign-in remain available without MCP. After an update, an outstanding MCP setup failure is retried once on the next normal administrator visit. The warning also offers **Retry MCP setup** without creating a new Guest Key.

<details>
<summary>Developer reference and access boundaries</summary>

`GET /wp-json/guest-key/v1/help` provides command names and input schemas. `POST /wp-json/guest-key/v1/run` runs up to 20 commands sequentially, stopping on failure. MCP exposes the same interface through `guest-key-help` and `guest-key-run`.

File scans return `next_cursor` after result, byte, entry, or time limits: repeat the same filters with `cursor` until it is `null`. `depth: 1` inspects immediate children; `depth: 2` includes the next level. Paths are relative to `wp-content`. Check each page’s skipped-file counts. Use small `limit`/`length` values and separate large reads to fit client output limits; never retry writes blindly after a clipped response. Exact argument names and pagination conventions are included in command help.

Permission to edit a post grants read access to all its stored metadata and permits custom-field writes, including unregistered underscore (`_`) keys. Registered/native field authorization, readonly schemas, validation, and sanitization still apply to writes. Missing keys remain distinct from empty values. REST, MCP, Abilities, and combined `post save` share this policy. Plugin-specific form validation and derived-data rebuilding require the plugin’s own API or UI.

User management permits listing and basic profile edits through the API. Account creation/deletion and role, password, email, authentication, or permission changes require a normal administrator session. Temporary browser sessions cannot use account-editing screens. User-meta credential/permission protections remain enforced. Cron runs existing callbacks synchronously and preserves recurrence; callback effects are not rolled back on failure.

</details>
