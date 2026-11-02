<?php
/**
 * Raised when tool input does not match its schema.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Tools;

/**
 * Deliberately not a ToolException: the validator does not know which tool it is
 * validating for, and inventing a name to satisfy a constructor would put a lie
 * in the journal. ToolRunner attaches the name when it wraps this.
 */
class SchemaViolation extends \RuntimeException
{
    /** @var string */
    private $field;

    /**
     * @param string $field   Dotted path of the offending value, '' for the root.
     * @param string $message What is wrong with it.
     */
    public function __construct($field, $message)
    {
        parent::__construct($message);

        $this->field = (string) $field;
    }

    public function getField()
    {
        return $this->field;
    }
}
