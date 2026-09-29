<?php
/**
 * Registry of AI-editable block capabilities.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Blocks;

/**
 * Discovers, caches and serves the ai.json capability manifests.
 *
 * Manifests are found in two shapes: per-block files at <dir>/<block>/ai.json
 * (this plugin's own blocks) and flat files at <dir>/*.json carrying an
 * explicit blockName (the core-block manifests). Discovery is lazy — nothing
 * is read until an AI request actually asks for a capability.
 */
class BlockRegistry
{
    const CACHE_PREFIX = 'sfcore_ai_manifests_';
    const CACHE_TTL    = 86400;

    /** @var string[] */
    private $manifestDirs;

    /** @var BlockCapability[] */
    private $capabilities = [];

    /** @var bool */
    private $discovered = false;

    /**
     * @param string[] $manifestDirs Directories to scan for manifests.
     */
    public function __construct(array $manifestDirs)
    {
        $this->manifestDirs = $manifestDirs;
    }

    /**
     * Register a manifest directly, bypassing discovery.
     *
     * A malformed manifest disables AI for that block rather than raising:
     * a bad manifest must never break the editor.
     *
     * @param string $blockName Full block name.
     * @param array  $manifest  Manifest array.
     *
     * @return void
     */
    public function register($blockName, array $manifest)
    {
        try {
            $this->capabilities[$blockName] = BlockCapability::fromArray($blockName, $manifest);
        } catch (\InvalidArgumentException $e) {
            unset($this->capabilities[$blockName]);
        }
    }

    /**
     * @param string $blockName Full block name.
     *
     * @return bool
     */
    public function has($blockName)
    {
        $this->discover();

        return isset($this->capabilities[$blockName]);
    }

    /**
     * @param string $blockName Full block name.
     *
     * @return BlockCapability|null
     */
    public function get($blockName)
    {
        $this->discover();

        return isset($this->capabilities[$blockName]) ? $this->capabilities[$blockName] : null;
    }

    /**
     * @return BlockCapability[] Keyed by block name.
     */
    public function all()
    {
        $this->discover();

        return $this->capabilities;
    }

    /**
     * Populate the registry from cache or disk, once per instance.
     *
     * @return void
     */
    private function discover()
    {
        if ($this->discovered) {
            return;
        }

        $this->discovered = true;

        $cacheKey = self::CACHE_PREFIX . SFCORE_VERSION;
        $raw      = get_transient($cacheKey);

        if (! is_array($raw)) {
            $raw = $this->readFromDisk();
            set_transient($cacheKey, $raw, self::CACHE_TTL);
        }

        /**
         * Filters the raw AI capability manifests before they are parsed.
         *
         * @param array $raw Manifest arrays keyed by block name.
         */
        $raw = apply_filters('swishfolio_ai_block_manifests', $raw);

        foreach ($raw as $blockName => $manifest) {
            if (! isset($this->capabilities[$blockName]) && is_array($manifest)) {
                $this->register($blockName, $manifest);
            }
        }
    }

    /**
     * @return array Raw manifest arrays keyed by block name.
     */
    private function readFromDisk()
    {
        $raw = [];

        foreach ($this->manifestDirs as $dir) {
            $dir = rtrim($dir, '/');

            foreach ((array) glob($dir . '/*/ai.json') as $file) {
                $manifest = json_decode((string) file_get_contents($file), true);

                if (! is_array($manifest)) {
                    continue;
                }

                $blockName = isset($manifest['blockName'])
                    ? $manifest['blockName']
                    : 'swishfolio-core/' . basename(dirname($file));

                $raw[$blockName] = $manifest;
            }

            foreach ((array) glob($dir . '/*.json') as $file) {
                $manifest = json_decode((string) file_get_contents($file), true);

                if (is_array($manifest) && isset($manifest['blockName'])) {
                    $raw[$manifest['blockName']] = $manifest;
                }
            }
        }

        return $raw;
    }
}
