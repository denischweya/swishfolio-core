<?php
/**
 * Resolves theme.json design tokens for AI consumption.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Context;

/**
 * Maps between the slugs an AI is allowed to emit (accent-1) and the values
 * blocks actually store (var(--wp--preset--color--accent-1)).
 *
 * The theme ships five style variations that each re-skin the whole palette,
 * so an AI-emitted literal colour would survive a variation switch as a broken
 * value. Slugs are the only safe currency: a slug absent from the active
 * theme.json is a validation failure, never a fallback.
 */
class TokenResolver
{
    /**
     * Token set => [CSS preset path segment, theme.json path].
     */
    const SETS = [
        'color'    => ['color', ['settings', 'color', 'palette'], ['settings', 'color', 'defaultPalette']],
        'spacing'  => ['spacing', ['settings', 'spacing', 'spacingSizes'], ['settings', 'spacing', 'defaultSpacingSizes']],
        'fontSize' => ['font-size', ['settings', 'typography', 'fontSizes'], ['settings', 'typography', 'defaultFontSizes']],
    ];

    /**
     * Origin precedence, weakest first. A later origin overrides a slug of the
     * same name declared by an earlier one.
     */
    const ORIGINS = ['default', 'theme', 'custom'];

    /** @var array Token set => list of slugs. */
    private $slugs = [];

    private function __construct(array $slugs)
    {
        $this->slugs = $slugs;
    }

    /**
     * @param array $themeJson Decoded theme.json (or wp_get_global_settings output shape).
     *
     * @return self
     */
    public static function fromArray(array $themeJson)
    {
        $slugs = [];

        foreach (self::SETS as $set => $config) {
            $node            = self::dig($themeJson, $config[1]);
            $defaultsEnabled = self::dig($themeJson, $config[2]);

            $slugs[$set] = self::extractSlugs($node, $defaultsEnabled !== false);
        }

        return new self($slugs);
    }

    /**
     * Build from the live site, merging theme.json with user global styles.
     *
     * @return self
     */
    public static function fromTheme()
    {
        return self::fromArray(['settings' => wp_get_global_settings()]);
    }

    /**
     * @param array $node            Preset node: a flat list, or keyed by origin.
     * @param bool  $defaultsEnabled Whether the "default" origin actually renders.
     *
     * @return string[]
     */
    private static function extractSlugs($node, $defaultsEnabled)
    {
        if (! is_array($node)) {
            return [];
        }

        // A raw theme.json file holds a flat list; wp_get_global_settings()
        // returns the same presets keyed by origin (default/theme/custom).
        $keys     = array_keys($node);
        $isFlat   = $keys === [] || $keys === range(0, count($node) - 1);
        $byOrigin = $isFlat ? ['theme' => $node] : $node;

        $slugs = [];

        foreach (self::ORIGINS as $origin) {
            if ($origin === 'default' && ! $defaultsEnabled) {
                // The theme disabled default presets, so WordPress emits no CSS
                // custom property for them. Offering those slugs to the AI would
                // produce values that resolve to nothing.
                continue;
            }

            if (! isset($byOrigin[$origin]) || ! is_array($byOrigin[$origin])) {
                continue;
            }

            foreach ($byOrigin[$origin] as $preset) {
                if (is_array($preset) && isset($preset['slug'])) {
                    $slug = (string) $preset['slug'];

                    if (! in_array($slug, $slugs, true)) {
                        $slugs[] = $slug;
                    }
                }
            }
        }

        return $slugs;
    }

    /**
     * @param array $data Source array.
     * @param array $path Path segments.
     *
     * @return mixed|null
     */
    private static function dig(array $data, array $path)
    {
        $node = $data;

        foreach ($path as $segment) {
            if (! is_array($node) || ! array_key_exists($segment, $node)) {
                return null;
            }

            $node = $node[$segment];
        }

        return $node;
    }

    /**
     * @param string $tokenSet One of color|spacing|fontSize.
     *
     * @return string[]
     */
    public function getSlugs($tokenSet)
    {
        return isset($this->slugs[$tokenSet]) ? $this->slugs[$tokenSet] : [];
    }

    /**
     * @param string $tokenSet Token set name.
     * @param string $slug     Candidate slug.
     *
     * @return bool
     */
    public function isValid($tokenSet, $slug)
    {
        return in_array((string) $slug, $this->getSlugs($tokenSet), true);
    }

    /**
     * @param string $tokenSet Token set name.
     * @param string $slug     Slug to expand.
     *
     * @return string CSS custom property reference.
     */
    public function toStorageValue($tokenSet, $slug)
    {
        if (! isset(self::SETS[$tokenSet])) {
            return (string) $slug;
        }

        return sprintf('var(--wp--preset--%s--%s)', self::SETS[$tokenSet][0], $slug);
    }

    /**
     * Inverse of toStorageValue(), for rendering before/after in the preview.
     *
     * @param string $tokenSet     Token set name.
     * @param string $storageValue Stored attribute value.
     *
     * @return string|null Slug, or null when the value is not a known token.
     */
    public function toSlug($tokenSet, $storageValue)
    {
        foreach ($this->getSlugs($tokenSet) as $slug) {
            if ($this->toStorageValue($tokenSet, $slug) === $storageValue || $slug === $storageValue) {
                return $slug;
            }
        }

        return null;
    }
}
