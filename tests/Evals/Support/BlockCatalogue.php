<?php
/**
 * Which block names actually exist on this install.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Tests\Evals\Support;

/**
 * "Every block used is registered" is a different claim from "every block used
 * has a capability manifest", and conflating them is how a plan ends up naming a
 * block that was renamed or removed: the manifest is a file in this repository,
 * the registration is a block.json.
 *
 * So registration is read from block.json — this plugin's own under blocks/, and
 * WordPress's own under wp-includes/blocks/ when the eval suite is running inside
 * a WordPress tree, which is how it is checked out. When core is not reachable
 * the core half is reported as unavailable rather than quietly passing.
 */
final class BlockCatalogue
{
    /** @var string[] */
    private $pluginBlocks = [];

    /** @var string[] */
    private $coreBlocks = [];

    /** @var bool */
    private $coreAvailable = false;

    private function __construct()
    {
    }

    /**
     * @return self
     */
    public static function discover()
    {
        $catalogue = new self();

        $catalogue->pluginBlocks = self::readNames(SFCORE_PLUGIN_DIR . 'blocks');

        $coreDir = self::coreBlockDir();

        if ($coreDir !== null) {
            $catalogue->coreAvailable = true;
            $catalogue->coreBlocks    = self::readNames($coreDir);
        }

        return $catalogue;
    }

    /**
     * @return string|null Absolute path to WordPress's bundled block directory.
     */
    public static function coreBlockDir()
    {
        $wordpress = dirname(rtrim(SFCORE_PLUGIN_DIR, '/'), 3);
        $blocks    = $wordpress . '/wp-includes/blocks';

        return is_dir($blocks) ? $blocks : null;
    }

    /**
     * @return bool Whether core block names can be verified at all.
     */
    public function isCoreAvailable()
    {
        return $this->coreAvailable;
    }

    /**
     * @param string $blockName Full block name.
     *
     * @return bool
     */
    public function isRegistered($blockName)
    {
        $blockName = (string) $blockName;

        if (in_array($blockName, $this->pluginBlocks, true)) {
            return true;
        }

        return in_array($blockName, $this->coreBlocks, true);
    }

    /**
     * Whether this name can be checked at all, so an unverifiable core block is
     * reported as a gap rather than counted as a pass.
     *
     * @param string $blockName Full block name.
     *
     * @return bool
     */
    public function isVerifiable($blockName)
    {
        return strpos((string) $blockName, 'core/') !== 0 || $this->coreAvailable;
    }

    /**
     * @return string[]
     */
    public function pluginBlocks()
    {
        return $this->pluginBlocks;
    }

    /**
     * @return string[]
     */
    public function coreBlocks()
    {
        return $this->coreBlocks;
    }

    /**
     * @param string $dir Directory of block directories.
     *
     * @return string[]
     */
    private static function readNames($dir)
    {
        $names = [];

        foreach ((array) glob(rtrim($dir, '/') . '/*/block.json') as $file) {
            $metadata = json_decode((string) file_get_contents($file), true);

            if (is_array($metadata) && isset($metadata['name'])) {
                $names[] = (string) $metadata['name'];
            }
        }

        sort($names);

        return $names;
    }
}
