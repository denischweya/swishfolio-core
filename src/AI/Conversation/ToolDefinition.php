<?php
/**
 * One tool, as a model sees it.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Conversation;

use SwishfolioCore\Tools\ToolInterface;

/**
 * The provider-facing projection of a ToolInterface: the three things both
 * providers need, and nothing else. The side-effect class is deliberately not
 * here — a model that knew which calls were gated would plan around the gate
 * rather than propose the work and let the user decide.
 */
class ToolDefinition
{
    /** @var string */
    private $name;

    /** @var string */
    private $description;

    /** @var array */
    private $inputSchema;

    /**
     * @param string $name        Tool name in its canonical slashed form.
     * @param string $description What it does and when to use it.
     * @param array  $inputSchema JSON Schema for the arguments.
     */
    public function __construct($name, $description, array $inputSchema)
    {
        $this->name        = (string) $name;
        $this->description = (string) $description;
        $this->inputSchema = $inputSchema;
    }

    /**
     * @param ToolInterface $tool Tool to describe.
     *
     * @return self
     */
    public static function fromTool(ToolInterface $tool)
    {
        return new self($tool->getName(), $tool->getDescription(), $tool->getInputSchema());
    }

    public function getName()
    {
        return $this->name;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function getInputSchema()
    {
        return $this->inputSchema;
    }
}
