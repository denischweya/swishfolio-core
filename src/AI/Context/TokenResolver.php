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
        'color'    => ['color', ['settings', 'color', 'palette']],
        'spacing'  => ['spacing', ['settings', 'spacing', 'spacingSizes']],
        'fontSize' => ['font-size', ['settings', 'typography', 'fontSizes']],
    ];

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
            $node = $themeJson;

            foreach ($config[1] as $segment) {
                $node = isset($node[$segment]) ? $node[$segment] : null;

                if ($node === null) {
                    break;
                }
            }

            $slugs[$set] = [];

            foreach ((array) $node as $preset) {
                if (is_array($preset) && isset($preset['slug'])) {
                    $slugs[$set][] = (string) $preset['slug'];
                }
            }
        }

        return new self($slugs);
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
