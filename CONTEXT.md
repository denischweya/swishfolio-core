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
