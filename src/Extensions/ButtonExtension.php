<?php
/**
 * Button Block Extension
 *
 * Extends core/button with:
 *  - Icon (before/after the link text)
 *  - Hover text + background color
 *  - Hover animation variant (lift / scale / slide / shift)
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Extensions;

use SwishfolioCore\Services\SvgKses;

class ButtonExtension
{

    /**
     * Server-side SVG icon library. Same slugs as the editor map in
     * src/js/button-extension.js — keep them in sync when adding icons.
     */
    private const ICONS = [
        'arrow-right'    => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>',
        'arrow-left'     => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>',
        'arrow-up-right' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><line x1="7" y1="17" x2="17" y2="7"/><polyline points="7 7 17 7 17 17"/></svg>',
        'arrow-down'     => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>',
        'chevron-right'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><polyline points="9 6 15 12 9 18"/></svg>',
        'plus'           => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>',
        'check'          => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><polyline points="20 6 9 17 4 12"/></svg>',
        'download'       => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>',
        'external'       => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>',
    ];

    public function __construct()
    {
        add_action('enqueue_block_editor_assets', [ $this, 'enqueueEditorAssets' ]);
        add_action('wp_enqueue_scripts', [ $this, 'enqueueFrontendStyles' ]);
        add_action('enqueue_block_assets', [ $this, 'enqueueFrontendStyles' ]);
        add_filter('render_block', [ $this, 'renderBlock' ], 10, 2);
    }

    public function enqueueEditorAssets(): void
    {
        $asset_file = SFCORE_PLUGIN_DIR . 'assets/js/button-extension.asset.php';

        if (file_exists($asset_file)) {
            $asset = require $asset_file;
        } else {
            $asset = [
                'dependencies' => [ 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-compose', 'wp-hooks', 'wp-i18n' ],
                'version'      => SFCORE_VERSION,
            ];
        }

        wp_enqueue_script(
            'sfcore-button-extension',
            SFCORE_PLUGIN_URL . 'assets/js/button-extension.js',
            $asset['dependencies'],
            $asset['version'],
            true
        );

        wp_localize_script(
            'sfcore-button-extension',
            'sfcoreButtonIcons',
            self::ICONS
        );

        wp_enqueue_style(
            'sfcore-button-extension-editor',
            SFCORE_PLUGIN_URL . 'assets/css/button-extension-editor.css',
            [ 'sfcore-button-extension-frontend' ],
            SFCORE_VERSION
        );
    }

    public function enqueueFrontendStyles(): void
    {
        wp_enqueue_style(
            'sfcore-button-extension-frontend',
            SFCORE_PLUGIN_URL . 'assets/css/button-extension.css',
            [],
            SFCORE_VERSION
        );
    }

    /**
     * Inject icon, hover-color CSS vars, and animation class into core/button.
     */
    public function renderBlock(string $block_content, array $block): string
    {
        if (($block['blockName'] ?? '') !== 'core/button') {
            return $block_content;
        }

        $attrs           = $block['attrs'] ?? [];
        $icon            = $attrs['sfcoreIcon'] ?? '';
        $icon_position   = $attrs['sfcoreIconPosition'] ?? 'after';
        $hover_text      = $attrs['sfcoreHoverTextColor'] ?? '';
        $hover_bg        = $attrs['sfcoreHoverBgColor'] ?? '';
        $hover_animation = $attrs['sfcoreHoverAnimation'] ?? '';

        if (! $icon && ! $hover_text && ! $hover_bg && ! $hover_animation) {
            return $block_content;
        }

        // Insert icon SVG inside the .wp-block-button__link.
        if ($icon && isset(self::ICONS[ $icon ])) {
            // Both the icon SVG and the wrapper attrs are run through their
            // appropriate escape function before reaching the output.
            $svg = wp_kses(self::ICONS[ $icon ], SvgKses::allowlist());

            $icon_html = sprintf(
                '<span class="sfcore-btn-icon sfcore-btn-icon--%s" aria-hidden="true">%s</span>',
                esc_attr($icon_position),
                $svg
            );

            if ('before' === $icon_position) {
                $block_content = preg_replace(
                    '/(<a\b[^>]*class="[^"]*wp-block-button__link[^"]*"[^>]*>)/',
                    '$1' . $icon_html,
                    $block_content,
                    1
                );
            } else {
                $block_content = preg_replace(
                    '/(<\/a>)(?![\s\S]*<\/a>)/',
                    $icon_html . '$1',
                    $block_content,
                    1
                );
            }
        }

        // Compose inline style + classes to attach to the outer .wp-block-button.
        $style_parts = [];
        if ($hover_text) {
            $style_parts[] = '--sfcore-btn-hover-text:' . esc_attr($hover_text);
        }
        if ($hover_bg) {
            $style_parts[] = '--sfcore-btn-hover-bg:' . esc_attr($hover_bg);
        }
        $style_attr = $style_parts ? 'style="' . implode(';', $style_parts) . '"' : '';

        $class_parts = [];
        if ($hover_text || $hover_bg) {
            $class_parts[] = 'sfcore-btn--has-hover';
        }
        if ($hover_animation && 'none' !== $hover_animation) {
            $class_parts[] = 'sfcore-btn--anim-' . sanitize_html_class($hover_animation);
        }
        if ($icon) {
            $class_parts[] = 'sfcore-btn--has-icon';
        }
        $class_to_add = implode(' ', $class_parts);

        if ($class_to_add || $style_attr) {
            // Augment the FIRST element's class attribute (the .wp-block-button wrapper).
            $block_content = preg_replace_callback(
                '/^(\s*<\w+)([^>]*)(>)/',
                function ($matches) use ($class_to_add, $style_attr) {
                    $opening = $matches[1];
                    $rest    = $matches[2];
                    $close   = $matches[3];

                    if ($class_to_add) {
                        if (preg_match('/\sclass=("|\')([^"\']*)("|\')/', $rest)) {
                            $rest = preg_replace(
                                '/\sclass=("|\')([^"\']*)("|\')/',
                                ' class=$1$2 ' . $class_to_add . '$3',
                                $rest,
                                1
                            );
                        } else {
                            $rest .= ' class="' . $class_to_add . '"';
                        }
                    }

                    if ($style_attr) {
                        $rest .= ' ' . $style_attr;
                    }

                    return $opening . $rest . $close;
                },
                $block_content,
                1
            );
        }

        return $block_content;
    }
}
