# Swishfolio Core — CONTEXT

Swishfolio Core is a WordPress plugin (GPL-2.0-or-later, text domain `swishfolio-core`, version 1.0.3, requires WP ≥ 6.4 / PHP ≥ 7.4) providing the functionality layer for portfolio sites built on the **Swishfolio block theme** (separate repo at `wp-content/themes/swishfolio` — see its own CONTEXT.md). It ships 23 custom Gutenberg blocks, a Project custom post type with two taxonomies, a forms subsystem (REST submission, SMTP email, 5 ESP integrations), SVG upload support, and editor extensions for three core blocks. This file is the authoritative technical spec: accurate enough to regenerate the plugin from scratch, and the first thing to read before touching code.

---

## 1. Domain language

Use these terms exactly; avoid the listed synonyms.

**Testimonials block** — parent layout container arranging a grid of testimonial cards. Holds shared visual styling (card background, border, shadow, text color, columns); does not store content. `swishfolio-core/testimonials`. _Avoid_: testimonials grid, testimonial list, reviews block.

**Testimonial card** — a single quote with attribution (quote body, author name, role/company, optional avatar image, optional URL on the role line). Authored as an InnerBlock of Testimonials — not a CPT (ADR-0001). `swishfolio-core/testimonial-card`. _Avoid_: testimonial entry, review card, quote card.

**Swish Counter** — a row of stat counters. Parent layout block holding counter items in a grid; owns shared visual styling (font sizes, colors, weight, alignment, counter↔title gap). `swishfolio-core/swish-counter`. _Avoid_: stats block, metrics row, number grid.

**Counter item** — a single animated stat: numeric value flanked by optional prefix/suffix, stacked above a title. The number tweens from zero to its final value once on first viewport entry. InnerBlock of Swish Counter. `swishfolio-core/swish-counter-item`. _Avoid_: stat card, counter card, metric.

**Pricing table** — parent layout container arranging a row of pricing plans (max 4) and owning the billing toggle (monthly/yearly labels, default billing, discount badge, period labels) and shared visual styling. `swishfolio-core/pricing`. _Avoid_: pricing block, price table, plans grid.

**Pricing plan** — a single purchasable tier inside the Pricing table: title, subtitle, monthly and yearly prices, feature list, CTA button, own highlighted flag. InnerBlock of Pricing (ADR-0002). `swishfolio-core/pricing-plan`. _Avoid_: price list, pricing column, tier card.

**Feature list** — the feature lines inside a Pricing plan, authored as a constrained `core/list` block — not a custom block or attribute array (ADR-0002; check icon via CSS `::before` mask). _Avoid_: features array, benefit list.

**Price link** — a per-plan replacement for the numeric price: link text (e.g. "Contact Sales") + URL, shown underlined in the price slot at price typography in both billing modes; when set it wins over numeric prices. _Avoid_: custom price, price override, contact link.

**Swish Slider / Slide** — infinite CSS marquee of image slides (parent `swishfolio-core/swish-slider`, child `swishfolio-core/swish-slide`). Full agreed spec grilled 2026-07-08 (see agent memory "swish-slider-spec").

---

## 2. Repo layout & build system

```
swishfolio-core.php      # bootstrap: constants, autoload, plugins_loaded → Plugin->boot()
composer.json            # PSR-4: "SwishfolioCore\\" => "src/"; no runtime deps
package.json             # @wordpress/scripts ^28 (dev), @wordpress/icons ^10; scripts: build / start / packages-update
webpack.config.js        # extends wp-scripts; multi-block entries; editor plugins src/js → assets/js
src/                     # PHP (namespace SwishfolioCore\)
src/js/                  # editor-extension JS sources → compiled to assets/js/*.js (+ .asset.php)
blocks/<name>/           # block.json + src/{index,edit,save,view}.js|scss → build/ output
blocks/shared/           # components/inspector-tabs.js, social-icons.js (shared JS)
blocks/_shared/          # _variables.scss (shared SCSS design tokens)
assets/css|js|admin|images  # editor.css, compiled extensions, admin UI, pattern screenshots
templates/admin/         # dashboard.php, entries.php, email-settings.php, integrations.php
patterns/features-showcase.html
docs/adr/                # architecture decision records
inc/, languages/         # empty
```

**Constants**: `SFCORE_VERSION`, `SFCORE_PLUGIN_DIR`, `SFCORE_PLUGIN_URL`, `SFCORE_PLUGIN_FILE`. Composer autoload with `spl_autoload_register` PSR-4 fallback for vendor-less dev. Activation/deactivation hooks just `flush_rewrite_rules()`.

**Build**: `npm run build` (or `start` for watch — watch emits unminified dev bundles) discovers every `blocks/*/src/index.js` → `blocks/<name>/build/index.js` (+ `index.css`, `style-index.css`, RTL variants, `index.asset.php`), and each `blocks/*/src/view.js` → `build/view.js`. Editor plugins in `src/js/*.js` → `assets/js/*.js`. CleanWebpackPlugin removed so sources are preserved. Dev-mode (`npm start`) build output is routinely committed; a later `npm run build` flips bundles back to minified — noisy diffs, expected.

---

## 3. PHP architecture

### Bootstrap & wiring (`src/Plugin.php`)
Constructor instantiates everything; `boot()` registers hooks:

| Hook | Method | Purpose |
|---|---|---|
| `init` | loadTextDomain / initPostTypes / initTaxonomies / initBlocks / initBlockPatterns | registration |
| `block_categories_all` | registerBlockCategory | category `swishfolio` ("Swishfolio", icon `portfolio`) |
| `enqueue_block_editor_assets` | enqueueEditorAssets | `assets/css/editor.css`; `sfcore-project-sidebar` script only on `sfcore_project` |

Services instantiated: `AdminMenu` (admin only), `FormProcessor`, `EmailService`, `EspManager`, `SvgSupport`, `ColumnsExtension`, `ButtonExtension`, `NavigationExtension`. WP-CLI: `wp swishfolio migrate-colors` (`Commands\MigrateColorsCommand`; maps hardcoded hex → palette refs; `--dry-run`, `--network`, `--post-type=`).

### Contracts & abstracts
- `Contracts/`: `RegistrableInterface` (`register()`), `BlockInterface` (`getName()`, `getPath()`), `PostTypeInterface` (`getSlug()`, `getArgs()`, `getLabels()`), `TaxonomyInterface` (`getSlug()`, `getPostTypes()`, `getArgs()`), `RenderableInterface` (`render(array $attributes, string $content): string`).
- `Abstracts/AbstractBlock`: `getPath()` = `SFCORE_PLUGIN_DIR . 'blocks/' . getName()`; `register()` calls `register_block_type()` on that dir (reads block.json); adds `render_callback` when the class implements `RenderableInterface`. Per-block filter: `sfcore_block_{blockName}_args`.
- `AbstractPostType` / `AbstractTaxonomy`: same pattern, filters `sfcore_{slug}_args`.

### Registered blocks (23)
Card, Hero, Heading, Portfolio, LatestPosts, Testimonials, TestimonialCard, Faq, Features, FeatureCard, BentoCards, BentoCard, SwishGallery, SwishForm, SwishSocials, Pricing, PricingPlan, SwishCv, SwishLinks, SwishCounter, SwishCounterItem, SwishSlider, SwishSlide — each a `src/Blocks/<Name>Block.php` class. Dynamic (PHP-rendered): **Portfolio**, **LatestPosts**, **SwishForm**; all others static (save.js).

### Block pattern
`swishfolio-core/features-showcase` from `patterns/features-showcase.html` (blockTypes `swishfolio-core/features`, category `swishfolio`); pattern screenshot URLs passed to editor via `wp_localize_script` as `sfcoreFeaturesData.patternPreviews`.

---

## 3b. AI block editing subsystem

Selection-based AI editing: the editor sends a selection, the AI returns structured actions, PHP validates them and sends them back. **PHP never mutates post content** — actions are applied client-side through the block editor store, so native undo reverses them and WordPress revisions are the history. The only DB write is the usage-counter option.

- **Capability manifests** — one `blocks/<name>/ai.json` per block (23) plus `src/AI/Manifests/core/*.json` (5: paragraph, heading, list, list-item, button). Schema v1: `tier` (`full`|`content`), `purpose`, `summary`, `capabilities.{content,layout,style}`, optional `children`, `excluded`. Field types: `text, url, richtext, number, boolean, enum, token`. Colours/spacing/font sizes are **`token` fields carrying theme.json slugs, never literal values** — the theme's five style variations re-skin the whole palette, so a literal would break on a variation switch. The manifest is simultaneously the AI's description and the validator's allowlist.
- **`Blocks/BlockRegistry`** — lazy discovery of `*/ai.json` and flat `*.json`, cached in transient `sfcore_ai_manifests_{SFCORE_VERSION}` (raw arrays, not objects), extensible via filter `swishfolio_ai_block_manifests`.
- **`AI/Context/TokenResolver`** — reads `theme.json` presets. Handles both the flat list of a raw theme.json and the origin-keyed shape of `wp_get_global_settings()`, excluding the `default` origin when the theme disables default presets. `fromTheme()` is the live factory.
- **`AI/Context/ContextAssembler`** — builds the `AIRequest`. Only manifests for blocks in play are sent; neighbours contribute name + purpose, never attributes. System prompt is byte-stable for cache reuse; selection and instruction go in the user message. A hero selection costs ~1,535 tokens (~1,420 cacheable).
- **`AI/Prompt/PromptManager`** — file-based prompts in `src/AI/Prompt/{system,tasks}/*.txt`, `{{placeholder}}` substitution, raises on an unreplaced placeholder.
- **`AI/Providers/`** — `AbstractAIProvider` wraps `wp_remote_post()` (no vendored SDK; zero runtime Composer deps) with retry on 429/5xx and a single overridable `post()` seam. `ProviderRegistry` applies stored credentials and delegates discovery to `ProviderCatalogue`.
- **`AI/Providers/ProviderCatalogue`** — the one place that answers "which providers exist". **Seven entries**: `anthropic`, `openai`, `gemini`, `mistral`, `openrouter`, `groq`, and `openai-compatible` **last**, as the custom-endpoint escape hatch. The four added 2026-11-03 are `AbstractOpenAICompatiblePreset` subclasses — fixed base URL, label, model list, **no transport** — which is why `converse()` tool calling works on them from day one. Gemini goes through its OpenAI-compatible endpoint rather than its native `contents`/`parts` API, so there is no third transport. OpenRouter sends the `HTTP-Referer`/`X-Title` attribution headers it asks integrations for, and is the one preset whose tool support is a property of the **model** it routes to, not the provider.
  **The catalogue exists because of a bug.** The slug allowlist was `Settings::PROVIDERS`, a hardcoded constant enforced in **nine places** (Settings ×5, SwishAiMenu, SettingsController, ConnectionsController), which made the documented `swishfolio_ai_providers` filter half-functional: a third party could register a provider class that `getActive()`, the connect route and the admin screen then all refused by slug, so **no credential could ever be stored against it**. `Settings::LEGACY_PROVIDERS` survives only so `migrate()` can recognise a pre-catalogue option. The admin screen (`SwishAiMenu:145,294`) and `SettingsController` were already registry-driven, so the four appear with no UI change.
  Two latent bugs fixed with it. `OpenAICompatibleProvider::supportsTools()` returned **true** by inheritance — a guess about an endpoint that could be Together (tools) or Ollama (none), and guessing wrong meant the agent died inside a turn instead of refusing up front; it now returns **false** behind filter `swishfolio_ai_compatible_supports_tools`, and only the named presets promise tools. And `testConnection()` pinged `key(getModels())`, which is `null` for a custom endpoint, so **that provider's connection test could never pass** however good the credentials were — it now prefers the configured model.
  **Not added, deliberately:** a context-window table (every model these presets list is 128k+, and the Groq claim justifying one was not substantiable) and per-provider pricing (a price table shipped in a plugin goes stale and misleads — it belongs with the hosted credits work, where it can be updated server-side). Model lists are curated and hardcoded; `Settings::setModel()` only sanitises, so the dropdown is a **UI** constraint and a newer model is a UI change, not a validation one.
- **`Actions/`** — `ActionSchema` (schemaVersion 1.0; types `update_block_attributes`, `update_block_content`, `insert_block`, `remove_block`). `ActionValidator` runs five **all-or-nothing** stages: envelope → target → allowlist → value → sanitize. Value expands token slugs to `var(--wp--preset--*)`; sanitize applies `wp_kses_post`/`esc_url_raw`/`sanitize_text_field`. Any failure rejects the entire response.
- **`AI/Settings`** — option `swishfolio_ai_settings`, key encrypted via the existing `EncryptionService`; `get()` never returns the key, an empty `api_key` on save preserves the stored one.
- **`AI/UsageLogger`** — option `swishfolio_ai_usage`: lifetime counters + 100-entry rolling window. No prompt or content text is stored.
- **`REST/`** — on the existing `swishfolio/v1` namespace: `POST /ai/edit`, `POST /ai/test-provider`, `GET /ai/usage`, `GET|POST /ai/settings`. The post ID is read from the request and **verified** with `current_user_can('edit_post', $id)`. Capability `swishfolio_use_ai` is granted through a `user_has_cap` filter (not written to roles). Rate limit 30/hour/user, filter `swishfolio_ai_rate_limit`.
- **Tests** — `tests/Unit/` via PHPUnit 9.6 (`vendor/bin/phpunit --testsuite unit`), WP functions stubbed in `tests/stubs/`. No automated test touches the network. `ManifestConformanceTest` proves every manifest attribute exists in its `block.json`.

### Editor UI (`src/js/ai/`, `src/js/ai-sidebar/`)

Entry `src/js/ai-sidebar.js` → `assets/js/ai-sidebar.js`, enqueued by `Plugin::enqueueAiSidebar()` (not post-type gated) with `window.sfcoreAiEditor = {maxActions, capabilities}` localised from `Plugin::aiCapabilityPayload()`. **Field order in that payload is significant** — the client resolves `update_block_content` to the first richtext field, matching AllowlistStage/ValueStage.

`PluginSidebar` + `PluginSidebarMoreMenuItem` from `@wordpress/editor`; toolbar "Ask AI" via `addFilter('editor.BlockEdit')` + `BlockControls`. Apply re-checks every returned clientId against the set that was sent, then dispatches inside `registry.batch()` from `useRegistry()` — one `updateBlockAttributes(clientIds, attrsByClientId, {uniqueByBlock:true})`, one `insertBlocks` per insertion, one `removeBlocks` — so an AI change is **one undo step**.

### Generation engine (`src/AI/Generation/`, `src/Actions/Handlers/`)

Two-stage per plan §9: `POST /ai/plan` returns a validated site plan for review, `POST /ai/generate` re-validates and executes it. Unlike in-editor editing, generation **does** write server-side, through `ActionExecutor` + `Actions/Handlers/*` with a per-action-type capability check; `canGenerate()` requires `swishfolio_use_ai` AND `edit_pages`.

**Kits.** Generation composes from one **kit per template site**: `src/AI/Generation/Kits/<slug>/` (`kit.json`, `design.json`, `units/*.html`) plus images and fonts in `assets/kits/<slug>/`, all written by `seed/extract-patterns.php` from `seed/sites/<slug>/` (see `seed/README.md`; never hand-edit). A unit is one top-level section of a template page, verbatim, with demo specifics made portable (`{{kit-image:x}}`, `{{link:<page key>}}`, no attachment ids, no demo URLs); the header and footer are units too but belong to the design, not the plan. `PatternLibrary` lists the kits (`kits()`, `kit()`, `describeKits()` for the template matcher) and picks one with `resolve($requested, $brief, $scope)`: the request's `kit`, else the kit already applied (`swish_kit` option, or detected from its CSS fingerprint / style variation), else the `swishfolio_ai_generation_kit` filter, else `designer`. A plan names its kit and only that kit's catalogue (`Kit::catalogue()`: page compositions plus each unit's role, summary and CopySlots outline) is in the prompt, so sections never mix designs.

A pattern section edits the unit's CopySlots slots by id (`texts` t*, `attrs` a*, `remove`/`clones` u*). **Coverage**: every slot still holding demo copy (`Kit::requiredSlots()`, anything with words or figures except a "01"-style ordinal) must be rewritten or inside a removed unit. `/ai/plan` validates without coverage, makes one `fill-copy` follow-up call for the gaps (`PlanCoverage`), prunes the smallest repeatable unit around any left, then validates fully (`PlanCoverageStage`). Composed kit markup carries inline SVG, forms and scripts, so `PlanExecutor` vouches for it (`TrustedMarkup::vouch()`) and the content tools skip kses for exactly that body. The kit's design (palette, fonts, CSS, parts) is applied separately by `AI/Onboarding/KitDesign`; generation never writes styles.

`PlanExecutor` composes page content, creates pages as drafts, then makes a second pass resolving `{{page:key}}` links to permalinks. Both `createActions()` and `relinkActions()` skip pages carrying `existingPostId` (set only by `PlanCollisionStage` when `onConflict === 'skip'`) — a missing guard there overwrote pages the owner asked to keep.

### BYOK connections and the Swish AI menu

**BYOK (bring your own key).** The plugin ships no AI credits and no backend of its own: the site owner connects their own OpenAI and/or Anthropic account and pays that provider directly. Both can be connected at once; `active` picks which one requests use.

- **`src/AI/Credentials/`** — `CredentialStoreInterface` + `WordPressCredentialStore`. Keys live in their **own** option `swishfolio_ai_credentials`, encrypted via `EncryptionService`, deliberately separate from settings so a settings export/dump cannot leak one. Provider slugs are validated before use as array keys.
- **`src/AI/Settings`** — option `swishfolio_ai_settings`: `active`, `enabled`, `providers.<slug>.{model,base_url,status,last_validated_at}`, `brand`. `get()` never returns a key and keeps flat `provider`/`model`/`base_url`/`hasKey` keys describing the active provider for pre-BYOK callers. Migrates the old flat single-provider option, moving the inline `api_key` into the credential store and removing it. `setEnabled(true)` is refused with no connected provider; disconnecting the active provider falls back to another connected one, and disconnecting the last one forces `enabled` off.
- **Brand context** — `siteName`, `siteDescription` (500 char cap), `designStyle`, `blockShapes`, `copywritingTone`, each validated against a closed vocabulary. Blended into the **cache-stable prefix** of both `ContextAssembler` (editing) and `PlanAssembler` (generation); empty fields are omitted rather than sent blank. Colours/spacing/fonts are NOT set here — those remain theme.json tokens.
- **`src/Admin/SwishAiMenu`** — top-level **Swish AI** menu (`dashicons-superhero-alt`, position 31) with Connections / Brand & Context / Usage. Not a Swish Forms submenu: AI is its own product surface. Provider cards handle connect / test / activate / disconnect via `admin-post.php` with nonce + `manage_options`. `assets/admin/swish-ai.js` repopulates each card's model control from a localised provider→models map, so changing provider never leaves a stale model the provider would reject. Raw provider errors are mapped to human messages by `SwishAiMenu::noticeText()`.
- **REST** — `SettingsController` (GET/POST `/ai/settings`, GET `/ai/models`) handles everything except credentials and **ignores any `api_key` sent to it**; `ConnectionsController` owns POST `/ai/connections`, POST `/ai/connections/test`, DELETE `/ai/connections/{provider}`. All on the existing `swishfolio/v1` namespace rather than a second one.
- `enabled` defaults to **false** and is enforced by `/ai/edit` and the generation routes (403 `sfcore_ai_disabled`); turning it on requires a separate acknowledgement of what leaves the site. `uninstall.php` clears both options across a multisite and leaves user content alone.

### Tests

**Browser suites.** `npm run test:e2e:session` mints a Playwright storage state for an existing admin (no password, no throwaway user), then `npm run test:e2e` drives the real editor flow against a stubbed provider — insert hero → Ask AI → preview → apply → one Ctrl+Z — and `npm run test:e2e:smoke` renders the three Swish AI screens and checks the editor bundle loads. Neither needs API credit. The stub intercepts at the network boundary, so PHP's five validation stages do NOT run there; they are covered by PHPUnit.

`--testsuite unit` (381) and `--testsuite evals` (172, 12 live-mode cases skipped unless `SWISHFOLIO_AI_EVAL_LIVE=1` and `SWISHFOLIO_AI_EVAL_API_KEY` are both set — never in CI). Jest via `npm run test:unit` (110 tests, 14 suites). Playwright in `tests/e2e/` against a stubbed provider. Source must stay PHP 7.4-parseable.

**Accept rate.** Applying happens client-side, so the editor reports the kept count to `POST /ai/applied`, which calls `UsageLogger::recordAccepted()` (capped at `swishfolio_ai_max_actions`). Without it the Usage screen's "Changes applied" would be permanently zero.

`npm run test:e2e:patterns` proves every theme section pattern renders: it parses each with the real block registry (catching `save()`/markup mismatches the way Gutenberg does), then builds a throwaway draft of all 25, measures the height each occupies on the front end, and deletes the draft. `KitConformanceTest` asserts every shipped kit loads and every unit composes: its files and images exist, its links name the kit's own pages, and nothing links into the demo site.

Not yet verified: no live provider call has been made — the connected Anthropic key authenticates but the account has no credit, so `output_config.format` and prompt quality remain unproven.

---

## 3c. AI tool layer (`src/Tools/`)

The WordPress operation surface the AI acts through, specced in
`docs/superpowers/specs/2026-11-02-ai-tool-layer-design.md`. WPVibe was the reference for *which*
operations an AI needs on a site — not for architecture: WPVibe is an MCP server exposing WordPress
to external clients, this is an AI client inside WordPress. Its catalogue transferred; its transport
and auth half did not.

**Two write paths, deliberately separate.** In-editor block editing (§3b) is unchanged: PHP never
mutates post content, the editor applies actions client-side, native undo reverses them. The tool
layer is the *server-side* path, and everything that writes there goes through it.

- **`ToolInterface`** — name, title, description, input JSON Schema, side-effect class
  (`read` | `write_draft` | `write_published` | `destructive`), `authorize()`, `execute()`. This is
  the old `ServerActionHandlerInterface` plus the four things an Abilities-shaped tool needs. The
  description is not documentation: it is what the model chooses by, and the same string the
  Abilities bridge registers as `meta.description`.
- **`ToolRunner`** — the only path: resolve → validate → authorize → confirm → execute → record.
  Reads skip the journal entirely. A mutating call reserves its sequence number *before* executing,
  so the audit row sorts ahead of the target rows it wrote.
- **`InputValidator`** — a JSON Schema subset written in-plugin (zero runtime Composer deps still
  holds). Supports `type`, `required`, `properties`, `additionalProperties:false`, `enum`,
  `minimum`, `maximum`, `maxLength`, `minItems`, `maxItems`, `items`, `default`, and **raises
  `LogicException` on any keyword it does not recognise** — silently ignoring one would let a schema
  author believe a constraint was enforced when it was not. Coerces `"12"` → `12` and `1` → `true`
  because providers differ; does not coerce an object where a scalar belongs.
- **`ToolContext extends ExecutionContext`** — inherits plan-key resolution and the `createdIds`
  ledger, adds `runId`, `siteId` and `confirmed`. `siteId` is bound from the current site and has
  **no setter**: a model-supplied site ID on multisite is a tenancy escape.
- **`ConfirmToken`** — `issue()`/`verify()`, `wp_hash`-signed over the canonicalised plan + user +
  issue time, 15-minute TTL. The gate has to tell "the user approved this" from "the model asserted
  the user approved this", and both arrive in the same payload; a boolean cannot.
- **`CallSummary`** — derives the preview and audit summary from the tool's title, validated input
  and resolved post titles, so no tool implements a `preview()` method to forget.

**Read tools (6)** — `site-info`, `list-content`, `read-page`, `get-global-styles`, `list-media`,
`get-block-capabilities`. All floored at `edit_posts`; `read-page` narrows to `edit_post`. All
capped and paged — an unbounded read is the commonest way a tool layer becomes unusable.
`read-page` runs `parse_blocks()` and reduces the tree to the registry's vocabulary, addressed by
index path (`[0,2]`); **a block with no manifest comes back opaque** — children kept, attributes
withheld — because the validator could not check an edit to them. Markup is never returned: 20 of
23 blocks are static `save()` blocks, so the tree is the authoritative state.
`get-block-capabilities` returns the index when called bare and full manifests for up to 10 named
blocks, so the model pays for the two or three it uses instead of all 28.
Dropped from WPVibe on purpose: `rendered-html`, `registered-meta`, `last-change`, `navigate`.

**Write tools (6)** — the five former `Actions/Handlers/*` plus `rollback-run`. `create-page` is
`write_draft`; `set-front-page`, `set-navigation` and `set-style-variation` are `write_published`;
`rollback-run` is `destructive`. **`update-page` is the one tool whose declared class and enforced
class differ**: declared `write_draft`, but its `authorize()` demands confirmation when the target
turns out to be published — whether it touches the live site depends on the page it is given, and
declaring `write_published` would prompt on every relink of a freshly created draft.

**`MutationJournal` is the only writer for journalled targets.** Tools do not call `wp_insert_post`,
`wp_update_post` or `update_option` themselves. Leaving capture to each tool would put every new
tool one forgotten line from an unreversible write, discovered the first time a user asked to undo.

- Table `{$wpdb->prefix}swishfolio_ai_journal` — per-site by prefix, so multisite isolation needs no
  `site_id` column. Installed by `JournalTable::install()`, version-guarded, on activation *and*
  `admin_init` (an update from a version without the table never runs activation).
- Three row kinds by `target_type`: `call` (the audit record — what was asked, what happened,
  which error), `post` and `option` (undo records carrying prior state). Rollback replays only the
  latter. One call can have several target rows: `set-front-page` writes three options.
- Prior state, per target: a forced `wp_save_post_revision()` for an overwrite, `{"created": id}`
  for an insert, `{"option", "prior", "existed"}` for an option.
- **Two details that corrupt silently if missed.** `existed` is stored separately from the prior
  value, because an option that was `false` and an option that did not exist are otherwise
  indistinguishable and rollback would leave a phantom row. And `wp_save_post_revision()` returns
  null when the post is unchanged, the type lacks support, or `WP_POST_REVISIONS` is off — so the
  prior content is then stored inline. Core supports revisions on `wp_global_styles` (`post.php:522`)
  and `wp_navigation` (`post.php:588`), so inline is the site-configuration path, not a per-type one.
- Input is capped at 4KB and keys matching `/key|secret|token|password/i` are redacted.
- Append-only is enforced by `JournalRepositoryInterface` exposing only `append`, `markReverted`,
  `findRun`, `recentRuns`, `pruneBefore` — no update, no delete. `AppendOnlyTest` asserts that
  method list by reflection, because a future `update()` would pass every behavioural test.
- `WpdbJournalRepository` in production, `tests/Support/InMemoryJournalRepository` in tests. The
  interface exists because the unit suite runs on stubbed WP functions, so `$wpdb` cannot be stubbed
  meaningfully — same reasoning as `CredentialStoreInterface`.

**`Rollback`** walks a run's target rows in reverse `seq`, **best-effort**: a failed entry is written
to its own `revert_error` and the walk continues, because stopping half way leaves a state nobody
asked for. Created posts are trashed, never deleted; a post already gone counts as reverted; a
pruned revision is reported rather than silently skipped. Reverting twice is a no-op.

**`ActionExecutor` is now a façade** over `ToolRunner`, keeping its signature so `PlanExecutor` and
`GenerationController` are untouched. It owns the generation vocabulary (`create_page` →
`swishfolio/create-page`), strips `null` optional keys — `PlanExecutor` emits `'template' => null`
and tool schemas are closed — and converts `ToolException` back to `ExecutionException`.
`PlanExecutor::execute()` takes an optional third `ToolContext`; without one the run cannot be
journalled, so `ActionExecutor` raises rather than writing unrecorded.

**`AbilitiesBridge`** registers every tool on `abilities_api_init` with `show_in_rest => false` and
`meta.mcp.public => false`. Nothing is exposed. The plugin floor is 6.7 and the Abilities API landed
in 6.9, so on most installs it no-ops. What it buys is proof the definitions are well formed before
anything depends on them; later MCP exposure is two flags per tool, not a rewrite. Ability calls go
through the runner, not the tool, or they would be a way to write without a journal row.

**REST** (`ToolsController`, same `swishfolio/v1` namespace): `GET /ai/tools`,
`POST /ai/tools/preview`, `POST /ai/tools/run`, `GET /ai/audit`, `GET /ai/audit/{runId}`. The
preview/run split *is* the gate. Gated on `swishfolio_use_ai` + the owner's `enabled` switch. Tool
runs are capped at 25 calls (`swishfolio_ai_max_tool_calls`) and 60/hour/user
(`swishfolio_ai_tool_rate_limit`) — not token spend, which tools do not incur, but an agent loop
that keeps writing.

**Generation now carries approval**: `/ai/plan` returns a `confirmToken` for the plan the owner
reviews, `/ai/generate` verifies it against the plan **as sent**, so an edited plan must be
previewed again. Both the report and the failure carry `runId`.

**`Admin\AuditLogPage`** — "Audit Log" under Swish AI (`edit_posts`), runs grouped newest-first with
an Undo button per run. The undo goes through the tool, not `Rollback`, because the tool is what
checks this user may reverse these particular writes. Confirmation there is a nonce, not a
`ConfirmToken`: the human is clicking a button on the page that lists the run. Daily
`swishfolio_ai_prune_journal` expires rows older than 90 days
(`swishfolio_ai_journal_retention_days`, 0 to keep everything) — bulk expiry of aged rows, not
mutation of records. `uninstall.php` drops the table: the journal is a record of what the AI did,
not content the owner authored.

**Tests** — `tests/Unit/Tools/` with `wp-tools-stubs.php` layered over the generation stubs (adds
modelled revisions, trash, `delete_option`, `parse_blocks`, `WP_Query`). `RoundTripTest` is the
keystone: a whole run — create, overwrite a published page, publish a menu, repoint the front page,
reskin — then one rollback, and every pre-existing post is byte-identical, every option is as it
was, and everything created is in the trash. `RollbackTest` is the inverse matrix (13 cases).
`WriteToolsTest` is the port of the old `ServerActionHandlerTest` with its assertions intact; four
error-reason assertions changed deliberately when per-handler slugs (`insert_failed`, `not_a_page`,
`empty_menu`, `unknown_variation`) became unified `ToolError` codes. `tests/Evals/ToolDescriptionEvalTest`
guards the catalogue: descriptions distinguishable (worst pairwise overlap 0.22 against a 0.40
ceiling), 150–450 chars each, whole catalogue under 14KB of JSON per request.

**Not built in this slice**: media upload (slice 3), write breadth — projects CPT, posts,
taxonomies, menus, options allowlist, theme.json tokens (slice 4), async jobs and progress
(slice 5), external MCP exposure (phase 5). The spec's model-in-the-loop tool-selection eval is
deferred too: the provider adapter has no tool-calling support yet, so
`ToolDescriptionEvalTest` measures the property that eval would test, offline.

---

## 3d. Agent layer (`src/AI/Conversation/`, `src/AI/Agent/`, `src/Jobs/`, `src/AI/Onboarding/`)

Specced in `docs/superpowers/specs/2026-11-02-agent-loop-design.md`. §3c built the tool surface and
nothing could reach it: no provider supported tool calling, nothing in `src/AI/` referenced
`ToolRunner`, and no UI called the tool routes.

**Provider tool-calling.** `converse()` is a second method on `AIProviderInterface`, not an overload
of `generate()` — structured output constrains one reply to a schema, tool calling is a multi-turn
exchange, and the structured path is load-bearing for editing and generation. `AbstractAIProvider`
answers `supportsTools()` false and raises, so a third-party provider from the
`swishfolio_ai_providers` filter is never handed a conversation it cannot answer.

- **`Conversation/ToolNameMap`** — both providers require `^[a-zA-Z0-9_-]{1,64}$`, so every
  registered name is illegal as-is. Only the slash is replaced (`swishfolio/site-info` →
  `swishfolio__site-info`); hyphens survive. Reversal splits on the first `__`, unambiguous because
  no tool name contains one — **asserted, not assumed**.
- **The two wire formats differ in a way that bites.** Anthropic carries tool results as
  `tool_result` blocks inside **one** user message; splitting a parallel batch across messages does
  not error, it teaches the model to stop issuing parallel calls. OpenAI wants one `role: tool`
  message per `tool_call_id` and has **no `is_error` flag**, so a failure must be legible in the
  content or it reads as a successful empty result.
- `Message` is one class for user/assistant/tool-result, because providers interleave them in one
  ordered list and a tool result that does not immediately follow its call is a protocol error.

**`Agent/AgentLoop`** — resolve → converse → run calls → feed back → repeat. Three decisions:

1. **A tool failure goes back to the model, not up as an exception.** Stable error codes and field
   paths exist so a model can correct its own call; aborting on a `schema_invalid` throws that away.
2. **`confirm_required` pauses the turn.** Reads and drafts proceed; the first call that would
   change what visitors see ends the turn with the proposal and a `ConfirmToken` for exactly it.
   The model is never told which tools are gated, so it proposes work rather than planning around
   the gate. On resume the approved calls are **replayed from the history's last assistant turn**,
   not from the request body, so a client cannot pair a token with different calls.
3. **Iterations are capped** (8, `swishfolio_ai_agent_max_iterations`). A model that keeps calling
   tools is an unbounded bill against the owner's own key.

**REST** — `POST /ai/agent` (`{instruction, history, confirmToken, async}`) and
`GET /ai/jobs/{id}`. History lives on the **client**, which keeps the plugin stateless and
multisite-safe, and is only safe because nothing in it is trusted: every replayed call is
re-validated, re-authorised and re-checked against the token. Capped at 60 turns, **oldest dropped**
— the newest carry the calls a token must match. `POST /ai/onboarding/extract` is the CV import.

**`src/Jobs/`** — a correction to the original premise: generation's *execution* is a handful of fast
database writes and was never the timeout risk. An agent turn makes **up to eight provider calls**,
which on a typical host outlives PHP's execution limit, so that is what moved off the request.

- Jobs live in **transients**, not a table: a job is a progress record and the durable record of what
  the work did is the journal. Losing one costs a progress bar, not history.
- The turn **runs as the user who queued it** — cron has no current user, and a tool authorising
  against nobody would either refuse everything or be made to refuse nothing.
- `wp_schedule_single_event` is nudged with `spawn_cron()`, since WP-Cron otherwise waits for a page
  load a single-admin session may not produce for minutes.
- Three failure modes are handled because each otherwise leaves a spinner turning forever: a
  provider exception, an unexpected `Throwable`, and **cron firing the same event twice** (which
  would double the turn's writes — guarded by refusing to run a job that is not `queued`).

**UI** (`src/js/agent/`, `src/js/ai-sidebar/agent-*.js`) — its **own** `PluginSidebar`, registered as
a second plugin. The block panel is meaningless without a selection and says so; "add a contact
page" has nothing to do with what is selected, and one panel would have an empty state that is wrong
half the time. The **transcript the user reads and the history the server needs are kept
separately** — deriving either from the other means showing raw tool JSON or discarding what the next
turn replays. The approval prompt lists the **whole batch**, because that is what the token approves.
Declining sends nothing: those calls never ran. Failure copy differs from the block editor's on
purpose — there nothing was changed and it can say so; here the loop may have completed journalled
calls before the provider dropped, so it points at the audit log.

**`AI/Onboarding/ProfileExtractor`** — the differentiator, in the version that works. The product
idea was a LinkedIn URL; LinkedIn serves a login wall to server-side requests and forbids scraping,
so a URL field fails for almost everyone and fails confusingly. **Pasted text** works for every
source and asks for consent by construction. The result is a proposal (`"saved": false`) the owner
corrects before anything is stored — a CV is full of things nobody wants on their website — and it
reports what the CV **did not** give it. Style and tone enums are read from `Settings` rather than
restated, or a model suggesting "elegant-modern" would produce an answer `saveBrand` silently
discarded.

### Tool catalogue (18)

Reads: `site-info` (now including each post type's taxonomies), `list-content`, `read-page`,
`get-global-styles`, `list-media`, `list-navigation`, `get-block-capabilities`.
Writes: `create-content`, `update-content`, `set-front-page`, `set-navigation`,
`update-navigation`, `set-style-variation`, `set-terms`, `upload-media`, `set-alt-text`,
`update-site-settings`, `rollback-run`.

- `create-content`/`update-content` replaced the page-only pair over a **closed enum** of
  `page|post|sfcore_project` — a closed set, not free text, so no model output can create a
  revision, a menu item or an attachment through them. `postType` defaults to `page`, which is why
  `ActionExecutor` needed only a name change.
- `upload-media` is the one genuinely dangerous operation: scheme checked before any fetch,
  `download_url()` → `wp_safe_remote_get()` → `wp_http_validate_url()` refusing loopback,
  link-local and private addresses, and `wp_handle_sideload()` admitting the file only on its own
  bytes. Both media tools are `write_draft` — an attachment is invisible until referenced, and a
  prompt per image would make the gate noise.
- `set-terms` takes **names**, not IDs, because a model made to supply IDs invents them. Creating
  missing terms is off by default and needs the taxonomy's own `edit_terms`.
- `update-site-settings` is an **allowlist of two**; the options table holds the home URL, the admin
  email and the active theme, so a free-text option name would be privilege escalation.
- `update-navigation` is what `set-navigation` was missing: on any existing site "add the page to my
  menu" is the request and a second menu is the wrong answer. It replaces wholesale, which is why
  `list-navigation` exists.

**Journal target types are now four.** `post`, `option`, `meta` and `terms`. Meta and terms need
their own rows because **a post revision does not carry them**: restoring the revision would put the
content back and silently leave the alt text or the categorisation changed. Prior terms are stored as
**IDs**, since a rollback matching names would fail or duplicate after a rename.

**Tests** — `tests/Unit/Agent/`, `tests/Unit/Jobs/`, `tests/Unit/Onboarding/`, `tests/js/agent/`.
The tool-selection eval the §3c spec deferred now exists in both halves: `ToolSelectionEvalTest`
(recorded) proves everything downstream of the choice, because **a recording cannot measure
selection — the recording is the selection**; `LiveToolSelectionEvalTest` (`@group live`,
self-skipping) makes the measurement, each case naming a set of defensible first moves.

**Still not built**: credits, subscription entitlements and analytics (need the hosted backend,
deliberately deferred), external MCP exposure (two flags per tool when wanted), theme.json token
editing (an arbitrary patch there has no undo, as `SetStyleVariationHandler` originally argued).

---

## 4. Content model

### Project CPT (`src/PostTypes/ProjectPostType.php`)
Slug `sfcore_project`; public, archive, REST base `projects`, rewrite `projects`, menu icon `dashicons-portfolio`, position 5; supports title/editor/thumbnail/excerpt/custom-fields. Status enum: `completed | in-progress | planned | on-hold`.

Registered meta (all single, REST-exposed):
- `_sfcore_project_status` (string enum above)
- `_sfcore_completion_date` (string)
- `_sfcore_skills` (array of strings)
- `_sfcore_project_url` (string, esc_url_raw)
- `_sfcore_client_name` (string)

Classic side meta box `sfcore_project_details` (nonce-guarded save on `save_post_sfcore_project`) **and** a Gutenberg document panel: `src/js/project-sidebar.js` → `PluginDocumentSettingPanel` registered as `sfcore-project-sidebar`, rendered only for `sfcore_project`, editing the same meta via `useEntityProp`. Skills edited as comma-separated text ↔ array.

### Taxonomies
- `sfcore_project_cat` — hierarchical, REST base `project-categories`, rewrite `project-category`
- `sfcore_project_type` — hierarchical, REST base `project-types`, rewrite `project-type`
Both attached to `sfcore_project` only.

### Form entries (`src/Forms/FormEntryPostType.php`)
Slug `swish_form_entry`; private, no UI, `create_posts => do_not_allow`, supports title only. Meta: `_swish_form_id`, `_swish_form_type` (contact|subscription), `_swish_submission_ip` (SHA-256 hashed), `_swish_submission_email`, `_swish_esp_synced` (bool), `_swish_form_data` (JSON). Static API: `createEntry()`, `getEntries()`, `getEntryCount()`, `markAsSynced()`.

---

## 5. Forms subsystem ("Swish Forms")

**Flow**: swish-form block (frontend JS) → POST `wp-json/swishfolio/v1/forms/submit` → `FormProcessor::handleSubmission()` → validate/sanitize (`ValidationService`) → create `swish_form_entry` → dispatch `swish_forms_contact_submitted` (→ `EmailService::sendContactEmail`) or `swish_forms_subscription_submitted` (→ `EspManager::handleSubscription`).

- **Security**: per-form nonce `swish_form_submit_{formId}` (permission callback), honeypot field (bot fill → silent fake success client-side), transient rate limit `swish_form_rate_{md5(ip)}` = 10/hour/IP.
- **ValidationService**: per-type validation (email `is_email`, tel regex `/^[\d\s\-\(\)\+\.]+$/`, required checks) + per-type sanitization; field types: `text, email, tel, textarea, select, checkbox`.
- **EmailService** (`src/Email/`): HTML email in neo-brutalist style (4px black border, 8px hard shadow, yellow #FFD93D header); optional SMTP via `phpmailer_init`; AJAX `wp_ajax_swish_forms_send_test_email`. Settings in option `swish_forms_email_settings` (smtp_enabled/host/port/encryption/username/password, from_email, from_name).
- **EspManager + providers** (`src/Email/Providers/`): `mailchimp, convertkit, klaviyo, activecampaign, brevo`, each implementing `EmailProviderInterface` (`getName, getSlug, getRequiredFields, setCredentials, testConnection, getLists, subscribe, getLastError`) over `AbstractEmailProvider` (`wp_remote_request` wrapper). AJAX: `wp_ajax_swish_forms_test_connection`, `wp_ajax_swish_forms_fetch_lists`. Settings in option `swish_forms_esp_settings` keyed by provider slug.
- **EncryptionService** (`src/Services/`): aes-256-cbc, key = sha256(AUTH_KEY) fallback `'swishfolio-default-key'`; format base64(iv::cipher). API keys/SMTP passwords stored encrypted.
- **AdminMenu** (`src/Admin/`): top-level `swish-forms` menu (manage_options) with Dashboard / Form Entries / Email Settings / Integrations submenus; templates in `templates/admin/`; assets `assets/admin/admin.{css,js}` localized with nonce `swish_forms_admin`.

---

## 6. Services

- **SvgSupport** (`src/Services/SvgSupport.php`): allows `image/svg+xml` uploads; sanitizes on upload (strips scripts, event handlers, data: URIs, foreignObject; handles gzipped .svgz); fixes MIME detection, admin preview, dimensions (viewBox parsing), attachment metadata; admin CSS for media library display.
- **SvgKses**: static allowlist for rendering inline SVG via `wp_kses` (svg, line, polyline, polygon, path, circle, rect + presentation attrs).

---

## 7. Core-block editor extensions

Each has a PHP class (`src/Extensions/`) enqueueing a compiled editor script (`assets/js/*.js` from `src/js/*.js`) + frontend CSS, and a `render_block` filter (priority 10) that rewrites frontend markup. JS side uses `blocks.registerBlockType` (add attributes), `editor.BlockEdit` (inspector UI), `blocks.getSaveContent.extraProps` / `editor.BlockListBlock` (classes).

**ButtonExtension** (`core/button`) — attributes `sfcoreIcon` (slug, ''), `sfcoreIconPosition` (before|after, 'after'), `sfcoreHoverTextColor`, `sfcoreHoverBgColor`, `sfcoreHoverAnimation` (lift|scale|slide|shift, ''). Icon set (JS map + matching PHP SVG map, localized as `sfcoreButtonIcons`): arrow-right, arrow-left, arrow-up-right, arrow-down, chevron-right, plus, check, download, external. Output: classes `sfcore-btn--has-icon`, `--has-hover`, `--anim-{x}`; CSS vars `--sfcore-btn-hover-text`, `--sfcore-btn-hover-bg`; SVG injected server-side by regex.

**ColumnsExtension** (`core/columns` + `core/column`) — attribute `sfcoreVerticalAlignment` (top|center|bottom|space-between|space-around|space-evenly|stretch, ''). Output class `sfcore-vertical-align-{x}` (align-items on columns, justify-content inside column).

**NavigationExtension** (`core/navigation`) — attributes `sfcoreOverlayAlign` (left|center|right), `sfcoreHoverStyle` (none|underline|underline-grow|color), `sfcoreHoverColor`, `sfcoreHamburgerIcon` (preset slug or `{type:'custom', url}`). Presets (localized `sfcoreNavIcons`): lines-3, lines-2, x, dots, plus, grid; close icon always swapped to canonical X server-side. Registers nav block style variations on `init` (default, centered-overlay, minimal). Output: `sfcore-nav--align-{x}`, `sfcore-nav--hover-{x}`, var `--sfcore-nav-hover-color`.

---

## 8. Block conventions

- **block.json**: apiVersion 3, category `swishfolio`, `html: false` everywhere; children add `reusable: false` + `parent`; assets referenced `file:./build/...`; dynamic blocks add `"render": "file:./render.php"` or PHP render_callback via `RenderableInterface`.
- **BEM prefix `sfcore-`**: root `sfcore-<block>`, elements `__x`, modifiers `--y`. (swish-cv is the exception: uses `wp-block-swishfolio-core-swish-cv__*` classes.)
- **Styling contract**: parent blocks write CSS custom properties inline from attributes; children/SCSS consume them. Empty-string color attribute = inherit theme/parent default.
- **Inspector**: shared `InspectorTabs` component (`blocks/shared/components/inspector-tabs.js`): `useInspectorTabs(initial)` → `[activeTab, setActiveTab]`; `<InspectorTabs activeTab setActiveTab allowedTabs=['general','style','advanced']>`; classes `sfcore-inspector-tabs__tab is-active`. Rollout via the `block-tabbed-inspector` skill; not yet on every block (faq, pricing, swish-cv, swish-gallery, swish-links, swish-socials use plain panels).
- **Shared SCSS tokens** (`blocks/_shared/_variables.scss`): theme-preset color aliases (`$color-base/contrast/accent-1..6`), style-aware `$shadow-sm..xl`, `$border-*`, `$radius-*`, `$text-glow`; spacing xs–2xl; transitions .1/.2/.3s; breakpoints 600/782/1024; z-index 100/900/1000; mixins `card-base`, `button-base`, `hover-lift`, `text-glow`.
- **Inline-editable content** uses `source: html` attributes with class selectors (see per-block tables) — changing those class names breaks saved content.
- **Icons**: per-block libraries built from `@wordpress/icons`; `blocks/shared/social-icons.js` for brand SVGs.
- **A11y**: `prefers-reduced-motion` respected by every animated block (counter snaps, marquee stops); ARIA on accordions/tablists/lightboxes.

---

## 9. Block catalog

Attribute format: `name:type=default`. All colors are strings; empty = inherit.

### hero — `swishfolio-core/hero` (static, 68 attrs)
Full-bleed hero: heading/subheading, color-or-image background with overlay, framed rotated hero image with 2 decorative blob SVGs, up to 3 positioned background blob layers, 2 CTAs with icons, social links.
- Content/layout: `layout:s="full"`, `heading:s=""`, `subheading:s=""`, `headingStyle:s="normal"`, `headingSize:s="large"`, `headingTransform:s="none"`, `minHeight:n=600`, `contentAlign:s="center"`, `verticalAlign:s="center"`, `textColor:s="var(--wp--preset--color--base)"`, `headingFullWidth:b=false`, `headingOverlayImage:b=false`
- Background: `backgroundType:s="color"`, `backgroundColor:s="var(--wp--preset--color--accent-1)"`, `backgroundImage:o={}`, `overlayColor:s="var(--wp--preset--color--contrast)"`, `overlayOpacity:n=50`
- Hero image: `heroImage:o={}`, `imageBorderWidth:n=4`, `imageBorderColor:s=contrast`, `imageShadowSize:s="large"`, `imageRotation:n=2`, `showImageDecoShapes:b=true`, `decoShape1:s="circle"`, `decoShape1Color:s=accent-2`, `decoShape2:s="square"`, `decoShape2Color:s=accent-1`
- CTAs: `ctaText/ctaUrl:s=""`, `ctaStyle:s="primary"`, `ctaIcon:o={type:"auto",value:""}`, `ctaIconPosition:s="right"`, `secondaryCtaText/Url:s=""`, `secondaryCtaIcon:o={type:"auto"}`, `secondaryCtaIconPosition:s="right"`
- Decor: `decorativeShape:s="none"`, `decorativeColor:s=accent-2`, `socialLinks:a=[]`
- Bg shape layers ×3 (`bgShape{1,2,3}…`): `Type:s="none"` (blob preset key or custom), `CustomSvg:o={}`, `Color:s=accent-2/accent-1/accent-4`, `GradientSide:s="none"` (fade mask via CSS mask-image), `Size:s="medium|medium|small"`, `Position:s="top-right|bottom-left|center-left"`, `Opacity:n=30`
- Image blobs ×2 (`imageBlob{1,2}…`): `Shape:s="blob1|blob2"` (6 organic path presets blob1–blob6), `Color:s=accent-2/accent-1`, `Size:s="medium"`, `Rotation:n=0`
- CTA icon library ~27 dashicon-style entries; `type:"auto"` picks arrow by context. No view.js.

### heading — `swishfolio-core/heading` "Custom Heading" (static, 45 attrs)
Neo-brutalist heading stack: pre-heading badge + main heading + sub-heading, each independently styled (bg, text color, rotation −10..10°, border style/width/color, hard shadow none/small/medium/large/xlarge = 4/8/12/16px offsets), plus optional left/right icons and dotted/grid background patterns.
- Per element `{preHeading,heading,subHeading}`: text, `BackgroundColor`, `TextColor` (=#000), `Rotation:n=0`, `BorderStyle` (pre="solid", others "none"), `BorderWidth:n=4`, `BorderColor="#000000"`, `ShadowSize` (pre="small", others "none"), `show*:b=true`. Pre-heading default bg `#FFD93D`.
- Heading extras: `headingTag:s="h2"`, `headingSize:s="large"` (legacy fallback), `headingFontSize:s=""` (theme preset slug → `has-{slug}-font-size` class), `headingFontSizeCustom:s=""` (→ `--sf-heading-fs` var, clamp-wrapped in SCSS), `headingStyle:s="normal"`; `subHeadingFontSize:s="medium"`.
- Icons: `leftIcon/rightIcon:o={type:"none",value:""}` + `Color="#000000"`, `BackgroundColor=""`, `Size="medium"`.
- Block: `contentAlign:s="center"`, `blockBackgroundColor:s=""`, `showBackgroundPattern:b=false`, `backgroundPattern:s="dots"` (dots|grid|large-dots via radial-gradient).
- Markup: `.sfcore-heading--align-{x}` > `__pre`, `__main` (+ size/style/has-bg classes), `__sub`, `__icon--{left|right}--{size}`. No view.js.

### card — `swishfolio-core/card` (static)
Standalone icon card. `icon:o={type:"library",value:"star"}` (41-icon library or custom image URL), `iconPosition:s="top"` (top|left|right), `iconSize:s="medium"`, `iconColor:s=""`, `iconBackgroundColor:s="#F0C020"`, `iconShape:s="square"` (square|rounded|circle), `cardBorderStyle:s="solid"` (solid|dashed|none), `cardBorderColor:s="#121212"`, `cardShadowColor:s="#121212"`, `title:s=""`, `description:s=""`, `linkUrl/linkText:s=""`, `linkStyle:s="text"` (text|button), `decorativeShape:s="none"` (circle|square|triangle), `decorativeColor:s=""`. Supports full color/spacing/typography/border. Markup: `.sfcore-card--icon-{pos}--icon-{size}--icon-shape-{shape}--border-{style}`, vars `--card-border-color`, `--card-shadow-color`; `__shape--{x}` absolute decorative div. view.js empty.

### features — `swishfolio-core/features` (static parent) + feature-card child
Parent grid; global styling flows to children as `--features-*` CSS vars; children override per-attribute (empty = inherit via `var()` fallback).
- features attrs: `gridGap:s="2rem"`, `cardBorderStyle:s="solid"`, `cardBorderColor/cardShadowColor:s=""`, `titleFontSize/subheadingFontSize:s=""`, `titleColor/iconColor/iconBackgroundColor/ctaTextColor/ctaBackgroundColor/cardBackgroundColor/cardAccentColor:s=""`. Vars emitted: `--grid-gap`, `--card-border-color`, `--card-shadow-color`, `--features-title-color`, `--features-title-font-size`, `--features-subheading-font-size`, `--features-icon-color`, `--features-icon-bg`, `--features-cta-text-color`, `--features-cta-bg`, `--features-card-bg`, `--features-card-accent`. Template: 3 medium cards MODULE 01–03. Advanced tab shows pattern previews (`sfcoreFeaturesData`) that replace the block.
- feature-card attrs: `subheading:s="MODULE 01"`, `title:s="Feature Title"`, `titleTag:s="h3"` (h2–h5), `description:s`, `icon:o={type:"library",value:"star"}` (29-icon lib|custom|none), `ctaType:s="link"` (link|button|none), `ctaText:s="Learn More"`, `ctaUrl:s=""`, `cardSize:s="medium"` (small=4col span, medium=6, large=8), `cardStyle:s="default"` (default|accent|highlight), `backgroundImage:o={}` + `backgroundImagePosition:s="center center"` + `overlayColor:s=""` + `overlayOpacity:n=50`, override colors/font-sizes (empty ⇒ parent var). Markup: `.sfcore-features__card--{size}--{style}` [`--has-bg-image --has-overlay`], vars `--card-bg`, `--card-accent`.

### bento-cards — `swishfolio-core/bento-cards` (static parent) + bento-card child
Bento grid with 5 card styles: `overlay`, `hover-reveal`, `caption-below`, `stacked`, `cta`; optional "layered images" mode (image on colored backdrop) and "tall image" hover-scroll reveal (image slowly pans on hover).
- bento-cards attrs: `gridGap:s="1.5rem"`, `cardStyle:s="overlay"`, `cardOverlayColor:s=""` (default rgba(6,26,20,.85)), `cardOverlayOpacity:n=60`, `accentColor:s=""` (default #FFE500), `cardSubtitleColor/cardTitleColor/cardDescriptionColor/cardTextBg:s=""`, `cardTitleFontSize:s=""`, `layeredImages:b=false`, `layeredBgColor:s=""`, `layeredPaddingTop:n=10` (%), `layeredTallImage:b=false`, `layeredTallHeight:n=395` (px viewport), `layeredTallSpeed:n=4.6` (s), `ctaType:s="none"` (none|link|button), `ctaText:s="View All"`, `ctaUrl/ctaTextColor/ctaBgColor:s=""`. Vars: `--grid-gap --overlay-color --overlay-opacity --accent-color --card-subtitle-color --card-title-color --card-description-color --card-text-bg --card-title-base-size --layered-bg-color --layered-padding-top --layered-tall-height --layered-tall-speed`. Root: `.sfcore-bento--style-{x} [--layered --tall-image]`; template full + two-thirds + one-third.
- bento-card attrs: `image:o`, `subtitle/title/description:s=""` (html-sourced from `.sfcore-bento__card-subtitle/-title/-description`), `titleColor:s=""`, `cardSize:s="one-third"` (full|two-thirds|half|one-third), `layeredImageWidth:n=100` (20–100%, layered mode only). Markup: `.sfcore-bento__card--{size} [--has-image]` > `__image-layer > __image`, `__overlay`, `__content > __subtitle + __info > __title-row (__title + __actions[InnerBlocks core/buttons]) + __description`; var `--layered-image-width`.

### faq — `swishfolio-core/faq` (static + view.js)
Accordion; items stored in `items:a=[]` of `{question, answer}` (not InnerBlocks). `allowMultipleOpen:b=false`, `defaultOpenFirst:b=true`, `iconStyle:s="plus"` (plus|chevron|arrow), `iconPosition:s="right"`, colors `questionColor/headerBackgroundColor/contentBackgroundColor/answerColor:s=""`. Markup: `.sfcore-faq--icon-{pos}` `data-allow-multiple data-default-open-first`; item `.sfcore-faq__item[.is-open]` > `button.__header[aria-expanded][aria-controls]` + `#faq-content-{i}.__content[aria-hidden]` > `__answer`. view.js: click/Enter/Space toggles `is-open` + aria; closes siblings unless allow-multiple; plus icon swaps +/−.

### latest-posts — `swishfolio-core/latest-posts` "Swish Blog" (dynamic)
Server-rendered post grid (`LatestPostsBlock::render`, ServerSideRender in editor). `columns:n=3` (1–4), `postsPerPage:n=3` (1–12), `categories:a=[]`, `showDate:b=true`, `showAuthor:b=false`, `showExcerpt:b=true`, `excerptLength:n=20` (10–55 words), `showFeaturedImage:b=true`, `imageAspectRatio:s="landscape"` (square|landscape|portrait), `inheritQuery:b=false` (use main query on blog/archive for pagination), `imageBorderRadius:n=0` (0–50), `titleColor/excerptColor/metaColor:s=""`.

### portfolio — `swishfolio-core/portfolio` (dynamic + view.js)
Queries `sfcore_project` (tax_query on both taxonomies). `layout:s="grid"` (grid|masonry|list), `cardStyle:s="default"` (default|minimal|overlay|stacked), `columns:n=3` (1–4), `gap:n=24` (8–64px), `postsPerPage:n=6` (1–24), `categories:a=[]`, `projectTypes:a=[]`, `orderBy:s="date"` (date|title|modified|rand|menu_order), `order:s="DESC"`, `showFilters:b=false`, `filterTaxonomy:s="category"` (category|type), display toggles `showExcerpt:b=true / showCategory:b=true / showProjectType:b=false / showStatus:b=false / showCompletionDate:b=false / showSkills:b=false / showClient:b=false / showProjectUrl:b=false / showViewButton:b=true / showClientLabel:b=true`, `viewButtonText:s="View Project"`, `imageAspectRatio:s="landscape"` (square|landscape|wide|portrait), `hoverEffect:s="lift"` (none|lift|zoom|reveal), `featuredProjectId:n=0` (hero above grid), `taxonomyStyle:s="plain"` (plain|rounded|border|underline), `taxonomyColor:s=""`, `titleFontSize/titleFontWeight:s=""`, `imageBorderRadius:n=0`, `cardContentBackground:s=""`. Vars: `--portfolio-gap/-columns`, `--sf-portfolio-title-size/-weight/-image-radius/-content-bg/-tax-color`. Root modifiers `--layout- --style- --hover- --cols- --tax-`. Grid=CSS grid, masonry=CSS columns, list=image|content rows. view.js: filter buttons (`data-filter`, class `filter-{slug}` on items) fade/scale out non-matches (300ms then display:none). Status badge classes `__status--{completed|in-progress|planned|on-hold}`.

### pricing — `swishfolio-core/pricing` (static parent + view.js) + pricing-plan child (ADR-0002)
Parent owns billing toggle + shared styling; plans are InnerBlocks (max 4, appender hidden at cap); column count = plan count (`grid-auto-flow: column`).
- pricing attrs: `showToggle:b=true`, `defaultBilling:s="monthly"`, `toggleMonthlyLabel:s="Monthly"`, `toggleYearlyLabel:s="Yearly"`, `yearlyDiscount:s="Save 20%"`, `monthlyPeriodLabel:s="month"`, `yearlyPeriodLabel:s="year"`, colors `borderColor/highlightedBackgroundColor/highlightedTextColor/textColor/toggleBackgroundColor:s=""`. providesContext: `swishfolio-core/pricing/{defaultBilling,monthlyPeriodLabel,yearlyPeriodLabel}`. Vars `--pricing-border-color/-highlight-bg/-highlight-text/-text-color/-toggle-bg`. Root carries `data-billing` (monthly|yearly); toggle is `role=tablist` with `data-billing` buttons + discount badge. view.js flips root `data-billing` + `is-active`/`aria-selected` — CSS shows/hides the per-plan monthly/yearly price rows (child static saves can't read parent attrs; this is the established pattern for parent display state).
- pricing-plan attrs: `title/subtitle:s=""` (html-sourced `.sfcore-pricing__title/__subtitle`), `monthlyPrice/yearlyPrice:s=""`, `pricePrefix:s="$"`, `priceLinkText/priceLinkUrl:s=""` (Price link: replaces numeric price in both modes when set), `highlighted:b=false`, `badgeText:s="Recommended"`, `buttonText:s=""` (html-sourced `.sfcore-pricing__button`), `buttonUrl:s=""`. usesContext = the three parent keys. Feature list = constrained `core/list` InnerBlock; check icon styled via CSS `::before` mask. `priceSuffix` was removed (dead); period labels live on parent.

### swish-counter — parent (static + view.js) + swish-counter-item child
- swish-counter attrs: `columns:n=4` (1–6), `align:s="center"`, `counterFontSize:s=""`, `counterFontWeight:n=800`, `counterColor:s=""`, `titleFontSize:s=""`, `titleColor:s=""`, `counterTitleGap:n=8` (0–40px). Vars `--sf-counter-columns/-size/-weight/-color/-title-size/-title-color/-title-gap`. Grid responsive 4→2 (781px)→1 (480px). Template: 4 items (100, 50, 10, 5).
- swish-counter-item attrs (all html-sourced spans/p under `.sfcore-swish-counter-item__{prefix,value,suffix,title}`): `prefix:s=""`, `value:s="0"`, `suffix:s=""`, `title:s=""`. Save emits `data-target` (parsed float) + `data-decimals` (from value string) on the value span; `<figure class="sfcore-swish-counter-item">`.
- Parent view.js: IntersectionObserver (threshold 0.4) → rAF tween 0→target over 2s easeOutCubic, `toLocaleString` formatting, unobserve after run; reduced-motion ⇒ snap.

### swish-cv — `swishfolio-core/swish-cv` (static)
Timeline rows in `rows:a` of `{year, role, atCompany, description, location}` (4 sample rows default); `accentColor:s=""` → `--swish-cv-accent`, `titleColor:s=""` → `--swish-cv-title`. Row markup (class prefix `wp-block-swishfolio-core-swish-cv__`): `__row > __year + __middle (h3.__role > text + span.__role-at, p.__description) + __location`. Toolbar row move-up/down/delete; Enter in role jumps to atCompany. No view.js.

### swish-form — `swishfolio-core/swish-form` (dynamic: render.php + PHP class; view.js)
`formId:s=""` (auto `swish-form-{clientId8}`), `formType:s="contact"` (contact|subscription; switching resets fields to type defaults), `fields:a=[]` of `{id, type, label, placeholder, required, width(full|half), options[]}`, `submitButtonText:s="Submit"`, `successMessage/errorMessage:s=…`, `recipientEmail:s=""`, `emailSubject:s="New Form Submission"`, `espProvider:s="none"`, `espListId:s=""`, `enableHoneypot:b=true`. Rendered form: `.sfcore-swish-form--{type}` > form.__form `data-form-id/-type/-success-message/-error-message`, hidden honeypot `hp_field`, nonce input, `.__messages[aria-live=polite]`, fields `.__field--{full|half}` (label + input/textarea/select + `.__field-error`), button with `.__button-text` + hidden `.__button-loading` spinner. view.js: async fetch POST JSON `{formId, formType, nonce, fields}` to `/wp-json/swishfolio/v1/forms/submit`; honeypot short-circuit; field-level error rendering (`has-error`); button loading state; success resets form + scrolls message into view.

### swish-gallery — `swishfolio-core/swish-gallery` (static + view.js)
`images:a=[]` (`{id,url,alt,caption,sizes}`), `layout:s="grid"` (grid|masonry|carousel), `columns:n=3` (1–6), `gap:n=16` (0–48), `imageSize:s="large"`, `aspectRatio:s="original"` (original|square|4-3|16-9|9-16|2-3|3-2), `hoverEffect:s="zoom"` (zoom|lift|blur|none), `enableLightbox:b=true`, `lightboxShowCaption:b=true`, `autoplay:b=false`, `autoplaySpeed:n=5000`, `showArrows:b=true`, `showDots:b=true`, `imageBorderRadius:n=0` (0–40), `imageShadow:s="none"` (none|small|medium|large). Root: `.sfcore-swish-gallery--layout- --cols- --hover- --aspect- --shadow-` + data-attrs (`data-lightbox`, `data-lightbox-caption`, `data-autoplay`, `data-autoplay-speed`, `data-show-arrows`, `data-show-dots`); vars `--gallery-gap/-columns`, `--image-border-radius`. Items `figure.__item > .__image-wrapper > img.__image[data-full-url][data-index]` + `.__lightbox-trigger`. view.js: carousel = translateX slide nav (arrows/dots/swipe/keyboard, autoplay with hover-pause); lightbox = singleton `.sfcore-lightbox[role=dialog][aria-modal]` with prev/next/counter/caption, Escape/arrows, click-outside close, body scroll lock.

### swish-links — `swishfolio-core/swish-links` (static)
`links:a` of `{text, url, handle, icon, iconPosition(before|after), newTab}` (defaults: Read.cv//sasha, Are.na, LinkedIn with arrow-up-right after), `direction:s="horizontal"`, `gap:n=28` (0–64px), `hoverColor:s=""` → `--swish-links-hover-color`, `hoverStyle:s="underline-grow"` (none|underline|underline-grow|color), `linksAlign:s="center"`. Markup: `.sfcore-links--{dir}--hover-{style}--align-{x}` > `a.sfcore-link[--with-icon]` > `__icon--before` + `__text` + `__handle` (small, 0.6 opacity) + `__icon--after`. Hover effects pure CSS (underline-grow = ::after width 0→100%). No view.js.

### swish-slider — parent (static + view.js) + swish-slide child
Infinite CSS marquee (see memory "swish-slider-spec" for the agreed product spec).
- swish-slider attrs: `slideWidth:n=426` / `slideHeight:n=262` (px), `gap:n=1` (rem), `direction:s="left"` (left|right), `secondsPerSlide:n=6` (1–20, 0.5 step), `pauseOnHover:b=true`, `revealOnHover:b=true` (currently content is always-visible centered; scrim opacity transitions — reveal styling evolved 2026-07-08), `textColor/scrimColor:s=""`, `scrimOpacity:n=60`, `ctaColor/accentColor:s=""`, `ariaLabel:s="Featured slider"`. Root `<section class="sfcore-swish-slider [--right --pause-hover --reveal-hover]">` > `.sfcore-swish-slider__track` > slides. Vars: `--slide-w/-h/-ratio/-gap`, `--seconds-per-slide`, `--scrim-color/-opacity`, `--slider-text-color`, `--cta-color`, `--accent-color`; view.js sets `--marquee-distance` (one slide-set width) + `--marquee-duration` (slideCount × secondsPerSlide) then adds `is-running`. view.js clones the slide set (`aria-hidden`, links `tabindex=-1`) until track ≥ max(2×set, viewport+set); keyframes translateX 0→−distance, reversed for right; pause via animation-play-state on hover/focus-within; reduced-motion disables. Template: 4 slides.
- swish-slide attrs: `image:o`, `title/subtitle:s=""` (html-sourced `.sfcore-swish-slide__title/__subtitle`), `cta1Text:s="Start With This Design"`, `cta1Url:s="https://swishfolio.com/"`, `cta1NewTab:b=false`, `cta2Text:s="View Demo"`, `cta2Url:s="https://swishfolio.com/"`, `cta2NewTab:b=true`. Markup: `.sfcore-swish-slide > __image (object-fit cover) + __scrim (flat color, opacity transition) + __content (absolute inset-0, flex-centered) > __title + __subtitle + __ctas > __cta--primary / __cta--secondary` (button-styled variants).

### swish-socials — `swishfolio-core/swish-socials` (static)
`socialLinks:a=[]` of `{icon(platform key|custom), url, label, customIcon{url,id,alt}, isExternal}`; `iconShape:s="square"` (square|circle), `iconSize:s="medium"` (16/24/32px), `borderWidth:n=3` (0–8), `borderColor:s="#121212"`, `iconColor:s="#121212"`, `backgroundColor:s="transparent"`, `shadowSize:s="small"`, `hoverEffect:s="lift"` (lift|scale|rotate|glow|none), `useBrandColors:b=false` (platform brand bg + white icon), `alignment:s="center"`, `gapSize:s="medium"`. Markup: `.sfcore-socials--align---gap-` > `a.sfcore-socials__link--{shape}--{size}--shadow---hover-` (inline border/color styles) > SVG span or custom `img.__icon-img`. Brand SVGs from `blocks/shared/social-icons.js`. No view.js.

### testimonials — parent (static) + testimonial-card child (ADR-0001)
- testimonials attrs: `columns:n=3` (1–4), `cardBackgroundColor:s=""` (default theme accent-2), `cardBorderColor:s=""`, `cardBorderWidth:n=0` (0–8), `cardBorderRadius:n=18` (0–40), `cardShadow:s="none"` (none|small|medium|large), `textColor:s=""` (default contrast), `roleColor:s=""` (default accent-3). Vars `--sf-columns/-card-bg/-card-border-color/-card-border-width/-card-radius/-card-shadow/-card-text/-role-color`; grid 3→2→1 responsive. Template: 3 cards.
- testimonial-card attrs: `quote/name/role:s=""` (html-sourced `.sfcore-testimonial-card__quote/__name/__role`), `roleUrl:s=""` (role becomes link), `avatarImageId:n=0`, `avatarImageUrl:s=""`, `avatarColor:s=""` → `--sf-avatar-color` (initial-letter fallback avatar). Markup: `figure.sfcore-testimonial-card > __quote-mark(aria-hidden) + p.__quote + figcaption.__attribution > __avatar (img | initial span) + __meta > __name + __role`.

---

## 10. ADRs (`docs/adr/`)

- **0001-testimonials-as-innerblocks** — Testimonials converted from CPT+ServerSideRender to InnerBlocks; CPT and render class deleted outright (no live content, no migration). Projects CPT intentionally remains a CPT (it IS queried/listed).
- **0002-pricing-plans-as-innerblocks** — Pricing converted from `plans` attribute array to pricing-plan InnerBlocks with `core/list` feature lists; no deprecation/migration (block not live); `columns` attr removed (count = plan count); parent `data-billing` + CSS drives visible price row because child static saves can't read parent attributes.

Precedent to follow: content authored where it's used (InnerBlocks) unless it's genuinely queried across pages (then CPT); no dead-code soft-deprecations; parent display state → data-attribute + CSS, not child re-render.

---

## 11. Gotchas & current state (2026-07-08)

- **Active WIP**: pricing→InnerBlocks refactor and swish-slider styling are being iterated live; an auto commit-and-push loop commits `develop` every 30 min.
- Old saved `testimonials` / `pricing` markup from pre-refactor versions renders empty — accepted (ADRs).
- `npm start` (watch) rewrites every `build/` bundle unminified; production `npm run build` before release.
- `languages/` and `inc/` are empty; translations not yet generated.
- swish-cv breaks the `sfcore-` BEM convention (uses `wp-block-…` prefix) — match its existing style when editing.
- FAQ items are an attribute array, not InnerBlocks (predates the ADR-0001/0002 precedent).
- The theme is a separate git repo; plugin blocks reference theme preset colors (`var(--wp--preset--color--accent-*)`) so defaults assume the Swishfolio theme palette.

## 12. Regeneration order

To rebuild from scratch: (1) plugin bootstrap + composer PSR-4 + contracts/abstracts; (2) Plugin.php wiring + block category; (3) Project CPT + taxonomies + project-sidebar editor plugin; (4) forms subsystem (FormEntry CPT → ValidationService → FormProcessor REST → EmailService/SMTP → EspManager+providers → AdminMenu/templates); (5) SvgSupport/EncryptionService/SvgKses; (6) webpack multi-block build + shared InspectorTabs + `_variables.scss`; (7) blocks in dependency order (parents with children together); (8) core-block extensions; (9) pattern + WP-CLI command. Verify: `npm run build` clean, blocks insertable, form posts to REST and lands a `swish_form_entry`, portfolio renders projects.
