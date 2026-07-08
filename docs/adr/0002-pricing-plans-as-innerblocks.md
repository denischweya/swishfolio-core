# Pricing plans as InnerBlocks with core/list feature lists

The Pricing Table block previously stored its plans as a single `plans` attribute array edited through inspector panels. We converted it in place to a parent `swishfolio-core/pricing` block holding `swishfolio-core/pricing-plan` InnerBlocks (max 4), with each plan's feature list authored as a constrained `core/list` block rather than a custom feature block or string array. No deprecation or migration was written — existing saved pricing blocks break — because the block was confirmed not to be live anywhere (same stance as ADR-0001).

## Considered alternatives

- **Deprecation with `migrate()` from the plans array to InnerBlocks.** Rejected: mechanical to write but pure dead weight when there is no content to migrate.
- **Custom `pricing-feature` block per feature line.** Rejected: `core/list` gives add/sort/reorder, Enter-to-add, and inline editing for free; a custom block would re-implement all of that for one RichText line. The check-icon styling moved from per-`<li>` inline SVG to a CSS `::before` mask so the core markup styles identically.
- **Sortable feature array with hand-built inspector UI.** Rejected: custom reorder UI to maintain, and content editing buried in the sidebar instead of on the canvas.

## Consequences

- The `columns` attribute is gone; column count = plan count (CSS `grid-auto-flow: column`), capped by hiding the inserter at 4 plans.
- Each plan saves both monthly and yearly price rows; the parent's `data-billing` attribute and CSS decide which is visible, so the frontend toggle script only flips that attribute. Child static saves cannot read parent attributes — this is the standard workaround, and future parent-level display state should follow the same pattern.
- `priceSuffix` was dropped: it was already dead (never rendered in save output). Period labels ("/month", "/year") are editable parent attributes instead.
