<?php
/**
 * Raw access to the capability manifests, including the parts BlockCapability drops.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Tests\Evals\Support;

/**
 * BlockCapability deliberately discards the manifest's "excluded" list, because
 * absence is what enforces exclusion at runtime: an attribute the manifest does
 * not expose is unreachable, and a list of names would be a second thing to keep
 * in step with the first.
 *
 * An eval needs that list all the same. "No excluded attribute is attempted" is a
 * claim about what the model asked for, not about what the validator let through,
 * and the two are only the same while the manifest is internally consistent —
 * which this also checks, by refusing a manifest that both exposes and excludes
 * the same attribute.
 */
final class ManifestIndex
{
    /** @var array Block name => decoded manifest. */
    private $manifests = [];

    private function __construct()
    {
    }

    /**
     * @return self
     */
    public static function discover()
    {
        $index = new self();

        foreach ((array) glob(SFCORE_PLUGIN_DIR . 'blocks/*/ai.json') as $file) {
            $manifest = json_decode((string) file_get_contents($file), true);

            if (! is_array($manifest)) {
                continue;
            }

            $blockName = isset($manifest['blockName'])
                ? (string) $manifest['blockName']
                : 'swishfolio-core/' . basename(dirname($file));

            $index->manifests[$blockName] = $manifest;
        }

        foreach ((array) glob(SFCORE_PLUGIN_DIR . 'src/AI/Manifests/core/*.json') as $file) {
            $manifest = json_decode((string) file_get_contents($file), true);

            if (is_array($manifest) && isset($manifest['blockName'])) {
                $index->manifests[(string) $manifest['blockName']] = $manifest;
            }
        }

        ksort($index->manifests);

        return $index;
    }

    /**
     * @param string $blockName Full block name.
     *
     * @return array|null
     */
    public function manifestFor($blockName)
    {
        return isset($this->manifests[$blockName]) ? $this->manifests[$blockName] : null;
    }

    /**
     * @param string $blockName Full block name.
     *
     * @return string[] Attributes the manifest names as deliberately out of reach.
     */
    public function excludedFor($blockName)
    {
        $manifest = $this->manifestFor($blockName);

        if ($manifest === null || ! isset($manifest['excluded'])) {
            return [];
        }

        return array_values(array_map('strval', (array) $manifest['excluded']));
    }

    /**
     * @param string $blockName Full block name.
     *
     * @return string[] Attributes the manifest exposes.
     */
    public function exposedFor($blockName)
    {
        $manifest = $this->manifestFor($blockName);

        if ($manifest === null || ! isset($manifest['capabilities'])) {
            return [];
        }

        $exposed = [];

        foreach ((array) $manifest['capabilities'] as $attributes) {
            foreach (array_keys((array) $attributes) as $attribute) {
                $exposed[] = (string) $attribute;
            }
        }

        return $exposed;
    }

    /**
     * @return string[] Block names carrying a manifest.
     */
    public function blockNames()
    {
        return array_keys($this->manifests);
    }
}
