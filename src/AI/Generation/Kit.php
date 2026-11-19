<?php
/**
 * One template's generation kit.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Generation;

use SwishfolioCore\AI\Onboarding\CopySlots;

/**
 * A kit is everything generation may compose with for one template design:
 * its sections, its header and footer, its pages (as ordered section lists)
 * and a reference to its design (global styles, CSS, fonts, images).
 *
 * Sections only look right under their own template's CSS, so a plan uses
 * exactly one kit and never mixes units from two. Kits are written by
 * seed/extract-patterns.php into src/AI/Generation/Kits/<slug>/; never edit
 * them by hand.
 */
class Kit
{
    const SUPPORTED_VERSION = 2;

    /** @var string */
    private $dir;

    /** @var array */
    private $data;

    /** @var PatternUnit[] */
    private $units = [];

    /** @var CopySlots[] */
    private $slots = [];

    /** @var string[] */
    private $markup = [];

    /**
     * @param string $dir  Kit directory.
     * @param array  $data Decoded kit.json.
     *
     * @throws \InvalidArgumentException When the kit is unusable.
     */
    public function __construct($dir, array $data)
    {
        if (! isset($data['version']) || (int) $data['version'] !== self::SUPPORTED_VERSION || empty($data['slug'])) {
            throw new \InvalidArgumentException(sprintf('%s: not a version %d kit.', $dir, self::SUPPORTED_VERSION));
        }

        $this->dir  = rtrim((string) $dir, '/');
        $this->data = $data;

        foreach (isset($data['units']) ? (array) $data['units'] : [] as $id => $unit) {
            try {
                $this->units[(string) $id] = PatternUnit::fromArray((string) $id, (array) $unit);
            } catch (\InvalidArgumentException $e) {
                // One malformed entry removes one unit, not the kit.
                continue;
            }
        }
    }

    /**
     * @param string $dir Kit directory holding kit.json.
     *
     * @return self|null
     */
    public static function fromDir($dir)
    {
        $data = json_decode((string) @file_get_contents(rtrim($dir, '/') . '/kit.json'), true);

        if (! is_array($data)) {
            return null;
        }

        try {
            return new self($dir, $data);
        } catch (\InvalidArgumentException $e) {
            return null;
        }
    }

    public function getSlug()
    {
        return (string) $this->data['slug'];
    }

    public function getName()
    {
        return $this->text('name');
    }

    public function getTitle()
    {
        return $this->text('title');
    }

    public function getIndustry()
    {
        return $this->text('industry');
    }

    public function getDescription()
    {
        return $this->text('description');
    }

    public function getLook()
    {
        return $this->text('look');
    }

    public function getDir()
    {
        return $this->dir;
    }

    /**
     * @return array variation, classPrefix, fingerprint, palette, fonts, pageTemplate, header, footer, assetsPath, file.
     */
    public function getDesign()
    {
        return isset($this->data['design']) ? (array) $this->data['design'] : [];
    }

    /**
     * The template's design as its demo site's global styles hold it, with
     * {{kit-fonts}} standing for the kit's fonts directory. For the design
     * applier; generation itself never writes styles.
     *
     * @return array {variation, globalStyles, fonts}
     */
    public function getDesignFile()
    {
        $design = $this->getDesign();
        $file   = $this->dir . '/' . (isset($design['file']) ? $design['file'] : 'design.json');
        $data   = json_decode((string) @file_get_contents($file), true);

        return is_array($data) ? $data : [];
    }

    /**
     * @return string Theme template the template's pages use, or ''.
     */
    public function getPageTemplate()
    {
        $design = $this->getDesign();

        return isset($design['pageTemplate']) ? (string) $design['pageTemplate'] : '';
    }

    /**
     * @return array[] The template's pages: key, title, slug, navLabel, inNav, type, template, sections.
     */
    public function getPages()
    {
        return isset($this->data['pages']) ? array_values((array) $this->data['pages']) : [];
    }

    /**
     * @return string[] Page keys of the template, which its links point at.
     */
    public function getPageKeys()
    {
        return array_map(static function ($page) {
            return (string) $page['key'];
        }, $this->getPages());
    }

    public function getFrontPage()
    {
        return $this->text('frontPage');
    }

    /**
     * @return string[] Page keys in the template's menu, in order.
     */
    public function getNavigation()
    {
        return isset($this->data['navigation']) ? array_values(array_map('strval', (array) $this->data['navigation'])) : [];
    }

    /**
     * @return array name => {file, alt, title, width, height}
     */
    public function getAssets()
    {
        return isset($this->data['assets']) ? (array) $this->data['assets'] : [];
    }

    /**
     * The demo business this kit was cut from: kit.json "demo" (business,
     * tagline) plus demo.json when present (phones, emails, domains,
     * addresses, places, people; see seed/extract-demo-strings.php). For the
     * leftover scan only; never shown to a planner.
     *
     * @return array
     */
    public function getDemo()
    {
        $demo  = isset($this->data['demo']) ? (array) $this->data['demo'] : [];
        $extra = json_decode((string) @file_get_contents($this->dir . '/demo.json'), true); // phpcs:ignore WordPress.PHP.NoSilencedErrors

        $demo = is_array($extra) ? array_merge($extra, $demo) : $demo;

        // Words drawn in the kit's graphics (badge rings, map labels).
        $drawn = [];
        foreach ($this->units as $unit) {
            $drawn = array_merge($drawn, $unit->getFixedText());
        }
        $demo['drawn'] = array_values(array_unique($drawn));

        return $demo;
    }

    /**
     * @return string Hash of the seed sources this kit was extracted from.
     */
    public function getSourceHash()
    {
        return isset($this->data['source']['hash']) ? (string) $this->data['source']['hash'] : '';
    }

    /**
     * @param string $id Unit id.
     *
     * @return PatternUnit|null
     */
    public function get($id)
    {
        return isset($this->units[$id]) ? $this->units[$id] : null;
    }

    /**
     * @param string $id Unit id.
     *
     * @return PatternUnit|null A section unit, the only kind a plan may name.
     */
    public function section($id)
    {
        $unit = $this->get((string) $id);

        return $unit !== null && $unit->getScope() === 'section' && $this->usable($unit) ? $unit : null;
    }

    /**
     * A section whose demo form cannot become the plugin's working form (it is
     * tangled with a script, as in a quote calculator) is never offered: on a
     * real site it would be a form that sends nothing.
     *
     * @param PatternUnit $unit Unit.
     *
     * @return bool
     */
    public function usable(PatternUnit $unit)
    {
        if (! in_array('form', $unit->getNeeds(), true)) {
            return true;
        }

        if (in_array('script', $unit->getNeeds(), true)) {
            return false;
        }

        try {
            return \SwishfolioCore\AI\Onboarding\KitForms::swappable($this->markup($unit->getId()));
        } catch (\RuntimeException $e) {
            return false;
        }
    }

    /**
     * @param string $scope "section", "header" or "footer".
     *
     * @return PatternUnit[] Keyed by id.
     */
    public function units($scope = 'section')
    {
        return array_filter($this->units, function (PatternUnit $unit) use ($scope) {
            return $unit->getScope() === $scope && ($scope !== 'section' || $this->usable($unit));
        });
    }

    /**
     * @return PatternUnit|null
     */
    public function header()
    {
        return $this->get('header');
    }

    /**
     * @return PatternUnit|null
     */
    public function footer()
    {
        return $this->get('footer');
    }

    /**
     * Raw unit markup, kit placeholders included.
     *
     * @param string $id Unit id.
     *
     * @throws \RuntimeException When the unit or its file is missing.
     *
     * @return string
     */
    public function markup($id)
    {
        if (isset($this->markup[$id])) {
            return $this->markup[$id];
        }

        $unit = $this->get($id);

        if ($unit === null) {
            throw new \RuntimeException(sprintf('Kit "%s" has no unit "%s".', $this->getSlug(), $id));
        }

        $file = $this->dir . '/' . $unit->getFile();

        if (! is_file($file)) {
            throw new \RuntimeException(sprintf('Kit "%s" unit "%s" has no markup file.', $this->getSlug(), $id));
        }

        return $this->markup[$id] = trim((string) file_get_contents($file));
    }

    /**
     * The unit's addressable copy, computed from its markup.
     *
     * @param string $id Unit id.
     *
     * @return CopySlots
     */
    public function slots($id)
    {
        if (! isset($this->slots[$id])) {
            $this->slots[$id] = new CopySlots($this->markup($id));
        }

        return $this->slots[$id];
    }

    /**
     * Slot ids whose demo copy must be replaced (or their unit removed) before
     * the unit can be written: text and attribute slots holding words or
     * figures. Only a zero-padded ordinal like "01" (a step or section number)
     * counts as decoration; "8" or "64" may be the demo business's statistic.
     *
     * @param string $id Unit id.
     *
     * @return string[]
     */
    public function requiredSlots($id)
    {
        $required = [];
        $mirrors  = $this->mirrorSlots($id);

        foreach ($this->slots($id)->slots() as $slotId => $slot) {
            // Words drawn in a graphic are asked for but not demanded: any the
            // plan leaves are rewritten to the business name at finish (DemoScan).
            if ($slot['type'] === 'image' || isset($mirrors[$slotId]) || ! empty($slot['svg'])) {
                continue;
            }

            $value = trim(html_entity_decode(strip_tags((string) $slot['value']), ENT_QUOTES, 'UTF-8'));

            if (preg_match('/\p{L}/u', $value) || (preg_match('/\d/', $value) && ! preg_match('/^\W*0\d\W*$/u', $value))) {
                $required[] = (string) $slotId;
            }
        }

        return $required;
    }

    /**
     * Decorative copies of a heading (a hero title drawn a second time over
     * the photo, hidden from screen readers): text slots whose demo copy is
     * exactly a heading slot's (a phrase, not a one-word tag that happens to
     * match its card's title). They always repeat that heading's words, so
     * the planner never writes them (BlockComposer copies the heading in).
     *
     * @param string $id Unit id.
     *
     * @return array<string,string> mirror slot id => heading slot id.
     */
    public function mirrorSlots($id)
    {
        return self::mirrorsIn($this->slots($id)->slots());
    }

    /**
     * mirrorSlots() for any CopySlots slot list (a built page too).
     *
     * @param array $slots CopySlots slots.
     *
     * @return array<string,string> mirror slot id => heading slot id.
     */
    public static function mirrorsIn(array $slots)
    {
        $headings = [];
        foreach ($slots as $slotId => $slot) {
            if ($slot['type'] === 'text' && preg_match('/^h[1-6]\b/', (string) $slot['label'])) {
                $headings[trim((string) $slot['value'])] = (string) $slotId;
            }
        }

        $out = [];
        foreach ($slots as $slotId => $slot) {
            $value = trim((string) $slot['value']);
            if ($slot['type'] === 'text' && empty($slot['svg']) && ! preg_match('/^h[1-6]\b/', (string) $slot['label']) && isset($headings[$value]) && mb_strlen(strip_tags($value)) >= 12) {
                $out[(string) $slotId] = $headings[$value];
            }
        }

        return $out;
    }

    /**
     * Writer's notes for a unit's outline. A title is set at a display size
     * chosen for the demo's words: it is asked for at about that length (a
     * longer one is drawn smaller, HeadlineFit). A decorative copy of a
     * heading is filled in from the heading.
     *
     * @param string $id Unit id.
     *
     * @return array<string,string> slot id => note.
     */
    public function slotNotes($id)
    {
        $notes = [];
        foreach ($this->slots($id)->slots() as $slotId => $slot) {
            if ($slot['type'] === 'text' && preg_match('/^h1\b/', (string) $slot['label'])) {
                $notes[(string) $slotId] = sprintf('about %d characters, keep it close to that', HeadlineFit::length((string) $slot['value']));
            }
        }
        foreach ($this->mirrorSlots($id) as $mirror => $heading) {
            $notes[$mirror] = sprintf('a copy of %s, filled in for you: leave it out', $heading);
        }

        return $notes;
    }

    /**
     * @param string $name Kit image name.
     *
     * @return string|null Public URL of the image shipped with the plugin.
     */
    public function assetUrl($name)
    {
        $assets = $this->getAssets();
        $design = $this->getDesign();

        if (! isset($assets[$name]['file']) || empty($design['assetsPath']) || ! defined('SFCORE_PLUGIN_URL')) {
            return null;
        }

        return SFCORE_PLUGIN_URL . trim((string) $design['assetsPath'], '/') . '/' . ltrim((string) $assets[$name]['file'], '/');
    }

    /**
     * What a template matcher needs to choose between kits. No markup.
     *
     * @return array
     */
    public function describeForMatching()
    {
        $roles = [];

        foreach ($this->units('section') as $unit) {
            $roles[$unit->getRole()] = true;
        }

        $design = $this->getDesign();

        return [
            'slug'        => $this->getSlug(),
            'name'        => $this->getName(),
            'title'       => $this->getTitle(),
            'industry'    => $this->getIndustry(),
            'description' => $this->getDescription(),
            'look'        => $this->getLook(),
            'pages'       => $this->getPageKeys(),
            'roles'       => array_keys($roles),
            'sections'    => count($this->units('section')),
            'palette'     => isset($design['palette']) ? (array) $design['palette'] : [],
            'fonts'       => isset($design['fonts']) ? (array) $design['fonts'] : [],
        ];
    }

    /**
     * The promptable catalogue: the template's pages, then every section unit
     * with its CopySlots outline. Byte-stable for a given kit, so a provider
     * can cache it.
     *
     * @return string
     */
    public function catalogue()
    {
        $lines   = [];
        $lines[] = sprintf('Kit "%s": %s (%s). %s', $this->getSlug(), $this->getName(), $this->getTitle(), $this->getDescription());
        $lines[] = '';
        $lines[] = 'The template\'s own pages (key "title": its sections in order). Its buttons and links point at these keys:';

        foreach ($this->getPages() as $page) {
            // Only sections a plan may name (see usable()).
            $offered = array_filter((array) $page['sections'], function ($id) {
                return $this->section((string) $id) !== null;
            });
            $lines[] = sprintf('- %s "%s" (%s): %s', $page['key'], $page['title'], $page['type'], implode(', ', $offered));
        }

        $lines[] = '';
        $lines[] = 'Section units. Each is followed by its outline: [u*] are removable or repeatable units, t* text slots, a* attribute slots, i* images (fixed).';

        foreach ($this->units('section') as $id => $unit) {
            $lines[] = '';
            $lines[] = $unit->describe();
            $lines[] = $this->slots($id)->outline($this->slotNotes($id));
        }

        return implode("\n", $lines);
    }

    /**
     * @param string $key Top-level key.
     *
     * @return string
     */
    private function text($key)
    {
        return isset($this->data[$key]) ? (string) $this->data[$key] : '';
    }
}
