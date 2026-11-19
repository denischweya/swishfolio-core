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

### Additional Features
- SVG upload support with sanitization

## Requirements

- WordPress 6.7+
- PHP 7.4+
- Node.js 18+ (only to rebuild the assets from source)

## Installation

### From a release (recommended)

The built blocks and scripts are committed, so a release download is ready to use: no Node or Composer needed.

1. Download the zip of the latest release from
   [github.com/denischweya/swishfolio-core/releases/latest](https://github.com/denischweya/swishfolio-core/releases/latest)
   (for 1.3.0: [swishfolio-core-1.3.0.zip](https://github.com/denischweya/swishfolio-core/archive/refs/tags/v1.3.0.zip)).
2. In WordPress go to **Plugins > Add New Plugin > Upload Plugin**, choose the zip and click **Install Now**.
3. Activate **Swishfolio Core**. It works best with the free Swishfolio block theme.
4. Optional: connect an AI provider under **Swish AI > Connections** to use the AI features.

The zip unpacks to a folder named `swishfolio-core-1.3.0`. That works as is; rename it to `swishfolio-core` if you
install by hand over FTP, so later versions replace it instead of installing alongside it.

### From source (development)

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
