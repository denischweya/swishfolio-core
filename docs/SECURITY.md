# Security model

How Swishfolio Core constrains AI-assisted block editing, and what those
constraints do and do not cover.

The premise: **a language model is an untrusted input source.** Everything a
provider returns is treated the way a form POST from a stranger would be —
parsed, checked against a fixed allowlist, range-checked, sanitised, and
rejected wholesale on the first thing that does not fit.

This document describes version 1.0.3. Where a protection does not exist, that
is stated rather than implied.

---

## 1. The shape of the thing

```
Editor selection ──▶ POST swishfolio/v1/ai/edit
                        │
                        ├─ is_user_logged_in()
                        ├─ current_user_can( 'swishfolio_use_ai' )
                        ├─ current_user_can( 'edit_post', $postId )   ← verified, not trusted
                        └─ per-user hourly rate limit
                        │
                        ▼
                     ContextAssembler ──▶ AI provider ──▶ actions[]
                        │
                        ▼
                     ActionValidator: envelope → target → allowlist → value → sanitize
                        │                         any failure rejects ALL of it
                        ▼
                     validated actions returned in the HTTP response
                        │
                        ▼
                     the editor applies them client-side; the user presses Update
```

Read the arrows carefully. There is no branch in which PHP writes the AI's
output anywhere near `wp_update_post()`.

---

## 2. PHP never mutates post content

This is the load-bearing decision, not a detail.

`/ai/edit` **validates and returns**. `AIManager::edit()` calls the provider,
runs the validator, and returns the validated array; `AIController::edit()`
passes that straight to `rest_ensure_response()`. No `wp_insert_post`,
`wp_update_post` or content-writing `update_post_meta` call exists anywhere
under `src/AI/` or `src/REST/`.

What this buys:

- **No server-side write path to exploit.** An attacker who could somehow make
  a provider return arbitrary actions still cannot make PHP write them. The
  worst outcome is a response body the editor declines to apply.
- **Native undo works.** Changes are dispatched into the block editor's data
  store, so Ctrl+Z reverses them and WordPress revisions remain the history.
- **Review is just "not dispatching".** Declining a proposed change requires no
  rollback, because nothing was applied.
- **Nothing is persisted until the author presses Update**, by their own hand,
  under their own capabilities, through WordPress's own save path.

The single database write in the AI path is the usage counter option
(`src/AI/UsageLogger.php`), which contains no content.

---

## 3. The capability manifest is the allowlist

Every AI-editable block ships an `ai.json` manifest next to its `block.json`.
The manifest names the attributes the AI may touch and the values each may
take. 23 Swishfolio blocks have one; five core blocks (`core/paragraph`,
`core/heading`, `core/list`, `core/list-item`, `core/button`) have content-only
manifests under `src/AI/Manifests/core/`.

```json
{
  "version": 1,
  "tier": "full",
  "purpose": "hero",
  "summary": "Full-bleed opening section…",
  "capabilities": {
    "content": { "heading": { "type": "text", "maxLength": 120, "role": "headline" } },
    "layout":  { "contentAlign": { "enum": ["left", "center", "right"] } },
    "style":   { "backgroundColor": { "type": "token", "tokenSet": "color" } }
  }
}
```

The important property is **negative**. Hero carries 68 attributes; its
manifest exposes roughly 15. `decoShape1Color` is not forbidden by a rule that
could be bypassed — it is absent, so `BlockCapability::getField()` returns
`null` and the allowlist stage throws. Unreachable by construction beats denied
by policy.

The same manifest is simultaneously what the AI is *told* it can change and
what the validator *enforces*, so the two cannot drift apart.

A block with no manifest cannot be AI-edited at all. A malformed manifest is
discarded rather than raising, so a bad JSON file disables AI for one block
instead of breaking the editor (`BlockRegistry::register()`).

### Design tokens, not values

Colour, spacing and font-size fields are declared `"type": "token"`. The AI
emits a slug (`accent-2`), never a literal (`#FF6B6B`). `TokenResolver` reads
the live `theme.json` preset sets and the value stage rejects any slug that is
not in them — **a slug absent from the active theme is a validation failure,
never a fallback**. Only after a slug passes is it expanded to the
`var(--wp--preset--color--accent-2)` form blocks actually store.

This is why an AI edit survives a style-variation switch, and it also removes
free-form CSS values as an input class entirely.

---

## 4. Five-stage validation, all or nothing

`ActionValidator` runs five stages in order (`src/Actions/Stages/`). **Any
failure rejects the entire response.** Partial application is worse than none:
half an edit leaves a page in a state no one asked for and no one can describe.

| # | Stage | What it proves |
|---|---|---|
| 1 | **Envelope** | `schemaVersion` is exactly `1.0`; `actions` is a zero-indexed list; the count is within the cap (25 by default, filter `swishfolio_ai_max_actions`); every action has a known `type` and all keys that type requires. |
| 2 | **Target** | Every `clientId` and `parentClientId` appears in the block tree the editor submitted. A `remove_block` must additionally target a block at or beneath the selection root, walked through `parentClientId` with cycle detection. |
| 3 | **Allowlist** | Every attribute named exists in that block's manifest. `content`-tier blocks reject `update_block_attributes` outright. `update_block_content` requires the block to actually have a richtext field. `insert_block` requires the child to be in the parent's `children.allowed` *and* to have a manifest of its own, and respects `children.max`; `remove_block` respects `children.min`. |
| 4 | **Value** | Enum membership; numeric `min`/`max`; `maxLength` on text and content; boolean coercion from a fixed set; token-set membership against the live `theme.json`; URL scheme restricted to `http`, `https`, `mailto`, `tel`. This stage also performs the one transformation in the pipeline — expanding a validated token slug to its storage value. |
| 5 | **Sanitize** | `wp_kses_post()` on richtext and on `update_block_content`; `esc_url_raw()` on URLs; `sanitize_text_field()` on text. Enums, tokens, numbers and booleans were already normalised in stage 4 and are passed through as the typed values they now are. |

Failures raise a `ValidationException` carrying the stage slug and the JSON path
of the offending value. The REST layer returns HTTP 422 with the message *"AI
could not complete this request. Nothing was changed."* — literally true,
because nothing was.

Each rejection also increments a per-stage failure counter in the usage option,
so a prompt regression that starts producing invalid output is visible rather
than silent.

### Why the target stage is enough

The server cannot independently prove that a client ID is real: it only knows
what the editor sent in `blockTree`. That is acceptable **because the server
never writes**. The check that closes the loop runs on the side that can
actually verify it — the editor re-checks every returned client ID against the
set it sent before dispatching anything. The server-side stage catches a
provider that invents IDs; the client-side re-check catches everything else.
See the caveat in §8: that editor surface does not ship in 1.0.3.

---

## 5. Access control

### Capabilities

| Route | Method | Gate |
|---|---|---|
| `/ai/edit` | POST | `is_user_logged_in()`, then `current_user_can('swishfolio_use_ai')`, then `current_user_can('edit_post', $postId)` |
| `/ai/test-provider` | POST | `current_user_can('manage_options')` |
| `/ai/usage` | GET | `current_user_can('manage_options')` |
| `/ai/settings` | GET, POST | `current_user_can('manage_options')` |

**The post ID arrives in the request body and is verified, never trusted.**
`AIController::canEditPost()` runs `current_user_can('edit_post', $postId)`
against the ID the caller supplied, so a user cannot borrow someone else's post
context by naming it.

`swishfolio_use_ai` is granted through a `user_has_cap` filter to anyone holding
`edit_posts`, rather than being written into roles. Nothing is persisted,
nothing needs migrating, and a site owner can revoke it with a higher-priority
filter — see `docs/PRIVACY.md` §6 for the snippet. **By default this includes
Contributors.** If that is not what you want on your site, apply that filter.

### Nonces

The AI routes carry no explicit `wp_verify_nonce()` call. They are protected by
WordPress's own REST authentication: a cookie-authenticated request must present
a valid `X-WP-Nonce` header or `rest_cookie_check_errors()` rejects it before
any permission callback runs, which is what makes the routes CSRF-safe from a
browser. Requests authenticated another way (Application Passwords, for
instance) use that scheme's credentials instead — as with every WordPress REST
route.

The **public** form endpoint is different, because it must accept anonymous
submissions. It carries an explicit per-form nonce check in its permission
callback (`wp_verify_nonce($nonce, 'swish_form_submit_' . $formId)`), a honeypot
field, and an IP-keyed rate limit of 10 submissions per hour. The admin
templates that write settings each call `check_admin_referer()` and a capability
check before touching `$_POST`.

### Rate limiting

`/ai/edit` is capped at **30 requests per hour per user**, counted in the
transient `sfcore_ai_rate_<user ID>` and filterable through
`swishfolio_ai_rate_limit`. This is a cost control as much as a security
control: without it, one authenticated Contributor can spend the site owner's
provider budget.

Returning `0` from that filter **removes** the limit rather than blocking
requests. To stop AI requests entirely, delete the stored API key.

---

## 6. Credential handling

The provider API key is encrypted with AES-256-CBC before it is written to the
`swishfolio_ai_settings` option, using a key derived as `sha256(AUTH_KEY)` from
your `wp-config.php` salts (`src/Services/EncryptionService.php`) — the same
service that already stored ESP credentials and the SMTP password.

The raw key never leaves PHP:

- `Settings::get()` — used by the REST layer — returns `hasKey` as a boolean and
  never the key itself.
- `Settings::getKey()` is called only when constructing a provider request.
- Saving with an empty `api_key` preserves the stored one, so a masked form can
  be resubmitted without the key ever making a round trip through the browser.

Two operational caveats:

1. If `AUTH_KEY` is undefined, `EncryptionService` falls back to a fixed string
   present in this plugin's public source. Confirm your `wp-config.php` defines
   the standard salts.
2. Rotating `AUTH_KEY` makes existing ciphertext undecryptable. Re-enter
   credentials after a salt rotation.

Encryption at rest protects the database row. It does not protect against
someone who already holds `manage_options`: that user can use the key through
the plugin, and can change the endpoint it is sent to.

---

## 7. Supply chain

- **Zero runtime Composer dependencies.** `composer.json` requires only
  `php: >=7.4`. Provider HTTP goes through `wp_remote_post()`, exactly as the
  existing email provider layer uses `wp_remote_request()`. No SDK is vendored,
  so there is no third-party HTTP client to keep patched and nothing to conflict
  with another plugin's copy.
- **No remote code execution.** The plugin contains no `eval()`, no dynamic
  function construction from strings, and no code fetched at runtime and
  executed.
- **No remote assets.** Nothing is enqueued from a CDN or any external host;
  every script, style and font the plugin loads is a local file.
- **All bundled JavaScript is first-party or `@wordpress/*`.** The only npm
  runtime dependency is `@wordpress/icons`. Compiled block bundles ship
  alongside their ES6 and SCSS sources, so every byte in `build/` can be
  regenerated from the sources in `src/` with `npm run build`.

---

## 8. What this model does not cover

Stated plainly, because a security document that only lists strengths is not
one.

- **Prompt content is not access-controlled beyond the block itself.** A user
  who may edit a post may send that post's selected block — including its text —
  to the configured provider. If some blocks on your site carry sensitive copy,
  that is the boundary to think about.
- **The manifest bounds *what* changes, not *whether* a change is a good
  idea.** Validation proves an edit is structurally legal and in range. It
  cannot prove the new headline is accurate or appropriate. That is what the
  review-before-apply step is for.
- **Array-valued attributes are excluded from every manifest** (`faq.items`,
  `swish-links.links`, `swish-cv.rows`, `swish-socials.socialLinks`,
  `swish-gallery.images`, `swish-form.fields`). They cannot be AI-edited at all,
  which is a limitation rather than a protection, but it does mean those
  surfaces are untouched by this feature.
- **`manage_options` is the trust boundary for provider configuration.** An
  administrator can point the OpenAI-compatible provider at any base URL, and
  the stored key is then sent to that host. Treat the settings route as the
  privileged surface it is.
- **Error detail from the provider is passed through.** A failed provider call
  returns the upstream message in the response's `detail` field, which can
  expose third-party account or quota wording to any user who can reach
  `/ai/edit`.
- **The in-editor interface does not ship in 1.0.3.** The routes are live for
  every user holding `edit_posts`, but nothing in the shipped JavaScript calls
  them. The client-side clientId re-check described in §4 is part of that editor
  surface and is therefore not yet present; until it ships, the target stage is
  the only clientId check in effect.
- **SVG uploads are a separate, pre-existing surface.**
  `src/Services/SvgSupport.php` enables SVG uploads for every user holding
  `upload_files` and sanitises them with regular expressions rather than a DOM
  parser. That predates the AI work and is not covered by anything in this
  document. If your site does not need SVG uploads, disable them:

  ```php
  add_filter( 'upload_mimes', function ( $mimes ) {
      unset( $mimes['svg'], $mimes['svgz'] );
      return $mimes;
  }, 20 );
  ```

---

## 9. Reporting a vulnerability

Please report security issues privately rather than in a public issue tracker,
and allow time for a fix before disclosure. Include the plugin version, the
WordPress version, and the smallest reproduction you can manage.

---

## 10. Where these facts come from

| Claim | Source |
|---|---|
| No server-side content write | `src/AI/AIManager.php`, `src/REST/AIController.php` |
| Manifest as allowlist | `src/Blocks/{BlockRegistry,BlockCapability,FieldSpec}.php`, `blocks/*/ai.json` |
| Five stages and their order | `src/Actions/ActionValidator.php`, `src/Actions/Stages/*.php` |
| Token validation and expansion | `src/AI/Context/TokenResolver.php`, `src/Actions/Stages/ValueStage.php` |
| Selection-scope check | `src/Actions/ValidationContext.php` (`isWithinSelection`) |
| Capability checks and rate limit | `src/REST/AIController.php`, `src/Plugin.php` |
| Key encryption | `src/AI/Settings.php`, `src/Services/EncryptionService.php` |
| Form nonce, honeypot, rate limit | `src/Forms/FormProcessor.php` |
| Zero runtime dependencies | `composer.json`, `swishfolio-core.php` (PSR-4 fallback autoloader) |
