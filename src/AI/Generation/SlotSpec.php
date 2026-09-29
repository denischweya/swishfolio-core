<?php
/**
 * One fillable slot in a generation pattern.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Generation;

/**
 * A slot is a literal token that occurs in a generation pattern's raw markup and
 * is replaced with one str_replace. The token is the whole mechanism: because the
 * same literal appears everywhere that value is stored, an attribute in a block
 * comment and the text in the rendered markup can never drift apart.
 *
 * Slots whose token also occurs inside a block comment are marked inComment and
 * validated far more strictly, because a comment body is rewritten by KSES on
 * save: a bare "&" or an angle bracket there corrupts the block.
 */
class SlotSpec
{
    const TYPES = ['text', 'richtext', 'url'];

    /** @var string */
    private $name;

    /** @var string */
    private $token;

    /** @var string */
    private $type;

    /** @var int|null */
    private $maxLength;

    /** @var bool */
    private $inComment = false;

    /** @var string */
    private $default = '';

    /** @var string|null */
    private $role;

    private function __construct($name)
    {
        $this->name = $name;
    }

    /**
     * @param string $name Slot name as referenced by a plan's "content" map.
     * @param array  $spec Raw sidecar fragment.
     *
     * @throws \InvalidArgumentException When the fragment is unusable.
     *
     * @return self
     */
    public static function fromArray($name, array $spec)
    {
        $slot = new self($name);

        $slot->type = isset($spec['type']) ? (string) $spec['type'] : 'text';

        if (! in_array($slot->type, self::TYPES, true)) {
            throw new \InvalidArgumentException(
                sprintf('Slot "%s": unknown type "%s".', $name, $slot->type)
            );
        }

        $slot->token = isset($spec['token']) ? (string) $spec['token'] : '{{slot:' . $name . '}}';

        if ($slot->token === '') {
            throw new \InvalidArgumentException(sprintf('Slot "%s": empty token.', $name));
        }

        $slot->maxLength = isset($spec['maxLength']) ? (int) $spec['maxLength'] : null;
        $slot->inComment = ! empty($spec['inComment']);
        $slot->default   = isset($spec['default']) ? (string) $spec['default'] : '';
        $slot->role      = isset($spec['role']) ? (string) $spec['role'] : null;

        return $slot;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getToken()
    {
        return $this->token;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getMaxLength()
    {
        return $this->maxLength;
    }

    public function isInComment()
    {
        return $this->inComment;
    }

    public function getDefault()
    {
        return $this->default;
    }

    public function getRole()
    {
        return $this->role;
    }

    /**
     * Shape handed to the AI so it knows what copy each slot wants.
     *
     * @return array
     */
    public function describe()
    {
        $described = ['type' => $this->type];

        if ($this->role !== null) {
            $described['role'] = $this->role;
        }

        if ($this->maxLength !== null) {
            $described['maxLength'] = $this->maxLength;
        }

        if ($this->default !== '') {
            $described['example'] = $this->default;
        }

        return $described;
    }
}
