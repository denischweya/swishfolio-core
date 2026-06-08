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
