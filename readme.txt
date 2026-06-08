=== Swishfolio Core ===
Contributors: swishfolio
Tags: portfolio, blocks, gutenberg, full-site-editing, designers
Requires at least: 6.4
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.3
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Custom blocks, post types, and a CV/links toolkit for the Swishfolio portfolio theme.

== Description ==

Swishfolio Core is the companion plugin for the Swishfolio block theme. It ships
the custom blocks, post types, and admin tools that power a portfolio site:

* **Portfolio block** — pulls projects from the `project` custom post type into a
  configurable grid.
* **Hero block** — full-bleed hero with shapes, blob overlays, CTAs, and
  background patterns.
* **Custom Heading block** — pre-heading badge + main heading + sub-heading with
  per-element styling and click-to-expand inspector panels.
* **Features / Feature-Card blocks** — parent + child blocks for a feature grid
  with overrideable global tokens.
* **FAQ, Testimonials, Pricing, Bento Cards, Swish Gallery, Swish Form,
  Swish Socials, Swish Links, Swish CV** — additional editorial and contact
  surfaces.
* **Project & Testimonial custom post types** — content homes that the dynamic
  blocks above read from.
* **Form processor** — light-weight contact form pipeline with nonce-checked
  REST submission, post-type entries view, and optional SMTP/email-service
  routing.
* **Core block extensions** — adds vertical alignment to columns, plus icon,
  hover colour, and hover animation options to the core/button block.

The plugin is designed to slot into the Swishfolio theme but uses only standard
WordPress APIs, so it can be used with any block theme that provides the
expected colour and font-size presets in `theme.json`.

== Installation ==

1. Upload the `swishfolio-core` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** screen.
3. The Swishfolio blocks appear in the editor under a **Swishfolio** category.
4. (Recommended) Install and activate the Swishfolio theme for the full set of
   patterns and style variations.

== Frequently Asked Questions ==

= Do I need the Swishfolio theme? =

No — the plugin works with any block theme. The Swishfolio theme just adds
matching colour palettes, font sizes, and patterns that pair well with the
blocks.

= Does this plugin call external services or track users? =

No. The plugin makes no outbound HTTP requests and stores no analytics or
telemetry. Form submissions stay on your site; SMTP/email-service routing only
fires when you explicitly configure an SMTP server or ESP API key.

= Is the data portable if I disable the plugin? =

Content stored in custom post types (`project`, `testimonial`, form entries)
remains in your database. Block markup uses standard block-comment syntax and
will render as static HTML even if the plugin is removed.

= Can I use the migration command to update old hex-coloured blocks? =

Yes. Run `wp swishfolio migrate-colors --dry-run` (WP-CLI) to preview which
posts would change, then run without `--dry-run` to apply. Use `--network` on a
multisite install to walk every subsite.

== Changelog ==

= 1.0.3 =
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
Hex colours in built-in patterns are now palette references so they follow the
active theme style. Existing posts can be updated by running
`wp swishfolio migrate-colors`.
