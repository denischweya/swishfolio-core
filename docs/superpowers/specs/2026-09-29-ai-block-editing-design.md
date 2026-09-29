# Selection-Based AI Block Editing — Design Spec

**Date:** 2026-09-29
**Status:** Approved, not yet implemented
**Scope:** Sub-project A+B+C of the AI Website Builder plan — Block Capability Registry, AI provider abstraction, action engine, and the Gutenberg "Ask AI" editing surface.

---

## 1. Summary

Add AI-assisted block editing to `swishfolio-core`: a user selects a block in the WordPress editor, describes a change in natural language, reviews the proposed changes, and applies them. Changes are applied client-side through the block editor's own data store, so they are reversible with native undo and nothing is written to the database until the user saves as normal.

This is the first of several sub-projects. It deliberately excludes page and site generation, which reuse everything built here.

**Success criterion:** from a clean install with a provider key configured, a user can select a `swishfolio-core/hero`, type "make this feel more premium and tighten the headline", see a list of proposed changes, apply them, and reverse them with one Ctrl+Z.

---

## 2. Context

### What already exists

- **`swishfolio-core`** — 23 custom blocks, every one with an apiVersion 3 `block.json` carrying `description` and `keywords`. PSR-4 `SwishfolioCore\`, `Contracts/` + `Abstracts/`, REST namespace `swishfolio/v1` (in use by forms), `EncryptionService` (aes-256-cbc) already storing ESP credentials, and `AbstractEmailProvider` — a `wp_remote_request()` wrapper with five concrete providers behind it.
- **`swishfolio` theme** — FSE block theme, `theme.json` v3, 8-colour palette, 9-step spacing scale, 8 fluid font sizes, and **five complete style variations** (Default, Neo-Brutalist, Swiss, Sasha, Terminal) each fully re-skinning the palette.
- No AI code anywhere. No `tests/` directory anywhere.

### What this changes about the original plan

Three assumptions in the source plan do not hold against the actual codebase:

1. **"Standardize `block.json`" (Phase 1) is already done.** All 23 blocks have compliant metadata with descriptions and keywords. The real Phase-1 gap is semantic, not structural.
2. **"Use `theme.json` rather than Tailwind" (§24) is already true.** The theme has no Tailwind; `theme.json` is already the design system.
3. **The attribute surface is the actual problem.** The 23 blocks carry roughly 366 attributes between them — `hero` alone has 68, `heading` 45, `portfolio` 31. No prompt can carry that, and raw `block.json` gives an AI names like `decoShape1Color` with no semantics.

### Decisions taken during design

| Decision | Choice | Rationale |
|---|---|---|
| Housing | Inside `swishfolio-core` | One install; best first-website funnel. Reuses `EncryptionService` and the `swishfolio/v1` namespace immediately. Registry exposed via filter so other plugins can register. |
| Block scope | 23 Swishfolio blocks (full) + 4 core text blocks (content-only) | Users mostly select `core/paragraph`; "Ask AI" greyed out on core blocks would read as broken. |
| Capability description | Hand-authored `ai.json` manifest per block | Only tractable way to reduce 68 attributes to a promptable surface, and the manifest doubles as the validator allowlist. |
| Where changes apply | Client-side, via the block editor data store | Native undo, review-is-just-not-dispatching, revisions are the history, and PHP gains no write path at all. |
| Provider transport | `wp_remote_post()`, no vendored SDK | Matches `AbstractEmailProvider`. Plugin has zero runtime Composer deps; vendored HTTP clients are the classic WP plugin conflict. |
| First provider | OpenAI-shaped | Also yields "OpenAI-compatible" (OpenRouter, Groq, Ollama) for ~10% more work. Anthropic second. |

---

## 3. Architecture

```
Gutenberg selection
      |  clientId + blockName
      v
AIContextBuilder (JS)          selection, siblings/parent as name+purpose,
      |                        post type, active style variation
      |  POST swishfolio/v1/ai/edit    [nonce + edit_post capability]
      v
ContextAssembler (PHP)         + capability manifests for blocks in play
      |                        + resolved theme.json design tokens
      v
AIProvider                     prompt + JSON Schema; schema enforced provider-side
      |  actions[]
      v
ActionValidator (PHP)          5 stages, all-or-nothing
      |  validated actions[]   <- response body; nothing has been written
      v
React preview                  per-change diff list
      |  user accepts
      v
registry.batch(() => dispatch('core/block-editor').updateBlockAttributes(...))
      |
      v
User hits Update; WordPress saves and revisions as normal
```

PHP's only database write in this slice is the usage-counter option (§11). There is no code path by which an AI response mutates content server-side.

---

## 4. Block Capability Registry

### 4.1 Manifest location and loading

A sibling **`ai.json`** per block directory:

```
blocks/hero/
    block.json      <- unchanged, canonical per plan §11
    ai.json         <- new
    src/ build/ ...
```

Not a key inside `block.json`: keeps `block.json` schema-clean, and manifests load lazily — only when an AI request arrives, never on a normal page load. Parsed manifests are merged and cached in a transient keyed by `SFCORE_VERSION`, invalidated on plugin update.

### 4.2 Manifest schema (version 1)

```jsonc
{
  "version": 1,
  "tier": "full",              // "full" | "content"
  "purpose": "hero",           // short semantic role, used in site planning later
  "summary": "…",              // one sentence, shown to the AI
  "capabilities": {
    "content": { "<attr>": <FieldSpec>, … },
    "layout":  { "<attr>": <FieldSpec>, … },
    "style":   { "<attr>": <FieldSpec>, … }
  },
  "children": {                 // optional; only for parent blocks
    "allowed": ["swishfolio-core/testimonial-card"],
    "min": 1,
    "max": 12
  },
  "excluded": ["…"]            // documentation only; absence is what enforces
}
```

`FieldSpec` is one of:

| Shape | Meaning | Validation |
|---|---|---|
| `{ "type": "text", "maxLength": N, "role": "…" }` | Plain string content | length, `sanitize_text_field` |
| `{ "type": "url" }` | Link target | scheme allowlist, `esc_url_raw` |
| `{ "type": "richtext", "maxLength": N }` | HTML content | `wp_kses_post` |
| `{ "type": "number", "min": N, "max": N, "unit": "px" }` | Numeric | range |
| `{ "enum": [ … ] }` | Fixed option set | membership |
| `{ "type": "token", "tokenSet": "color" \| "spacing" \| "fontSize" }` | Design token | membership in live `theme.json` preset set |

`role` is an optional hint (`headline`, `supporting`, `primary-cta`) that lets prompts and the preview UI label fields meaningfully without hardcoding block knowledge.

### 4.3 Worked example — `blocks/hero/ai.json`

Hero drops from 68 attributes to 15:

```json
{
  "version": 1,
  "tier": "full",
  "purpose": "hero",
  "summary": "Full-bleed opening section: headline, subheading, up to two CTAs, over a colour or image background.",
  "capabilities": {
    "content": {
      "heading":          { "type": "text", "maxLength": 120, "role": "headline" },
      "subheading":       { "type": "text", "maxLength": 300, "role": "supporting" },
      "ctaText":          { "type": "text", "maxLength": 40, "role": "primary-cta" },
      "ctaUrl":           { "type": "url" },
      "secondaryCtaText": { "type": "text", "maxLength": 40, "role": "secondary-cta" },
      "secondaryCtaUrl":  { "type": "url" }
    },
    "layout": {
      "contentAlign":  { "enum": ["left", "center", "right"] },
      "verticalAlign": { "enum": ["top", "center", "bottom"] },
      "minHeight":     { "type": "number", "min": 320, "max": 1000, "unit": "px" }
    },
    "style": {
      "headingSize":      { "enum": ["medium", "large", "x-large", "display"] },
      "headingTransform": { "enum": ["none", "uppercase"] },
      "backgroundColor":  { "type": "token", "tokenSet": "color" },
      "textColor":        { "type": "token", "tokenSet": "color" },
      "overlayOpacity":   { "type": "number", "min": 0, "max": 100 },
      "ctaStyle":         { "enum": ["primary", "secondary", "outline"] }
    }
  },
  "excluded": ["decoShape1", "decoShape2", "heroImage", "backgroundImage", "imageRotation"]
}
```

### 4.4 Design tokens are mandatory, not preferred

`"type": "token"` means the AI emits a **slug** (`accent-1`), never a value (`#FF6B6B`). `TokenResolver` reads the live `theme.json` preset sets and expands the slug to the storage form each block already uses — for colours, `var(--wp--preset--color--accent-1)`, matching hero's existing attribute defaults.

This is load-bearing. The theme ships five style variations that each re-skin the entire palette; a single AI-emitted hex would survive a variation switch as a broken colour. Token-only output is what keeps AI edits compatible with the theme's defining feature.

An AI-supplied slug that is not in the active `theme.json` is a validation failure, not a fallback.

### 4.5 Tiers

- **`full`** — the 23 Swishfolio blocks. Hand-authored manifests, attribute-level editing.
- **`content`** — `core/paragraph`, `core/heading`, `core/list`, `core/button`. Four short built-in manifests exposing the RichText content field only. "Rewrite this for a professional audience" works; "make this darker" is honestly unavailable rather than silently wrong.

Core-block manifests cannot live in `blocks/*/ai.json` (those directories hold this plugin's own blocks and are scanned as block directories). They ship as JSON files in `src/AI/Manifests/core/{paragraph,heading,list,button}.json`, loaded by `BlockRegistry` alongside the discovered ones and identical in schema.

### 4.6 Registration

```php
BlockRegistry::register( string $blockName, array $manifest ): void
BlockRegistry::get( string $blockName ): ?BlockCapability
BlockRegistry::all(): array
```

Auto-discovers `blocks/*/ai.json` on first use, then applies the `swishfolio_ai_block_manifests` filter so `swish-core-system` and third-party blocks can register without patching core.

---

## 5. Provider layer

Mirrors the existing `src/Email/` structure exactly.

```php
interface AIProviderInterface {
    public function getSlug(): string;
    public function getLabel(): string;
    public function getRequiredFields(): array;
    public function getModels(): array;
    public function testConnection(): bool;
    public function generate( AIRequest $request ): AIResponse;
    public function getLastError(): ?string;
}
```

- **`AIRequest`** — system prompt, user prompt, JSON Schema, model, max tokens, temperature.
- **`AIResponse`** — decoded actions array, `inputTokens`, `outputTokens`, `model`, `providerSlug`, raw body for debugging.
- **`AbstractAIProvider`** — `wp_remote_post()` wrapper with timeout, retry on 429/5xx with backoff, and typed error mapping. No vendored SDK; the plugin keeps zero runtime Composer dependencies.
- **`AIManager`** — resolves the configured provider, builds the request, calls it, hands the response to `ActionValidator`.

### Providers

| Order | Provider | Notes |
|---|---|---|
| 1 | `OpenAIProvider` | Strict JSON Schema via structured outputs. |
| 1b | `OpenAICompatibleProvider` | Same adapter, configurable base URL — OpenRouter, Groq, Together, Ollama. |
| 2 | `AnthropicProvider` | Not in this slice. Schema via `output_config.format`; model IDs `claude-opus-5`, `claude-sonnet-5`, `claude-haiku-4-5`. Its explicit `cache_control` breakpoints suit this prompt shape unusually well — the registry prefix is large and byte-stable, the selection context small and volatile — and cache-read pricing on that prefix is where the eventual credit margin comes from. Measure both before fixing credit costs. |

### Prompt construction

Prompts live in `src/AI/Prompt/` as separate files, not inline strings, so they can be revised without touching application code. Ordering is chosen for cache stability: **system prompt → capability manifests → design tokens → selection context → user instruction.** Everything before the selection context is byte-stable across requests for the same site.

---

## 6. Action schema

Versioned per plan §51. Enforced provider-side by JSON Schema, then re-validated in PHP regardless.

```json
{
  "schemaVersion": "1.0",
  "summary": "Tightened the headline and raised contrast on the hero.",
  "actions": [
    {
      "type": "update_block_attributes",
      "clientId": "a1b2c3",
      "changes": { "heading": "Marketing that compounds", "backgroundColor": "accent-2" },
      "reason": "Shorter headline, stronger contrast against base"
    }
  ]
}
```

| Action | Payload | Constraint |
|---|---|---|
| `update_block_attributes` | `clientId`, `changes{}`, `reason` | Every key must exist in that block's manifest `capabilities` |
| `update_block_content` | `clientId`, `content`, `reason` | `content`-tier blocks; content field only |
| `insert_block` | `parentClientId`, `blockName`, `attributes{}`, `index`, `reason` | `blockName` must be permitted by the parent's `block.json` `parent`/`allowedBlocks` and the manifest's `children.allowed`; respects `children.max` |
| `remove_block` | `clientId`, `reason` | Must be a descendant of the selected block; respects `children.min` |

`insert_block` and `remove_block` are tractable because all seven child blocks already declare `parent` in `block.json` — the constraint is read from existing metadata.

`move_block` and `duplicate_block` are deferred. Server-side actions (`create_page`, `update_template`, `update_global_styles`) belong to the generation sub-project.

---

## 7. Validation pipeline

`ActionValidator` runs five stages. **Any failure rejects the entire response.** Partial application is worse than none.

1. **Envelope** — known `schemaVersion`; `actions` present; count within cap (default 25).
2. **Target** — `clientId` appears in the block tree the client submitted. The server cannot independently verify a clientId, which is acceptable because the server does not write; the client additionally re-checks every returned clientId against the set it sent, closing it on the side that can verify.
3. **Allowlist** — every attribute path exists in that block's manifest. `decoShape1Color` is not blocked by a rule; it is absent, therefore unreachable.
4. **Value** — enum membership, `min`/`max`, `maxLength`, token-set membership against live `theme.json`, URL scheme allowlist.
5. **Sanitize** — `wp_kses_post` for richtext, `esc_url_raw` for URLs, `sanitize_text_field` for text.

Failures return a structured error naming the stage and offending path, surfaced to the user as plan §48 requires: *"AI couldn't complete this request. Nothing was changed."* with details behind a disclosure.

---

## 8. REST API

On the existing `swishfolio/v1` namespace.

| Endpoint | Method | Capability | Purpose |
|---|---|---|---|
| `/ai/edit` | POST | `edit_post` on the target post + `swishfolio_use_ai` | Selection + prompt → validated actions |
| `/ai/test-provider` | POST | `manage_options` | Connection check |
| `/ai/settings` | GET | `manage_options` | Provider, model, masked key state |
| `/ai/settings` | POST | `manage_options` | Save; encrypts key on write |

- Nonce required on every route.
- **The post ID arrives in the request and is verified, never trusted** — `current_user_can( 'edit_post', $postId )`, per plan §44.
- `swishfolio_use_ai` is a new capability mapped to `edit_posts` by default, so site owners can restrict AI separately from editing.
- Rate limiting reuses the transient pattern already in `FormProcessor`, keyed per user: default 30 requests/hour, filterable.

`/ai/generate`, `/ai/plan` and `/ai/apply` belong to the generation sub-project. `/ai/apply` may never exist, since applying happens client-side.

---

## 9. Settings and key storage

Stored in option `swishfolio_ai_settings`: `provider`, `model`, `base_url` (compatible provider only), `api_key` (encrypted via existing `EncryptionService`), `enabled`.

The raw key is **never** returned to JavaScript. The settings screen shows a masked value plus Test Connection. This satisfies plan §42 using code that already exists.

Settings live under the existing `swish-forms` admin menu as a new "AI" submenu, matching current admin structure.

---

## 10. Editor UI

### AI sidebar
`registerPlugin` + `PluginSidebar` imported from **`@wordpress/editor`** (not the deprecated `@wordpress/edit-post`). Shows the current selection and its editable-property count, a prompt field, suggestion chips filtered by what the selected block's manifest actually supports, and the result panel.

### Block toolbar button
"Ask AI" injected via an `editor.BlockEdit` filter with a `BlockControls` fill — the same mechanism `ButtonExtension`, `ColumnsExtension` and `NavigationExtension` already use. Disabled with an explanatory tooltip when the selected block has no manifest.

### Selection context
A `useSelect` hook reading `getSelectedBlock`, `getBlockParents`, `getBlockOrder`, plus post type and active style variation. The selected block is sent in full; parent and siblings as name + purpose only. This is a token-cost decision: siblings at full fidelity would multiply prompt size for no gain.

### Preview and apply
One row per change: block name, human field label from the manifest, before → after. Colour changes render the resolved swatch rather than the slug. Actions: **Apply all**, **Apply selected** (per-change checkboxes — a user keeps the good half instead of rejecting all of it), **Discard**.

All dispatches wrap in a single `registry.batch()` so an AI change collapses into **one undo step**. Without that a five-change response needs five Ctrl+Z presses, which would make undo feel broken. A toast confirms with an explicit Undo button alongside the native shortcut.

---

## 11. Analytics

No custom database table. A single option, `swishfolio_ai_usage`, holds lifetime counters (requests, input tokens, output tokens, actions proposed, actions accepted, failures by stage) plus a rolling window of the most recent 100 request records — provider, model, input tokens, output tokens, action count, accepted count, duration, outcome. Older records fall off the window; the counters are cumulative.

No prompt text, block content, or AI response body is stored.

Token counts are recorded from the very first request even though nothing bills, so the eventual credit costs in plan §36 are set from measurement rather than guesswork.

---

## 12. Testing

All new infrastructure — the plugin currently has no `tests/` directory.

**PHPUnit / `WP_UnitTestCase`**
- Manifest discovery, parsing, caching, and cache invalidation on version bump.
- `ActionValidator`, table-driven, with a rejection case per stage: unknown attribute, out-of-range number, non-existent colour token, disallowed URL scheme, unknown `schemaVersion`, action count over cap, `insert_block` of a disallowed child.
- REST permission and nonce enforcement, including the "post ID verified not trusted" case.
- `TokenResolver` against a fixture `theme.json`, including a variation switch.
- Provider abstraction exercised through a fake provider. **No automated test touches the network** — the only live-key checks are the manual smoke test in milestone 4 and the opt-in live eval mode below.

**Jest / React Testing Library**
- Sidebar rendering and disabled states; context builder output shape; preview diff rendering; the clientId re-check; that apply produces exactly one undo entry.

**Playwright** (`wp-scripts test-e2e`, present in `@wordpress/scripts` 28)
- Open editor → select hero → Ask AI → preview → apply → undo → assert attributes restored, against a stubbed provider.

**AI evaluation suite** (plan §50), in two modes:
- **Recorded responses** — deterministic, free, runs in CI; catches registry and validator regressions.
- **Live provider** — on demand only; catches prompt-quality regressions and costs real money. Never in CI.

Corpus: fixtures of (block state + prompt) with assertions on returned actions — correct attributes touched, values in range, no excluded attribute attempted.

---

## 13. File layout

```
swishfolio-core/
├── src/
│   ├── AI/
│   │   ├── Contracts/AIProviderInterface.php
│   │   ├── Providers/{AbstractAIProvider,OpenAIProvider,OpenAICompatibleProvider}.php
│   │   ├── Prompt/{PromptManager.php, system/*.txt, tasks/*.txt}
│   │   ├── Context/{ContextAssembler.php, TokenResolver.php}
│   │   ├── Manifests/core/{paragraph,heading,list,button}.json
│   │   ├── {AIRequest,AIResponse,AIManager}.php
│   │   └── UsageLogger.php
│   ├── Actions/
│   │   ├── ActionSchema.php
│   │   ├── ActionValidator.php
│   │   └── Stages/{Envelope,Target,Allowlist,Value,Sanitize}Stage.php
│   ├── Blocks/BlockRegistry.php
│   ├── Blocks/BlockCapability.php
│   ├── REST/{AIController.php, SettingsController.php}
│   └── Admin/AISettingsPage.php
├── blocks/<name>/ai.json                (23 new files)
├── src/js/ai-sidebar/                   (React source → assets/js/)
└── tests/{Unit,Integration,E2E,Evals}/
```

---

## 14. Milestones

| # | Milestone | Done when |
|---|---|---|
| 1 | Test infrastructure | PHPUnit, Jest and Playwright all run green on a trivial test |
| 2 | Registry + tokens | 23 `ai.json` manifests authored; `BlockRegistry` and `TokenResolver` unit-tested against fixture `theme.json` |
| 3 | Action schema + validator | All five stages implemented, table-driven rejection tests passing |
| 4 | Provider + REST | OpenAI provider, settings screen with encrypted key; automated tests green against a fake provider, plus one manual smoke test of `/ai/test-provider` with a real key |
| 5 | `/ai/edit` end to end | Real prompt returns validated actions for a hero; permission and rate-limit tests pass |
| 6 | Editor UI | Sidebar, toolbar button, preview, batched apply, single-step undo |
| 7 | E2E + evals | Playwright loop green; recorded-response eval suite running in CI |

Also in scope because the work touches it: bump `Requires at least` from 6.4 to **6.7** (matching the theme; `PluginSidebar` from `@wordpress/editor` needs 6.6 regardless), and migrate the existing `src/js/project-sidebar.js` off the deprecated `@wordpress/edit-post` import so there is one convention rather than two.

---

## 15. Out of scope

Page and site generation; template, template-part and global-styles mutation; image generation or selection; SEO; the hosted Laravel API, credits and subscriptions; Google and Anthropic providers (Anthropic is second, not first); the AI history panel — native undo covers this slice, a history UI arrives with server-side actions; WordPress.org submission and plugin repositioning; theme generalization.
