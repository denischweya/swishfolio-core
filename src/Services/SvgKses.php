<?php
/**
 * Shared wp_kses() allowlist for SVG icons rendered by extensions.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Services;

/**
 * Centralised SVG tag/attribute allow-list used by extensions that emit icon
 * markup server-side (currently ButtonExtension and NavigationExtension).
 *
 * The list is intentionally tight — only the elements/attributes our bundled
 * icon SVGs actually use. Keep it that way so security reviewers can verify
 * nothing user-controlled escapes through wp_kses().
 */
final class SvgKses
{
    /**
     * Return the allowlist passed to wp_kses().
     *
     * @return array<string, array<string, bool>>
     */
    public static function allowlist(): array
    {
        return [
            'svg' => [
                'width'           => true,
                'height'          => true,
                'viewbox'         => true,
                'xmlns'           => true,
                'fill'            => true,
                'stroke'          => true,
                'stroke-width'    => true,
                'stroke-linecap'  => true,
                'stroke-linejoin' => true,
                'aria-hidden'     => true,
                'focusable'       => true,
                'class'           => true,
            ],
            'line'     => [ 'x1' => true, 'y1' => true, 'x2' => true, 'y2' => true ],
            'polyline' => [ 'points' => true, 'fill' => true, 'stroke' => true ],
            'polygon'  => [ 'points' => true, 'fill' => true, 'stroke' => true ],
            'path'     => [ 'd' => true, 'fill' => true, 'stroke' => true ],
            'circle'   => [ 'cx' => true, 'cy' => true, 'r' => true, 'fill' => true, 'stroke' => true ],
            'rect'     => [ 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'ry' => true, 'fill' => true, 'stroke' => true ],
        ];
    }
}
