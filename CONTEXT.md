# Swishfolio Core

Domain language for the Swishfolio Core WordPress plugin — a block library for portfolio sites built on the Swishfolio theme.

## Language

### Blocks

**Testimonials block**:
The parent layout container that arranges a grid of testimonial cards. Holds shared visual styling (card background, border, shadow, text color, columns); does not store content. Block name: `swishfolio-core/testimonials`.
_Avoid_: testimonials grid, testimonial list, reviews block.

**Testimonial card**:
A single quote with attribution (quote body, author name, role/company, optional avatar image, optional URL on the role line). Authored as an InnerBlock of the Testimonials block — not a custom post type. Block name: `swishfolio-core/testimonial-card`.
_Avoid_: testimonial entry, review card, quote card.

**Swish Counter**:
A row of stat counters. Parent layout block that holds counter items in a grid and owns shared visual styling (font sizes, colors, weight, alignment, gap between counter and title). Block name: `swishfolio-core/swish-counter`.
_Avoid_: stats block, metrics row, number grid.

**Counter item**:
A single animated stat — a numeric value flanked by optional prefix/suffix, stacked above a title. The displayed number tweens from zero to its final value once on first viewport entry. Authored as an InnerBlock of Swish Counter. Block name: `swishfolio-core/swish-counter-item`.
_Avoid_: stat card, counter card, metric.

**Pricing table**:
The parent layout container that arranges a row of pricing plans (up to 4) and owns the billing toggle (monthly/yearly labels, default billing, discount badge) and shared visual styling. Block name: `swishfolio-core/pricing`.
_Avoid_: pricing block, price table, plans grid.

**Pricing plan**:
A single purchasable tier inside the Pricing table — title, subtitle, monthly and yearly prices, feature list, CTA button, and its own highlighted flag. Authored as an InnerBlock of the Pricing table. Block name: `swishfolio-core/pricing-plan`.
_Avoid_: price list, pricing column, tier card.

**Feature list**:
The list of feature lines inside a Pricing plan. Authored as a `core/list` block constrained inside the plan — not a custom block or attribute array.
_Avoid_: features array, benefit list.

**Price link**:
A per-plan replacement for the numeric price: link text (e.g. "Contact Sales") plus a URL, shown underlined in the price slot at price typography in both billing modes. When set, it wins over any numeric prices.
_Avoid_: custom price, price override, contact link.
