# AI Tool Layer — registry, read surface and safety spine

**Date:** 2026-11-02
**Status:** approved
**Slice:** 1+2 of 5 (merged by decision)

## Why

Swishfolio's AI can write but cannot look. `/ai/edit` proposes block changes the
editor applies, and the generation endpoints create pages — but nothing lets the
model ask what pages exist, what a page contains, or what the active palette is.
Every "AI Website Agent" request ("improve my SEO", "add a testimonials section
to my homepage") needs that, and none of it exists.

Separately, three server-side mutations run today with no way back:
`SetFrontPageHandler` writes three options, `SetNavigationHandler` **publishes** a
`wp_navigation` post, and `SetStyleVariationHandler` rewrites the global styles
post. `ExecutionContext` states the problem outright: *"no transaction spans
wp_insert_post calls, so the only possible undo is 'trash exactly these'."*

WPVibe was used as the reference for what operations an AI needs on a WordPress
site. It is **not** the reference for architecture: WPVibe is an MCP *server*
exposing WordPress to external clients, while Swishfolio is an AI *client* inside
WordPress calling a provider with the owner's own key. What transfers is its tool
catalogue; its transport and auth half does not.

## Decisions

1. **Internal tool layer, MCP-shaped.** Tools are defined in the shape the
   Abilities API needs (name, input JSON Schema, permission callback, meta) but
   registered internally. Nothing is exposed to external MCP clients in this
   slice. Phase 5 flips exposure flags rather than rewriting.
2. **Reversal by compensating journal**, not snapshots or staging. Prior state is
   captured in the form native to each target: forced post revisions, prior
   option values, created IDs.
3. **All five existing server handlers migrate onto the tool contract** in this
   slice, because they are exactly the mutations that need the journal.
4. **Rollback is run-level only.** Per-entry revert is free to add later given the
   row structure; nothing needs it yet.
5. **The approval gate rides the existing preview/accept pattern** — a nonce-bound
   hash of the plan the user was shown — not a separate approval queue. Our user
   is in the UI; WPVibe's remote-approval panel solves a problem we do not have.

## Out of scope (deliberate, from WPVibe's surface)

File read/write/search, WP-CLI dispatch, code snippets, page-builder bridges
(Bricks/Elementor/Beaver/Breakdance), self-update, white-label, live reload,
app-password policy, auth diagnostics, connection status. Roughly half of WPVibe,
all of it either developer-facing or transport for remote clients.

Four of WPVibe's read endpoints are also dropped, each for a reason:

| Dropped | Why |
|---|---|
| `/rendered-html` | 20 of 23 blocks are static `save()`; the block tree *is* the truth. HTML adds tokens, not information. |
| `/registered-meta` | Swishfolio blocks carry state in block attributes, not post meta. |
| `/last-change` | A polling aid for remote clients. Nothing polls in-process. |
| `/navigate` | Browser control for a remote agent. |

## Architecture

Two paths stay separate, and the separation is load-bearing.

**Path 1 — in-editor block editing, unchanged.** `/ai/edit` → `ActionSchema` →
the five `ActionValidator` stages → `src/js/ai/apply.js` dispatches inside
`registry.batch()`. PHP never mutates post content; native undo reverses it and
WordPress revisions are the history. **No journal, no rollback, no tool
involvement.** `ActionSchema` keeps `changes` an open object because per-block
constraints live in the capability manifests, which is correct for this path.

**Path 2 — server-side site operations, new.** Each operation is a tool with a
closed input schema. The Abilities API requires a per-ability `input_schema`, and
every site operation has a knowable shape, so the schema lives on the tool.

```
ToolRunner::run( name, input, ToolContext )
  resolve           → tool_not_found
  validate input    → schema_invalid  (field path in details)
  authorize         → forbidden       (against the object, not a named cap)
  confirm gate      → confirm_required (write_published | destructive only)
  journal capture   → prior state, before the write
  execute           → write_failed
  record            → append journal row (ok | error)
```

### The contract

```php
interface ToolInterface
{
    public function getName();        // 'swishfolio/site-info'
    public function getTitle();
    public function getDescription(); // prompt text AND ability meta.description
    public function getInputSchema(); // closed JSON Schema
    public function getSideEffect();  // read|write_draft|write_published|destructive
    public function authorize(array $input, ToolContext $context);
    public function execute(array $input, ToolContext $context);
}
```

This is `ServerActionHandlerInterface` plus a name, a description, a schema and a
side-effect class. `authorize()` keeps its existing rule: checked against the
specific object being written, never against a capability named in the request.

`ToolContext extends ExecutionContext` — it inherits plan-key resolution and the
`createdIds` ledger, and adds `runId`, `siteId` and `confirmed`. `siteId` is bound
from the current site and is never read from model output.

`ToolRegistry` holds tools by name, with `withDefaultTools()` mirroring
`ActionExecutor::withDefaultHandlers()`.

`ActionExecutor` becomes a façade: it maps the five legacy action types to tool
names and delegates to `ToolRunner`, preserving its public signature so
`PlanExecutor` and `GenerationController` are untouched. Generation therefore
becomes journalled and reversible for free.

### Input validation

A minimal JSON Schema subset validator, written in-plugin — the plugin has zero
runtime Composer dependencies and that constraint holds. Supported: `type`
(string/integer/number/boolean/array/object), `required`, `properties`,
`additionalProperties: false`, `enum`, `minimum`, `maximum`, `maxLength`,
`minItems`, `maxItems`, `items`, `default`. Anything else in a schema is a
programming error and raises. Errors carry a dotted field path.

### The Abilities bridge

When `wp_register_ability()` exists (core 6.9+, or the feature plugin), loop the
registry and register each tool with `show_in_rest => false` and
`meta.mcp.public => false`. The plugin floor is WP 6.7, so on most installs the
bridge no-ops and the internal registry is unaffected. Registering with both
exposure flags false is deliberate: it proves the shape against the real API
without opening anything.

Tool names use the `swishfolio/` prefix, matching the REST namespace rather than
the `swishfolio-core/` block prefix.

## The read surface

Six tools, all `read`, all floored at `current_user_can('edit_posts')`, all
scoped to the context's `siteId`.

| Tool | Returns |
|---|---|
| `swishfolio/site-info` | Title, tagline, locale, active theme, style variations, registered post types, front-page config, page/project counts |
| `swishfolio/list-content` | Paged list of pages/posts/projects: id, title, slug, status, type, modified, excerpt |
| `swishfolio/read-page` | The block tree of one post |
| `swishfolio/get-global-styles` | Active variation plus the resolved theme.json tokens the AI may reference |
| `swishfolio/list-media` | Attachment search: id, alt, mime, dimensions, URL |
| `swishfolio/get-block-capabilities` | Capability manifests, on demand |

**`read-page`** runs `parse_blocks()` and reduces the tree to the registry's own
vocabulary: block name, the attributes its manifest declares, children, addressed
by index path (`[0, 2]`) since there are no clientIds server-side. A block with no
manifest collapses to an opaque node — children preserved, attributes withheld —
so the model is never offered state the validator cannot check.

**`get-block-capabilities`** overlaps `ContextAssembler`'s bulk prompt injection
on purpose. Injecting all 28 manifests costs tokens on every request; a tool lets
the model fetch the two or three it needs. It is also the one tool that makes
later external MCP exposure useful.

**Every read tool is capped and paged.** `list-content` defaults to 20 with a
cursor; `read-page` caps depth and node count and returns a truncation marker.
Unbounded reads are the most common way a tool layer becomes unusable.

## The safety spine

### Storage

One table, `{$wpdb->prefix}swishfolio_ai_journal`. `$wpdb->prefix` is already
site-scoped, so multisite isolation needs no extra column.

```
id, run_id, seq, user_id, tool, side_effect,
summary, input (capped 4KB), target_type (post|option), target_id,
prior, result, error_code, reverted_at, revert_error, created_at
```

Access goes through `JournalRepositoryInterface` — `WpdbJournalRepository` in
production, an in-memory implementation in tests. This mirrors
`CredentialStoreInterface` / `WordPressCredentialStore`, and it is what makes the
spine testable at all: the unit suite runs against stubbed WP functions, not a
real WordPress test harness, so `$wpdb` is not available to stub meaningfully.

### Inverse payloads

| Target | Capture | Rollback |
|---|---|---|
| post update | force `wp_save_post_revision()` **before** the write → `{"revision": 123}` | `wp_restore_post_revision(123)` |
| post create | `{"created": 123}` | `wp_trash_post()` — trash, never delete |
| option | `{"option": "...", "prior": ..., "existed": true}` | `update_option`, or `delete_option` when `existed` is false |

Two details that cause silent corruption if left implicit:

- **`existed` is stored separately from the prior value.** An option whose prior
  value was `false` and an option that did not exist are otherwise
  indistinguishable, and rollback would leave a phantom row.
- **`wp_save_post_revision()` returns null** when nothing changed or revisions are
  disabled via `WP_POST_REVISIONS`. When it does, the prior `post_content` and
  `post_title` are stored inline instead. Verified against core: `wp_global_styles`
  (`post.php:522`) and `wp_navigation` (`post.php:588`) both support revisions, so
  the inline path is a site-configuration fallback, not a per-type one.

### Rollback

`swishfolio/rollback-run` (side effect `destructive`) walks a run's entries in
reverse `seq`. A failed entry is recorded in `revert_error` and the walk
**continues** — stopping half way leaves the site in a worse state than
finishing — and the tool reports a partial result.

### Audit log

The same table, read grouped by `run_id`, surfaced under the existing Swish AI
menu. Append-only is enforced by the repository exposing only `append()`,
`markReverted()` and query methods — no update, no delete.

The single exception is time-based pruning on a scheduled event, default 90 days,
filterable to `0` for never. That is bulk expiry of aged rows, not mutation of
individual records; an unbounded table on shared hosting is a real failure mode.

### Approval gate

`ToolRunner` refuses any `write_published` or `destructive` tool unless the
request carries a short-lived, nonce-bound hash of the plan the user was shown.
Preview text is **derived** from the tool's description plus its validated input
and resolved target title ("Set the front page to 'Home'"), so no tool implements
a `preview()` method.

### Error contract

Every failure becomes a `ToolError` with a stable machine `code`, a human
`message`, and `details` carrying the failing field path:
`tool_not_found`, `schema_invalid`, `forbidden`, `confirm_required`,
`target_missing`, `write_failed`, `rate_limited`.

The model sees the code and can correct itself instead of guessing; the journal
stores it. Existing `ValidationException` and `ExecutionException` map into this
rather than being replaced.

## Testing

**Keystone, written first:** generate → journal → rollback → the site is
byte-identical to its pre-run state. Compares `post_content` and `post_title` on
touched posts, the three front-page options, and the global styles post. It is
the only test that proves the spine; everything else localises failures.

**`tests/Unit/Tools/Journal/`** — the inverse matrix:

- post update → revision captured before the write; rollback restores exactly
- `wp_save_post_revision()` returns null → inline fallback; rollback still exact
- option that did not exist → rollback **deletes** it, does not write `false`
- option whose prior value *was* `false` → rollback restores `false`
- entry 3 of 7 fails → 1, 2, 4–7 still revert, row 3 carries `revert_error`,
  result reports partial
- append-only → repository's public API is exactly append/markReverted/query,
  asserted by reflection

**`tests/Unit/Tools/`** — registry resolution and `tool_not_found`; table-driven
schema rejection with field paths; authorization against the specific object; read
caps (50 fixture pages → 20 plus cursor; deep tree → truncation marker);
`read-page` collapsing an unmanifested block to an opaque node.

**Gate** — `write_published` with no plan hash → `confirm_required` and **nothing
written**; stale hash → same; read tools need no hash.

**Migration proof** — the assertions in the existing handler tests stay as they
are. Imports and instantiation change, because the classes moved; any change to
an *assertion* means the migration changed behavior and is a defect.

**Abilities bridge** — `wp_register_ability()` does not exist on the 6.7 floor, so
the adapter is tested against a stub: each tool yields a well-formed definition
with a callable permission callback and both exposure flags false.

**Evals (recorded)** — given the tool list, does the model pick the right tool?
"What pages do I have?" → `list-content`; "make Home the front page" →
`set-front-page`. In practice a tool layer fails less often from bugs than from
descriptions the model cannot choose between, which is why `getDescription()` is
in the contract.

**One Playwright flow** — change the front page via a tool, see the audit row,
click rollback, confirm the front page reverted.

## Not in this slice

Media engine (slice 3), write breadth — projects CPT, posts, taxonomies, menus,
options allowlist, theme.json tokens (slice 4), async jobs and progress
(slice 5), external MCP exposure (phase 5).
