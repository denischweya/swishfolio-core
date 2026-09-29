<?php
/**
 * The catalogue of things page generation is allowed to compose.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Generation;

/**
 * Discovers generation units from sidecar JSON files, exactly as BlockRegistry
 * discovers ai.json capability manifests, and resolves each unit to raw block
 * markup.
 *
 * Units that name a "ref" borrow the markup of a pattern registered with
 * WordPress. Units that name a "file" read a slot-bearing HTML file from the
 * sidecar directory; those files are deliberately never registered as WordPress
 * patterns, so the inserter stays clean and a slot token can never reach a user.
 */
class PatternLibrary
{
    const CACHE_PREFIX = 'sfcore_generation_units_';
    const CACHE_TTL    = 86400;

    /** @var string[] */
    private $unitDirs;

    /** @var callable|null Given a pattern slug, returns its markup or null. */
    private $refResolver;

    /** @var PatternUnit[] */
    private $units = [];

    /** @var bool */
    private $discovered = false;

    /**
     * @param string[]      $unitDirs    Directories holding sidecar JSON files.
     * @param callable|null $refResolver Resolver for registered-pattern markup.
     */
    public function __construct(array $unitDirs, $refResolver = null)
    {
        $this->unitDirs    = $unitDirs;
        $this->refResolver = $refResolver;
    }

    /**
     * @param string $id Unit id.
     *
     * @return PatternUnit|null
     */
    public function get($id)
    {
        $this->discover();

        return isset($this->units[$id]) ? $this->units[$id] : null;
    }

    /**
     * @param string $id Unit id.
     *
     * @return bool
     */
    public function has($id)
    {
        return $this->get($id) !== null;
    }

    /**
     * @return PatternUnit[] Keyed by unit id.
     */
    public function all()
    {
        $this->discover();

        return $this->units;
    }

    /**
     * Units usable in this context, so the prompt carries nothing unusable.
     *
     * A unit listing style variations is only offered under one of them; a unit
     * needing media is never offered, because media is out of scope and a unit
     * with a missing image is worse than no unit at all.
     *
     * @param string      $scope          "page" or "section".
     * @param string|null $styleVariation Active style variation title.
     *
     * @return PatternUnit[]
     */
    public function usable($scope, $styleVariation = null)
    {
        $usable = [];

        foreach ($this->all() as $id => $unit) {
            if ($unit->getScope() !== $scope) {
                continue;
            }

            if ($unit->getRequiresMedia() > 0 || ! $unit->isKsesStable()) {
                continue;
            }

            $variations = $unit->getStyleVariations();

            if ($variations !== [] && ($styleVariation === null || ! in_array($styleVariation, $variations, true))) {
                continue;
            }

            $usable[$id] = $unit;
        }

        return $usable;
    }

    /**
     * Promptable catalogue: roles, summaries, capacities and slots. No markup.
     *
     * @param string      $scope          "page" or "section".
     * @param string|null $styleVariation Active style variation title.
     *
     * @return array
     */
    public function describe($scope, $styleVariation = null)
    {
        $described = [];

        foreach ($this->usable($scope, $styleVariation) as $id => $unit) {
            $described[$id] = $unit->describe();
        }

        ksort($described);

        return $described;
    }

    /**
     * Raw block markup for one unit, before slot substitution.
     *
     * @param string $id Unit id.
     *
     * @throws \RuntimeException When the unit or its markup cannot be resolved.
     *
     * @return string
     */
    public function markup($id)
    {
        $unit = $this->get($id);

        if ($unit === null) {
            throw new \RuntimeException(sprintf('Unknown generation unit "%s".', $id));
        }

        if ($unit->getFile() !== null) {
            return $this->readFile($unit);
        }

        $markup = $this->resolveRef($unit->getRef());

        if ($markup === null || trim($markup) === '') {
            throw new \RuntimeException(
                sprintf('Pattern "%s" is not registered on this site.', $unit->getRef())
            );
        }

        return $markup;
    }

    /**
     * @param PatternUnit $unit Unit to read.
     *
     * @throws \RuntimeException When the markup file is missing.
     *
     * @return string
     */
    private function readFile(PatternUnit $unit)
    {
        foreach ($this->unitDirs as $dir) {
            $file = rtrim($dir, '/') . '/' . $unit->getFile();

            if (is_file($file)) {
                return trim((string) file_get_contents($file));
            }
        }

        throw new \RuntimeException(
            sprintf('Generation unit "%s" has no markup file "%s".', $unit->getId(), $unit->getFile())
        );
    }

    /**
     * @param string $ref Registered pattern slug.
     *
     * @return string|null
     */
    private function resolveRef($ref)
    {
        if ($this->refResolver !== null) {
            return call_user_func($this->refResolver, $ref);
        }

        if (! class_exists('\WP_Block_Patterns_Registry')) {
            return null;
        }

        $registry = \WP_Block_Patterns_Registry::get_instance();

        if (! $registry->is_registered($ref)) {
            return null;
        }

        $pattern = $registry->get_registered($ref);

        return isset($pattern['content']) ? (string) $pattern['content'] : null;
    }

    /**
     * Populate the library from cache or disk, once per instance.
     *
     * @return void
     */
    private function discover()
    {
        if ($this->discovered) {
            return;
        }

        $this->discovered = true;

        // The directory set is part of the key: a second library reading other
        // directories must not be served the first one's units.
        $cacheKey = self::CACHE_PREFIX . SFCORE_VERSION . '_'
            . substr(md5(implode('|', $this->unitDirs)), 0, 8);
        $raw      = get_transient($cacheKey);

        if (! is_array($raw)) {
            $raw = $this->readFromDisk();
            set_transient($cacheKey, $raw, self::CACHE_TTL);
        }

        /**
         * Filters the raw generation unit sidecars before they are parsed.
         *
         * @param array $raw Sidecar arrays keyed by unit id.
         */
        $raw = apply_filters('swishfolio_ai_generation_units', $raw);

        foreach ($raw as $id => $sidecar) {
            if (! is_array($sidecar)) {
                continue;
            }

            try {
                $this->units[(string) $id] = PatternUnit::fromArray((string) $id, $sidecar);
            } catch (\InvalidArgumentException $e) {
                // A malformed sidecar removes one unit from the library rather
                // than breaking generation entirely.
                unset($this->units[(string) $id]);
            }
        }

        ksort($this->units);
    }

    /**
     * @return array Raw sidecar arrays keyed by unit id.
     */
    private function readFromDisk()
    {
        $raw = [];

        foreach ($this->unitDirs as $dir) {
            foreach ((array) glob(rtrim($dir, '/') . '/*.json') as $file) {
                $sidecar = json_decode((string) file_get_contents($file), true);

                if (! is_array($sidecar)) {
                    continue;
                }

                $id = isset($sidecar['id']) ? (string) $sidecar['id'] : basename($file, '.json');

                $raw[$id] = $sidecar;
            }
        }

        return $raw;
    }
}
