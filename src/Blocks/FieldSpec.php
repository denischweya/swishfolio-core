<?php
/**
 * One AI-editable field on a block.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Blocks;

/**
 * Describes a single attribute an AI is permitted to change, together with the
 * constraints its value must satisfy. Parsed from a block's ai.json manifest.
 */
class FieldSpec
{
    const TYPES = ['text', 'url', 'richtext', 'number', 'enum', 'token'];

    /** @var string */
    private $name;

    /** @var string */
    private $type;

    /** @var string */
    private $group;

    /** @var string|null */
    private $role;

    /** @var array */
    private $enum = [];

    /** @var float|null */
    private $min;

    /** @var float|null */
    private $max;

    /** @var int|null */
    private $maxLength;

    /** @var string|null */
    private $tokenSet;

    private function __construct($name)
    {
        $this->name = $name;
    }

    /**
     * @param string $name Attribute name as declared in block.json.
     * @param array  $spec Raw manifest fragment.
     *
     * @throws \InvalidArgumentException When the fragment is not a usable field spec.
     *
     * @return self
     */
    public static function fromArray($name, array $spec)
    {
        $field = new self($name);

        if (isset($spec['type'])) {
            $field->type = $spec['type'];
        } elseif (isset($spec['enum'])) {
            $field->type = 'enum';
        }

        if ($field->type === null || ! in_array($field->type, self::TYPES, true)) {
            throw new \InvalidArgumentException(
                sprintf('Field "%s": missing or unknown type.', $name)
            );
        }

        if ($field->type === 'enum') {
            if (empty($spec['enum']) || ! is_array($spec['enum'])) {
                throw new \InvalidArgumentException(
                    sprintf('Field "%s": enum requires a non-empty list.', $name)
                );
            }
            $field->enum = array_values($spec['enum']);
        }

        if ($field->type === 'token') {
            if (empty($spec['tokenSet'])) {
                throw new \InvalidArgumentException(
                    sprintf('Field "%s": token requires a tokenSet.', $name)
                );
            }
            $field->tokenSet = $spec['tokenSet'];
        }

        $field->group     = isset($spec['group']) ? $spec['group'] : 'style';
        $field->role      = isset($spec['role']) ? $spec['role'] : null;
        $field->min       = isset($spec['min']) ? (float) $spec['min'] : null;
        $field->max       = isset($spec['max']) ? (float) $spec['max'] : null;
        $field->maxLength = isset($spec['maxLength']) ? (int) $spec['maxLength'] : null;

        return $field;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getGroup()
    {
        return $this->group;
    }

    public function getRole()
    {
        return $this->role;
    }

    public function getEnum()
    {
        return $this->enum;
    }

    public function getMin()
    {
        return $this->min;
    }

    public function getMax()
    {
        return $this->max;
    }

    public function getMaxLength()
    {
        return $this->maxLength;
    }

    public function getTokenSet()
    {
        return $this->tokenSet;
    }
}
