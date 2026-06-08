# Testimonials authored as InnerBlocks, not a custom post type

The Testimonials block previously rendered via `ServerSideRender`, querying a `Testimonial` CPT. We replaced this with author-time InnerBlocks: the parent `swishfolio-core/testimonials` block holds a grid of `swishfolio-core/testimonial-card` children, each storing its own quote, name, role, avatar, and optional URL. The CPT and the PHP render class were deleted outright.

## Why

Adding a testimonial required navigating to a separate admin screen, creating a post, then returning to the page editor — friction with no payoff, since testimonial content is rarely reused across pages and was never queried by anything other than this one block. Authoring inline keeps the content where it's used and where it's seen.

## Considered alternatives

- **Keep the CPT, add InnerBlocks as a second mode.** Rejected: doubles the inspector surface area for a marginal-use second path.
- **Soft-deprecate the CPT (hidden menu, admin notice).** Rejected: leaves dead code; the CPT had no production content to preserve.
- **Ship a WP-CLI migration command before deleting the CPT.** Rejected: confirmed no live posts use the CPT or the old block, so migration is unneeded.

## Consequences

- The Project CPT (`ProjectPostType.php`) still exists. Future readers should not infer that all content types in this plugin follow the same authoring model — Projects are queried/listed in multiple places, testimonials are not.
- Existing site content using the old `swishfolio-core/testimonials` block will render as empty grids; we accepted this because no live pages were using it at the time of the change.
