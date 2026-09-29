<?php
/**
 * One AI generation request.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI;

class AIRequest
{
    /** @var string */
    private $systemPrompt;

    /** @var string */
    private $userPrompt;

    /** @var array */
    private $schema;

    /** @var string */
    private $model;

    /** @var int */
    private $maxTokens;

    /**
     * @param string $systemPrompt Stable prefix: instructions, manifests, tokens.
     * @param string $userPrompt   Volatile suffix: selection context and instruction.
     * @param array  $schema       JSON Schema the provider must enforce.
     * @param string $model        Model identifier.
     * @param int    $maxTokens    Output cap.
     */
    public function __construct($systemPrompt, $userPrompt, array $schema, $model, $maxTokens = 4096)
    {
        $this->systemPrompt = (string) $systemPrompt;
        $this->userPrompt   = (string) $userPrompt;
        $this->schema       = $schema;
        $this->model        = (string) $model;
        $this->maxTokens    = (int) $maxTokens;
    }

    public function getSystemPrompt()
    {
        return $this->systemPrompt;
    }

    public function getUserPrompt()
    {
        return $this->userPrompt;
    }

    public function getSchema()
    {
        return $this->schema;
    }

    public function getModel()
    {
        return $this->model;
    }

    public function getMaxTokens()
    {
        return $this->maxTokens;
    }
}
