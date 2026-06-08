<?php
/**
 * WP-CLI command to migrate hardcoded hex colors in stored post_content
 * to palette references after the pattern/block refactor.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Commands;

/**
 * Rewrites Swishfolio block attributes containing the OLD hardcoded hex
 * palette to current `var(--wp--preset--color--*)` references or slug names,
 * so existing posts follow theme style variations.
 *
 * ## EXAMPLES
 *
 *     # Preview changes on the current site without writing.
 *     wp swishfolio migrate-colors --dry-run
 *
 *     # Run on the current site only.
 *     wp swishfolio migrate-colors
 *
 *     # Run across every site in the network.
 *     wp swishfolio migrate-colors --network
 *
 *     # Limit to a single post type.
 *     wp swishfolio migrate-colors --post-type=page
 */
class MigrateColorsCommand
{
    /**
     * Block-attribute hex → replacement map. Dotted keys traverse nested attrs
     * (e.g. `style.border.color`).
     */
    private const ATTRIBUTE_MAPPINGS = [
        'swishfolio-core/hero' => [
            'backgroundColor'   => [ '#1040C0' => 'var(--wp--preset--color--accent-1)' ],
            'overlayColor'      => [ '#121212' => 'var(--wp--preset--color--contrast)' ],
            'imageBorderColor'  => [ '#121212' => 'var(--wp--preset--color--contrast)' ],
            'textColor'         => [ '#F0F0F0' => 'var(--wp--preset--color--base)' ],
            'decoShape1Color'   => [ '#F0C020' => 'var(--wp--preset--color--accent-2)' ],
            'decoShape2Color'   => [ '#D02020' => 'var(--wp--preset--color--accent-1)' ],
            'decorativeColor'   => [ '#F0C020' => 'var(--wp--preset--color--accent-2)' ],
            'bgShape1Color'     => [ '#F0C020' => 'var(--wp--preset--color--accent-2)' ],
            'bgShape2Color'     => [ '#D02020' => 'var(--wp--preset--color--accent-1)' ],
            'bgShape3Color'     => [ '#1040C0' => 'var(--wp--preset--color--accent-4)' ],
            'imageBlob1Color'   => [ '#F0C020' => 'var(--wp--preset--color--accent-2)' ],
            'imageBlob2Color'   => [ '#D02020' => 'var(--wp--preset--color--accent-1)' ],
        ],
        'swishfolio-core/card' => [
            'iconColor' => [
                '#D02020' => 'var(--wp--preset--color--accent-1)',
                '#1040C0' => 'var(--wp--preset--color--accent-4)',
                '#F0C020' => 'var(--wp--preset--color--accent-2)',
            ],
            'decorativeColor' => [
                '#F0C020' => 'var(--wp--preset--color--accent-2)',
                '#D02020' => 'var(--wp--preset--color--accent-1)',
                '#1040C0' => 'var(--wp--preset--color--accent-4)',
            ],
            'backgroundColor'    => [ '#1A1A1A' => 'accent-6' ],
            'style.border.color' => [
                '#F0C020' => 'var:preset|color|accent-2',
                '#D02020' => 'var:preset|color|accent-1',
                '#1040C0' => 'var:preset|color|accent-4',
            ],
        ],
        'swishfolio-core/features' => [
            'titleColor'          => [ '#FFFDF5' => 'var(--wp--preset--color--base)' ],
            'ctaTextColor'        => [ '#FFD93D' => 'var(--wp--preset--color--accent-2)' ],
            'cardBackgroundColor' => [ '#000000' => 'var(--wp--preset--color--contrast)' ],
            'cardAccentColor'     => [ '#FFD93D' => 'var(--wp--preset--color--accent-2)' ],
        ],
        'swishfolio-core/feature-card' => [
            'cardBackgroundColor' => [
                '#131313' => 'var(--wp--preset--color--accent-6)',
                '#191919' => 'var(--wp--preset--color--accent-6)',
                '#1c1c1c' => 'var(--wp--preset--color--accent-6)',
            ],
        ],
    ];

    /**
     * Inline CSS-variable replacements applied to innerHTML/innerContent.
     */
    private const INLINE_CSS_MAPPINGS = [
        'features-title-color'    => [ '#FFFDF5' => 'var(--wp--preset--color--base)' ],
        'features-cta-text-color' => [ '#FFD93D' => 'var(--wp--preset--color--accent-2)' ],
        'features-card-bg'        => [ '#000000' => 'var(--wp--preset--color--contrast)' ],
        'features-card-accent'    => [ '#FFD93D' => 'var(--wp--preset--color--accent-2)' ],
        'card-bg'                 => [
            '#131313' => 'var(--wp--preset--color--accent-6)',
            '#191919' => 'var(--wp--preset--color--accent-6)',
            '#1c1c1c' => 'var(--wp--preset--color--accent-6)',
        ],
    ];

    public function __invoke($args, $assoc_args): void
    {
        $dry_run   = (bool) ( $assoc_args['dry-run'] ?? false );
        $network   = (bool) ( $assoc_args['network'] ?? false );
        $post_type = $assoc_args['post-type'] ?? null;

        if ( $network ) {
            if ( ! is_multisite() ) {
                \WP_CLI::error( '--network requires a multisite install.' );
            }
            $sites       = get_sites( [ 'fields' => 'ids' ] );
            $grand_total = 0;
            foreach ( $sites as $site_id ) {
                switch_to_blog( (int) $site_id );
                \WP_CLI::log( sprintf( "\n=== Site #%d: %s ===", $site_id, get_bloginfo( 'name' ) ) );
                $grand_total += $this->migrate_site( $dry_run, $post_type );
                restore_current_blog();
            }
            \WP_CLI::log( sprintf( "\nTotal posts %s: %d", $dry_run ? 'that would change' : 'changed', $grand_total ) );
        } else {
            $this->migrate_site( $dry_run, $post_type );
        }

        \WP_CLI::success( $dry_run ? 'Dry run complete (no posts written).' : 'Migration complete.' );
    }

    private function migrate_site( bool $dry_run, ?string $post_type ): int
    {
        $query_args = [
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'post_type'      => $post_type ?: 'any',
        ];

        $post_ids = get_posts( $query_args );
        $changed  = 0;

        foreach ( $post_ids as $post_id ) {
            $original = get_post_field( 'post_content', $post_id );
            if ( ! is_string( $original ) || '' === $original ) {
                continue;
            }

            $updated = $this->migrate_content( $original );
            if ( $original === $updated ) {
                continue;
            }

            $changed++;
            \WP_CLI::log( sprintf(
                '  %s #%d: %s',
                $dry_run ? '[dry-run] would update' : 'updated',
                $post_id,
                get_the_title( $post_id ) ?: '(no title)'
            ) );

            if ( ! $dry_run ) {
                wp_update_post( [
                    'ID'           => $post_id,
                    'post_content' => $updated,
                ] );
            }
        }

        \WP_CLI::log( sprintf( '  %d posts %s', $changed, $dry_run ? 'would change' : 'changed' ) );
        return $changed;
    }

    private function migrate_content( string $content ): string
    {
        $blocks = parse_blocks( $content );
        $blocks = $this->migrate_blocks( $blocks );
        return serialize_blocks( $blocks );
    }

    /**
     * @param array $blocks
     * @return array
     */
    private function migrate_blocks( array $blocks ): array
    {
        foreach ( $blocks as &$block ) {
            $name = $block['blockName'] ?? '';

            if ( $name && isset( self::ATTRIBUTE_MAPPINGS[ $name ] ) ) {
                $block['attrs'] = $this->migrate_attrs(
                    $block['attrs'] ?? [],
                    self::ATTRIBUTE_MAPPINGS[ $name ]
                );
            }

            if ( ! empty( $block['innerHTML'] ) ) {
                $block['innerHTML'] = $this->migrate_inline_css( $block['innerHTML'] );
            }

            if ( ! empty( $block['innerContent'] ) && is_array( $block['innerContent'] ) ) {
                foreach ( $block['innerContent'] as &$chunk ) {
                    if ( is_string( $chunk ) ) {
                        $chunk = $this->migrate_inline_css( $chunk );
                    }
                }
                unset( $chunk );
            }

            if ( ! empty( $block['innerBlocks'] ) ) {
                $block['innerBlocks'] = $this->migrate_blocks( $block['innerBlocks'] );
            }
        }
        unset( $block );

        return $blocks;
    }

    private function migrate_attrs( array $attrs, array $mappings ): array
    {
        foreach ( $mappings as $path => $value_map ) {
            $current = $this->get_nested( $attrs, $path );
            if ( null === $current || ! isset( $value_map[ $current ] ) ) {
                continue;
            }
            $attrs = $this->set_nested( $attrs, $path, $value_map[ $current ] );
        }
        return $attrs;
    }

    private function migrate_inline_css( string $html ): string
    {
        foreach ( self::INLINE_CSS_MAPPINGS as $var_name => $mappings ) {
            foreach ( $mappings as $hex => $replacement ) {
                $pattern = '/(--' . preg_quote( $var_name, '/' ) . ':\s*)' . preg_quote( $hex, '/' ) . '\b/i';
                $html    = preg_replace( $pattern, '${1}' . $replacement, $html );
            }
        }
        return $html;
    }

    /**
     * @param array  $arr
     * @param string $path Dotted path, e.g. "style.border.color".
     * @return mixed|null
     */
    private function get_nested( array $arr, string $path )
    {
        $keys = explode( '.', $path );
        foreach ( $keys as $key ) {
            if ( ! is_array( $arr ) || ! array_key_exists( $key, $arr ) ) {
                return null;
            }
            $arr = $arr[ $key ];
        }
        return $arr;
    }

    private function set_nested( array $arr, string $path, $value ): array
    {
        $keys    = explode( '.', $path );
        $cursor  = &$arr;
        $last    = array_pop( $keys );
        foreach ( $keys as $key ) {
            if ( ! isset( $cursor[ $key ] ) || ! is_array( $cursor[ $key ] ) ) {
                $cursor[ $key ] = [];
            }
            $cursor = &$cursor[ $key ];
        }
        $cursor[ $last ] = $value;
        unset( $cursor );
        return $arr;
    }
}
