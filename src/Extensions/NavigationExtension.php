<?php
/**
 * Navigation Block Extension
 *
 * Extends core/navigation with:
 *  - Overlay alignment (overlay-only)
 *  - Hover style and hover color (both inline and overlay modes)
 *  - Hamburger icon picker (preset library + custom SVG upload)
 * Plus three block style variations for one-click skin presets.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Extensions;

use SwishfolioCore\Services\SvgKses;

class NavigationExtension
{
    /**
     * Open-state hamburger icons. Slugs match the editor's preset list.
     * When the overlay is open core swaps in its own close icon, which we
     * always replace with a single canonical X for predictability.
     */
    private const ICONS = [
        'lines-3' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><rect x="4" y="6" width="16" height="1.5"/><rect x="4" y="11.25" width="16" height="1.5"/><rect x="4" y="16.5" width="16" height="1.5"/></svg>',
        'lines-2' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><rect x="4" y="8.25" width="16" height="1.5"/><rect x="4" y="14.25" width="16" height="1.5"/></svg>',
        'x'       => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true" focusable="false"><line x1="6" y1="6" x2="18" y2="18"/><line x1="18" y1="6" x2="6" y2="18"/></svg>',
        'dots'    => '<svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><circle cx="6" cy="12" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="18" cy="12" r="1.5"/></svg>',
        'plus'    => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true" focusable="false"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>',
        'grid'    => '<svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><rect x="4" y="4" width="5" height="5"/><rect x="10" y="4" width="5" height="5"/><rect x="16" y="4" width="5" height="5"/><rect x="4" y="10" width="5" height="5"/><rect x="10" y="10" width="5" height="5"/><rect x="16" y="10" width="5" height="5"/><rect x="4" y="16" width="5" height="5"/><rect x="10" y="16" width="5" height="5"/><rect x="16" y="16" width="5" height="5"/></svg>',
    ];

    /**
     * Universal close icon (overlay open state). Always X for clarity.
     */
    private const CLOSE_ICON = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true" focusable="false"><line x1="6" y1="6" x2="18" y2="18"/><line x1="18" y1="6" x2="6" y2="18"/></svg>';

    public function __construct()
    {
        add_action('enqueue_block_editor_assets', [ $this, 'enqueueEditorAssets' ]);
        add_action('wp_enqueue_scripts', [ $this, 'enqueueFrontendStyles' ]);
        add_action('enqueue_block_assets', [ $this, 'enqueueFrontendStyles' ]);
        add_action('init', [ $this, 'registerVariations' ]);
        add_filter('render_block', [ $this, 'renderBlock' ], 10, 2);
    }

    public function enqueueEditorAssets(): void
    {
        $asset_file = SFCORE_PLUGIN_DIR . 'assets/js/navigation-extension.asset.php';

        $asset = file_exists($asset_file)
            ? require $asset_file
            : [
                'dependencies' => [ 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-compose', 'wp-hooks', 'wp-i18n' ],
                'version'      => SFCORE_VERSION,
            ];

        wp_enqueue_script(
            'sfcore-navigation-extension',
            SFCORE_PLUGIN_URL . 'assets/js/navigation-extension.js',
            $asset['dependencies'],
            $asset['version'],
            true
        );

        wp_localize_script(
            'sfcore-navigation-extension',
            'sfcoreNavIcons',
            self::ICONS
        );
    }

    public function enqueueFrontendStyles(): void
    {
        wp_enqueue_style(
            'sfcore-navigation-extension',
            SFCORE_PLUGIN_URL . 'assets/css/navigation-extension.css',
            [],
            SFCORE_VERSION
        );
    }

    /**
     * Register the three block style variations the user can pick from the
     * Styles panel. The CSS for each variation lives in navigation-extension.css.
     */
    public function registerVariations(): void
    {
        if (! function_exists('register_block_style')) {
            return;
        }

        register_block_style('core/navigation', [
            'name'  => 'default',
            'label' => __('Default', 'swishfolio-core'),
        ]);

        register_block_style('core/navigation', [
            'name'  => 'centered-overlay',
            'label' => __('Centered Overlay', 'swishfolio-core'),
        ]);

        register_block_style('core/navigation', [
            'name'  => 'minimal',
            'label' => __('Minimal', 'swishfolio-core'),
        ]);
    }

    /**
     * Inject alignment/hover classes, hover-color CSS var, and the chosen
     * hamburger icon into the rendered core/navigation HTML.
     */
    public function renderBlock(string $block_content, array $block): string
    {
        if (($block['blockName'] ?? '') !== 'core/navigation') {
            return $block_content;
        }

        $attrs        = $block['attrs'] ?? [];
        $align        = $attrs['sfcoreOverlayAlign'] ?? '';
        $hover_style  = $attrs['sfcoreHoverStyle'] ?? '';
        $hover_color  = $attrs['sfcoreHoverColor'] ?? '';
        $icon         = $attrs['sfcoreHamburgerIcon'] ?? null;

        $has_custom_settings = $align || $hover_style || $hover_color || ! empty($icon);
        if (! $has_custom_settings) {
            return $block_content;
        }

        // Compose classes + inline style for the wrapper.
        $class_parts = [];
        if ($align) {
            $class_parts[] = 'sfcore-nav--align-' . sanitize_html_class($align);
        }
        if ($hover_style && 'none' !== $hover_style) {
            $class_parts[] = 'sfcore-nav--hover-' . sanitize_html_class($hover_style);
        }
        $class_to_add = implode(' ', $class_parts);

        $style_attr = '';
        if ($hover_color) {
            $style_attr = sprintf(
                'style="--sfcore-nav-hover-color:%s"',
                esc_attr($hover_color)
            );
        }

        if ($class_to_add || $style_attr) {
            // Target the first element whose class contains `wp-block-navigation`
            // — that is the nav's outer wrapper. Avoids landing the class on
            // any pattern/wrapper element WP might emit before it.
            $block_content = preg_replace_callback(
                '/(<\w+\b[^>]*class=("|\'))([^"\']*\bwp-block-navigation\b[^"\']*)(("|\')[^>]*>)/',
                function ($matches) use ($class_to_add, $style_attr) {
                    $prefix = $matches[1];
                    $classes = $matches[3];
                    $suffix = $matches[4];

                    if ($class_to_add) {
                        $classes .= ' ' . $class_to_add;
                    }

                    $rebuilt = $prefix . $classes . $suffix;
                    if ($style_attr) {
                        // Insert the inline style just before the closing >.
                        $rebuilt = preg_replace('/>$/', ' ' . $style_attr . '>', $rebuilt, 1);
                    }
                    return $rebuilt;
                },
                $block_content,
                1
            );
        }

        // Swap the open-button icon.
        $open_svg = $this->resolveIconSvg($icon);
        if ($open_svg) {
            $block_content = $this->replaceButtonContent(
                $block_content,
                'wp-block-navigation__responsive-container-open',
                $open_svg
            );
        }

        // Always swap the close-button icon to the canonical X.
        $block_content = $this->replaceButtonContent(
            $block_content,
            'wp-block-navigation__responsive-container-close',
            wp_kses(self::CLOSE_ICON, SvgKses::allowlist())
        );

        return $block_content;
    }

    /**
     * Resolve the user's icon choice to a sanitised SVG string.
     *
     * @param mixed $icon Either a preset slug (string), or an object/array
     *                    {type: 'custom', url: '<media library svg url>'}.
     *                    Anything else returns empty string (no swap).
     */
    private function resolveIconSvg($icon): string
    {
        if (is_string($icon) && isset(self::ICONS[ $icon ])) {
            return wp_kses(self::ICONS[ $icon ], SvgKses::allowlist());
        }

        // Custom upload — fetch the SVG file from the Media Library and pass
        // through the same allow-list.
        if (is_array($icon) && ($icon['type'] ?? '') === 'custom' && ! empty($icon['url'])) {
            $url = esc_url_raw($icon['url']);
            // Resolve to local file path; only allow files inside uploads.
            $upload_dir = wp_get_upload_dir();
            if (strpos($url, $upload_dir['baseurl']) !== 0) {
                return '';
            }
            $path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $url);
            if (! is_readable($path)) {
                return '';
            }
            $contents = file_get_contents($path);
            if (false === $contents) {
                return '';
            }
            return wp_kses($contents, SvgKses::allowlist());
        }

        return '';
    }

    /**
     * Replace the inner HTML of the first <button> whose class contains
     * $class_marker with $replacement.
     */
    private function replaceButtonContent(string $html, string $class_marker, string $replacement): string
    {
        $pattern = '/(<button\b[^>]*class="[^"]*' . preg_quote($class_marker, '/') . '[^"]*"[^>]*>)[\s\S]*?(<\/button>)/';
        return preg_replace($pattern, '$1' . $replacement . '$2', $html, 1) ?? $html;
    }
}
