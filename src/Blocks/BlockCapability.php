<?php
/**
 * A block's parsed AI capability manifest.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Blocks;

/**
 * The AI-editable surface of one block type.
 *
 * This object is both the description handed to the AI and the allowlist the
 * action validator enforces: an attribute absent from getFields() cannot be
 * written, because nothing downstream can resolve it.
 */
class BlockCapability
{
    const SUPPORTED_VERSION = 1;
    const TIERS             = ['full', 'content'];

    /** @var string */
    private $blockName;

    /** @var string */
    private $tier;

    /** @var string */
    private $purpose;

    /** @var string */
    private $summary;

    /** @var FieldSpec[] Keyed by attribute name. */
    private $fields = [];

    /** @var array */
    private $allowedChildren = [];

    /** @var int|null */
    private $childMin;

    /** @var int|null */
    private $childMax;

    private function __construct($blockName)
    {
        $this->blockName = $blockName;
    }

    /**
     * @param string $blockName Full block name, e.g. swishfolio-core/hero.
     * @param array  $manifest  Decoded ai.json contents.
     *
     * @throws \InvalidArgumentException When the manifest is unusable.
     *
     * @return self
     */
    public static function fromArray($blockName, array $manifest)
    {
        $version = isset($manifest['version']) ? (int) $manifest['version'] : 0;

        if ($version !== self::SUPPORTED_VERSION) {
            throw new \InvalidArgumentException(
                sprintf('%s: unsupported manifest version "%s".', $blockName, $version)
            );
        }

        $capability       = new self($blockName);
        $capability->tier = isset($manifest['tier']) ? $manifest['tier'] : '';

        if (! in_array($capability->tier, self::TIERS, true)) {
            throw new \InvalidArgumentException(
                sprintf('%s: invalid tier "%s".', $blockName, $capability->tier)
            );
        }

        $capability->purpose = isset($manifest['purpose']) ? $manifest['purpose'] : '';
        $capability->summary = isset($manifest['summary']) ? $manifest['summary'] : '';

        $groups = isset($manifest['capabilities']) ? $manifest['capabilities'] : [];

        foreach ($groups as $group => $attributes) {
            foreach ((array) $attributes as $attribute => $spec) {
                $spec['group']                    = $group;
                $capability->fields[$attribute] = FieldSpec::fromArray($attribute, $spec);
            }
        }

        if (isset($manifest['children']) && is_array($manifest['children'])) {
            $children = $manifest['children'];

            $capability->allowedChildren = isset($children['allowed']) ? (array) $children['allowed'] : [];
            $capability->childMin        = isset($children['min']) ? (int) $children['min'] : null;
            $capability->childMax        = isset($children['max']) ? (int) $children['max'] : null;
        }

        return $capability;
    }

    public function getBlockName()
    {
        return $this->blockName;
    }

    public function getTier()
    {
        return $this->tier;
    }

    public function getPurpose()
    {
        return $this->purpose;
    }

    public function getSummary()
    {
        return $this->summary;
    }

    /**
     * @return FieldSpec[] Keyed by attribute name.
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * @param string $attribute Attribute name.
     *
     * @return FieldSpec|null Null when the attribute is not AI-editable.
     */
    public function getField($attribute)
    {
        return isset($this->fields[$attribute]) ? $this->fields[$attribute] : null;
    }

    public function getAllowedChildren()
    {
        return $this->allowedChildren;
    }

    public function getChildMin()
    {
        return $this->childMin;
    }

    public function getChildMax()
    {
        return $this->childMax;
    }
}
