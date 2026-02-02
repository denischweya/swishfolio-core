# Swishfolio Core

Core functionality plugin for the Swishfolio WordPress theme. Adds custom Gutenberg blocks, post types, and taxonomies for building stunning portfolios for designers, developers, photographers, contractors, and creative agencies.

**Website:** [swishfolio.com](https://swishfolio.com)

## Features

### Custom Blocks
- **Portfolio** - Showcase projects with multiple layouts (grid, masonry, list) and filtering
- **Hero** - Bold hero sections with neo-brutalist styling
- **Card** - Versatile content cards with various styles
- **Form** - Contact and subscription forms with validation
- **Gallery** - Image galleries with lightbox support
- **Testimonials** - Client testimonials carousel
- **FAQ** - Accordion-style FAQ sections
- **Heading** - Styled headings with decorative elements
- **Latest Posts** - Display recent blog posts

### Custom Post Types
- Projects (Portfolio items)
- Testimonials

### Custom Taxonomies
- Project Categories
- Project Types

## Requirements

- WordPress 6.4+
- PHP 7.4+
- Node.js 18+

## Installation

1. Clone the repository into your WordPress plugins directory:
   ```bash
   cd wp-content/plugins
   git clone https://github.com/denischweya/swishfolio-core.git
   ```

2. Install dependencies:
   ```bash
   cd swishfolio-core
   npm install
   ```

3. Build the assets:
   ```bash
   npm run build
   ```

4. Activate the plugin in WordPress admin.

## Development

Start the development server with hot reloading:
```bash
npm run start
```

Build for production:
```bash
npm run build
```

## Third-Party Code

### Build Tools (npm - MIT License)
- @wordpress/scripts
- PostCSS
- Sass
