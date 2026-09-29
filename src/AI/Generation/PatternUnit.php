<?php
/**
 * One unit of composition in the generation library.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Generation;

/**
 * A generation unit is a whole page body or one section of one, described well
 * enough for an AI to choose it without seeing its markup — 166 KB of pattern
 * markup will not fit in a prompt, but a role, a summary and a slot list will.
 *
 * Markup comes from exactly one of two places: "ref", a pattern registered with
 * WordPress (composed verbatim, because parse -> serialize is byte-identical for
 * every registered pattern), or "file", a slot-bearing HTML file shipped beside
 * this sidecar and deliberately not registered, so it never pollutes the
 * inserter.
 */
class PatternUnit
{
    const SUPPORTED_VERSION = 1;
    const SCOPES            = ['page', 'section'];

    /** @var string */
    private $id;

    /** @var string */
    private $scope;

    /** @var string|null */
    private $ref;

    /** @var string|null */
    private $file;

    /** @var string */
    private $role;

    /** @var string */
    private $summary;

    /** @var string[] */
    private $pageTypes = [];

    /** @var string[] Empty means variation-agnostic. */
    private $styleVariations = [];

    /** @var array */
    private $capacity = [];

    /** @var int */
    private $requiresMedia = 0;

    /** @var bool Ships remote placeholder images the user is expected to replace. */
    private $placeholderMedia = false;

    /** @var bool */
    private $ksesStable = true;

    /** @var SlotSpec[] Keyed by slot name. */
    private $slots = [];

    private function __construct($id)
    {
        $this->id = $id;
    }

    /**
     * @param string $id       Unit id, unique within the library.
     * @param array  $sidecar  Decoded sidecar JSON.
     *
     * @throws \InvalidArgumentException When the sidecar is unusable.
     *
     * @return self
     */
    public static function fromArray($id, array $sidecar)
    {
        $version = isset($sidecar['version']) ? (int) $sidecar['version'] : 0;

        if ($version !== self::SUPPORTED_VERSION) {
            throw new \InvalidArgumentException(
                sprintf('%s: unsupported generation unit version "%s".', $id, $version)
            );
        }

        $unit        = new self($id);
        $unit->scope = isset($sidecar['scope']) ? (string) $sidecar['scope'] : 'section';

        if (! in_array($unit->scope, self::SCOPES, true)) {
            throw new \InvalidArgumentException(
                sprintf('%s: invalid scope "%s".', $id, $unit->scope)
            );
        }

        $unit->ref  = empty($sidecar['ref']) ? null : (string) $sidecar['ref'];
        $unit->file = empty($sidecar['file']) ? null : (string) $sidecar['file'];

        if (($unit->ref === null) === ($unit->file === null)) {
            throw new \InvalidArgumentException(
                sprintf('%s: exactly one of "ref" or "file" is required.', $id)
            );
        }

        $unit->role    = isset($sidecar['role']) ? (string) $sidecar['role'] : '';
        $unit->summary = isset($sidecar['summary']) ? (string) $sidecar['summary'] : '';

        if ($unit->role === '' || $unit->summary === '') {
            throw new \InvalidArgumentException(
                sprintf('%s: a role and a summary are required.', $id)
            );
        }

        $unit->pageTypes        = array_values(array_map('strval', (array) (isset($sidecar['pageTypes']) ? $sidecar['pageTypes'] : [])));
        $unit->styleVariations  = array_values(array_map('strval', (array) (isset($sidecar['styleVariations']) ? $sidecar['styleVariations'] : [])));
        $unit->capacity         = isset($sidecar['capacity']) ? (array) $sidecar['capacity'] : [];
        $unit->requiresMedia    = isset($sidecar['requiresMedia']) ? (int) $sidecar['requiresMedia'] : 0;
        $unit->placeholderMedia = ! empty($sidecar['placeholderMedia']);
        $unit->ksesStable       = ! isset($sidecar['ksesStable']) || ! empty($sidecar['ksesStable']);

        $slots = isset($sidecar['slots']) ? (array) $sidecar['slots'] : [];

        foreach ($slots as $name => $spec) {
            $unit->slots[(string) $name] = SlotSpec::fromArray((string) $name, (array) $spec);
        }

        return $unit;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getScope()
    {
        return $this->scope;
    }

    public function getRef()
    {
        return $this->ref;
    }

    public function getFile()
    {
        return $this->file;
    }

    public function getRole()
    {
        return $this->role;
    }

    public function getSummary()
    {
        return $this->summary;
    }

    public function getPageTypes()
    {
        return $this->pageTypes;
    }

    public function getStyleVariations()
    {
        return $this->styleVariations;
    }

    public function getCapacity()
    {
        return $this->capacity;
    }

    public function getRequiresMedia()
    {
        return $this->requiresMedia;
    }

    public function hasPlaceholderMedia()
    {
        return $this->placeholderMedia;
    }

    public function isKsesStable()
    {
        return $this->ksesStable;
    }

    /**
     * @return SlotSpec[] Keyed by slot name.
     */
    public function getSlots()
    {
        return $this->slots;
    }

    /**
     * @param string $name Slot name.
     *
     * @return SlotSpec|null Null when the unit declares no such slot.
     */
    public function getSlot($name)
    {
        return isset($this->slots[$name]) ? $this->slots[$name] : null;
    }

    /**
     * The promptable description. Never includes markup.
     *
     * @return array
     */
    public function describe()
    {
        $described = [
            'scope'   => $this->scope,
            'role'    => $this->role,
            'summary' => $this->summary,
        ];

        if ($this->pageTypes !== []) {
            $described['pageTypes'] = $this->pageTypes;
        }

        if ($this->capacity !== []) {
            $described['capacity'] = $this->capacity;
        }

        if ($this->placeholderMedia) {
            $described['note'] = 'Ships placeholder images the site owner must replace.';
        }

        $slots = [];

        foreach ($this->slots as $name => $slot) {
            $slots[$name] = $slot->describe();
        }

        $described['slots'] = $slots;

        return $described;
    }
}
