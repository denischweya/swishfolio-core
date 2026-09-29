=== Swishfolio Core ===
Contributors: swishfolio
Tags: ai, blocks, block-editor, portfolio, forms
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-assisted block editing, plus custom blocks, a portfolio post type and forms for building block-theme websites.

== Description ==

Swishfolio Core turns the WordPress block editor into an AI-assisted website
builder. It ships 23 custom blocks, a Project post type, a contact-form
pipeline, and an AI editing engine that lets a person describe a change in
plain language and have it applied to the selected block.

The AI part is built around one rule: **the AI is never allowed to write
markup.** It returns a list of structured changes, each naming a block and an
attribute. PHP checks every one of those changes against a hand-written
capability manifest for that block type, and rejects the whole response if any
part of it is out of bounds. Nothing the AI returns is written to your database
by this plugin.

= AI block editing =

* **Capability manifests.** Every block ships an `ai.json` manifest listing the
  attributes the AI may change, with the allowed values for each. An attribute
  that is not in the manifest cannot be reached — it is not blocked by a rule,
  it simply does not exist as far as the AI is concerned. 23 Swishfolio blocks
  are covered in full, plus content-only manifests for `core/paragraph`,
  `core/heading`, `core/list`, `core/list-item` and `core/button`.
* **Design tokens, not colour values.** The AI emits theme preset slugs
  (`accent-2`), never literal values (`#FF6B6B`). A slug that is not in your
  active `theme.json` is rejected. This is what keeps AI edits working when you
  switch style variations.
* **Five-stage validation, all or nothing.** Every response passes through
  envelope, target, allowlist, value and sanitize checks. A single failure
  rejects the entire response — half an applied edit is worse than none.
* **PHP never mutates your content.** The `/ai/edit` route validates and
  returns changes; it has no write path to post content. See
  `docs/SECURITY.md`.
* **Bring your own provider.** OpenAI, or any OpenAI-compatible endpoint
  (OpenRouter, Groq, Together, a local Ollama). You supply the API key; there
  is no hosted service, no account, and no subscription. See
  `docs/AI-PROVIDERS.md`.

**What is not in this release:** the in-editor "Ask AI" sidebar and toolbar
button are not included in 1.0.3. The AI engine, its REST routes and its
settings route are present and working, but there is no AI screen in wp-admin
yet, so a key can currently only be saved by POSTing to the
`swishfolio/v1/ai/settings` route. Until an administrator saves a key, the
plugin makes no AI requests at all. The AI features are documented here so the
data handling is disclosed up front rather than after the interface lands.

= Blocks and site building =

* **Portfolio block** — pulls projects from the `project` custom post type into
  a configurable grid.
* **Hero block** — full-bleed hero with shapes, blob overlays, CTAs, and
  background patterns.
* **Custom Heading block** — pre-heading badge + main heading + sub-heading with
  per-element styling and click-to-expand inspector panels.
* **Features / Feature-Card blocks** — parent + child blocks for a feature grid
  with overrideable global tokens.
* **FAQ, Testimonials, Pricing, Bento Cards, Swish Gallery, Swish Form,
  Swish Socials, Swish Links, Swish CV, Swish Counter, Swish Slider** —
  additional editorial and contact surfaces.
* **Project & Testimonial custom post types** — content homes that the dynamic
  blocks above read from.
* **Form processor** — light-weight contact form pipeline with nonce-checked
  REST submission, post-type entries view, and optional SMTP/email-service
  routing.
* **Core block extensions** — adds vertical alignment to columns, plus icon,
  hover colour, and hover animation options to the core/button block.

The plugin is designed to slot into the Swishfolio theme but uses only standard
WordPress APIs, so it can be used with any block theme that provides colour,
spacing and font-size presets in `theme.json`.

Further documentation ships in the plugin's `docs/` directory:
`PRIVACY.md` (what is stored and what leaves your site), `SECURITY.md` (the
security model behind AI editing) and `AI-PROVIDERS.md` (how to get and
configure a key).

== External services ==

This plugin contacts third-party services. **Every one of them is off by
default and only becomes active once you enter credentials for it.** With no
credentials saved, this plugin makes no outbound HTTP requests of its own.

= 1. OpenAI (AI block editing) =

**What it is used for:** turning your plain-language instruction into a list of
proposed changes to the selected block.

**When a request is sent:** only when (a) an administrator has saved an OpenAI
API key in the plugin's AI settings, and (b) a logged-in user with the
`edit_posts` capability and permission to edit the target post triggers an AI
edit. No request is made on page load, on activation, on the front end, or for
site visitors. Every request is triggered by an editor action; there is no
background or scheduled call.

**What is sent to OpenAI**, as the body of a POST to
`https://api.openai.com/v1/chat/completions`:

* The instruction you typed.
* The selected block's identifier, its block type name, and its complete stored
  attribute set. For most blocks that includes its visible text — headings, body
  copy, button labels — and any image or link URLs stored in those attributes.
* The block type name and one-word purpose of the selected block's parent,
  siblings and inner blocks. Their attributes and their text are **not** sent.
* The post type you are editing and the name of the active style variation.
* The capability manifests for the block types involved: attribute names and
  their permitted values. This is a description of the plugin, not of your
  content.
* The colour, spacing and font-size preset slugs defined by your theme's
  `theme.json` — slug names only, no CSS values.
* The model name, an output token limit, and the JSON schema the reply must
  match.
* Your OpenAI API key, in the `Authorization` header.
* WordPress's standard outbound `User-Agent` header, which contains your
  WordPress version and your site's home URL.

**What is not sent:** the post title, the rest of the post's content, other
posts, usernames, email addresses, form entries, any part of your database, and
any data about your site's visitors.

**Where to read their terms**

* OpenAI terms of use: https://openai.com/policies/terms-of-use/
* OpenAI privacy policy: https://openai.com/policies/privacy-policy/

Because you use your own API key, your relationship is directly with OpenAI and
is governed by the agreement you accepted when you created that key.

= 2. Any OpenAI-compatible endpoint (AI block editing) =

If you select the "OpenAI-compatible API" provider you also supply a base URL.
The same data described in section 1 is then sent to **whatever host you
configure** — OpenRouter, Groq, Together, a self-hosted Ollama, or anything
else — instead of to OpenAI. This plugin cannot vouch for that host and cannot
link you to its terms. You are responsible for choosing an endpoint you trust
and for reading its terms and privacy policy before entering its URL.

= 3. Email service providers (form subscriptions, optional) =

If you connect an email service provider under **Swish Forms → Integrations**
and point a form at it, the email address and the submitted field values of
each matching form submission are sent to that provider so the person can be
added to your list. Nothing is sent until you save API credentials for one of
these providers and select it on a form.

**Mailchimp** — endpoint `https://<dc>.api.mailchimp.com/3.0`

* Terms: https://mailchimp.com/legal/terms/
* Privacy: https://mailchimp.com/legal/privacy/

**Kit (formerly ConvertKit)** — endpoint `https://api.convertkit.com/v3`

* Terms: https://kit.com/terms
* Privacy: https://kit.com/privacy

**Klaviyo** — endpoint `https://a.klaviyo.com/api`

* Terms: https://www.klaviyo.com/legal/terms-of-service
* Privacy: https://www.klaviyo.com/legal/privacy-notice

**ActiveCampaign** — endpoint: the account API URL you supply

* Terms: https://www.activecampaign.com/legal/terms-of-service
* Privacy: https://www.activecampaign.com/legal/privacy-policy

**Brevo** — endpoint `https://api.brevo.com/v3`

* Terms: https://www.brevo.com/legal/termsofuse/
* Privacy: https://www.brevo.com/legal/privacypolicy/

= 4. Your own SMTP server (form notification email, optional) =

If you enable SMTP under **Swish Forms → Email Settings**, form notification
email is handed to the SMTP host you configure instead of to PHP's mail
function. That host is yours, not a service this plugin chooses.

= What this plugin never does =

It sends no analytics, no telemetry, no usage reports, no licence checks and no
"phone home" of any kind to the plugin author or to anyone else. AI usage
counters are written to your own database and are never transmitted. No remote
script, stylesheet or font is loaded by this plugin at runtime.

== Installation ==

1. Upload the `swishfolio-core` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** screen.
3. The Swishfolio blocks appear in the editor under a **Swishfolio** category.
4. (Recommended) Install and activate the Swishfolio theme for the full set of
   patterns and style variations.
5. (Optional) To enable AI block editing, obtain an API key from your chosen
   provider and save it as described in `docs/AI-PROVIDERS.md`. Until you do,
   the AI features are inert and no external request is made.

== Frequently Asked Questions ==

= Do I need the Swishfolio theme? =

No — the plugin works with any block theme. The Swishfolio theme just adds
matching colour palettes, font sizes, and patterns that pair well with the
blocks. The AI features need a theme that declares colour, spacing and
font-size presets in `theme.json`, which every modern block theme does.

= Does this plugin call external services? =

Yes, and only ones you configure. The AI features send your selected block and
your instruction to OpenAI (or to an OpenAI-compatible endpoint you choose);
the form subscription feature sends subscriber details to the email service
provider you connect. Both are inactive until you save credentials for them.
The **External services** section above lists exactly what is sent, when, and
where to read each service's terms.

= Does this plugin track users or send analytics? =

No. There is no telemetry, no analytics and no licence check. The plugin
records AI token counts and request outcomes in a WordPress option on your own
site so you can see what you are spending; that record contains no prompt text,
no block content and no response body, and it is never transmitted anywhere.

= Do I need an account or a subscription for the AI features? =

No. You bring your own provider API key and are billed by that provider
directly. There is no hosted service behind this plugin, no credits system and
nothing to sign up for.

= Can the AI break my page or overwrite my content? =

The plugin has no code path that writes post content in response to an AI
reply. `/ai/edit` validates the AI's proposed changes and returns them; the
editor applies them on the client, so a single Ctrl+Z reverses them and nothing
is stored until you press Update yourself. Every proposed change is also
checked against a per-block allowlist and rejected wholesale if any part of it
is out of bounds.

= Is the data portable if I disable the plugin? =

Content stored in custom post types (`project`, `testimonial`, form entries)
remains in your database — the plugin ships no uninstall routine, so nothing is
deleted automatically. `docs/PRIVACY.md` lists every option and post type the
plugin creates and how to remove each one. Block markup uses standard
block-comment syntax and will render as static HTML even if the plugin is
removed.

= Can I use the migration command to update old hex-coloured blocks? =

Yes. Run `wp swishfolio migrate-colors --dry-run` (WP-CLI) to preview which
posts would change, then run without `--dry-run` to apply. Use `--network` on a
multisite install to walk every subsite.

== Changelog ==

= 1.1.0 =
* Add AI block editing: per-block `ai.json` capability manifests for 23
  Swishfolio blocks plus five core blocks, design-token resolution from
  `theme.json`, a five-stage all-or-nothing action validator, an OpenAI and
  OpenAI-compatible provider layer over `wp_remote_post()`, and the REST routes
  `swishfolio/v1/ai/edit`, `/ai/test-provider`, `/ai/usage` and `/ai/settings`.
  The in-editor interface is not included in this release.
* Add a local AI usage record (token counts and outcomes only, no prompt or
  content text) in the `swishfolio_ai_usage` option.
* Store the AI provider API key encrypted at rest in the `swishfolio_ai_settings`
  option.
* Correct the readme: document every external service the plugin contacts. The
  previous FAQ stated the plugin made no outbound requests, which was never
  accurate — the email service provider integrations have always done so.
* Add Swish Links and Swish CV blocks.
* Add core/button extension with icon picker, hover styles, and 4 hover
  animations (lift / scale / slide / shift).
* Add Sasha style support: custom heading block now offers a FontSizePicker
  with preset + custom mode.
* Migrate hardcoded hex colours in patterns and the hero block to
  `var(--wp--preset--color--*)` references.
* Add `wp swishfolio migrate-colors` WP-CLI command for migrating existing
  posts.
* Various security hardening: SVG escape allow-listing, REST nonce in
  permission_callback, sanitised navigation hover CSS values.

= 1.0.2 =
* Bento Cards block refinements.
* Project sidebar editor extension.

= 1.0.1 =
* Initial public release.

== Upgrade Notice ==

= 1.0.3 =
Adds AI block editing, which sends the block you select and your instruction to
an AI provider you configure. Read the External services section before
enabling it. Hex colours in built-in patterns are now palette references so
they follow the active theme style; existing posts can be updated by running
`wp swishfolio migrate-colors`.
