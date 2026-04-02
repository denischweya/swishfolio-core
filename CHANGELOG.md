# Changelog

All notable changes to Swishfolio Core will be documented in this file.

## [1.0.3] - 2026-04-02

### Added
- New **Pricing Table** block with the following features:
  - Up to 3 pricing columns with responsive grid layout
  - Monthly/Yearly billing toggle with animated switching
  - Customizable plan title, subtitle, and pricing
  - Feature list with checkmark icons
  - CTA buttons with customizable URL and text
  - Highlighted/Featured plan option with "Recommended" badge
  - Color customization options:
    - Toggle background color
    - Border color
    - Text color for non-highlighted plans
    - Highlighted background color
    - Highlighted text color
  - Uses theme design tokens for consistent styling across style variations

## [1.0.2] - 2026-03-15

### Changed
- Refactored FAQ and Portfolio block styles to use base color variables instead of accent colors
- Updated color palette handling in blocks to use theme colors dynamically
- Replaced hardcoded color arrays with `useSetting` hook for improved flexibility

## [1.0.1] - 2026-03-01

### Added
- Style-aware design system with Terminal style variation
- New card styling options for Testimonials block (background, border, shadow settings)

### Changed
- Removed border from testimonial container class

## [1.0.0] - 2026-02-15

### Added
- Initial release
- Card block
- Hero block
- Heading block with custom text formats
- Portfolio block with filtering
- Latest Posts block
- Testimonials block with carousel and grid modes
- FAQ block with accordion functionality
- Features block
- Bento Cards block
- Swish Gallery block
- Swish Form block with email service integrations
- Swish Socials block
- Project custom post type
- Testimonial custom post type
- Project Category and Project Type taxonomies
- Admin dashboard and settings
- SVG upload support
- Columns extension
