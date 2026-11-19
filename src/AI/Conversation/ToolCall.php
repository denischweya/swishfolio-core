<?php
/**
 * One tool call a model asked for.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Conversation;

/**
 * The id matters as much as the name: both providers correlate a result back to
 * its call by id, and answering the wrong id produces a conversation the model
 * cannot follow.
 */
class ToolCall
{
    /** @var string */
    private $id;

    /** @var string */
    private $name;

    /** @var array */
    private $input;

    /**
     * @param string $id    Provider-assigned call id.
     * @param string $name  Tool name, already mapped back to its slashed form.
     * @param array  $input Arguments the model supplied.
     */
    public function __construct($id, $name, array $input = [])
    {
        $this->id    = (string) $id;
        $this->name  = (string) $name;
        $this->input = $input;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getInput()
    {
        return $this->input;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return [
            'id'    => $this->id,
            'name'  => $this->name,
            'input' => $this->input,
        ];
    }
}
