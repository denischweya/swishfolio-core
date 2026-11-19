<?php
/**
 * What a provider said back.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Conversation;

/**
 * A reply carries text, tool calls, or both — a model routinely explains what it
 * is about to do in the same turn as asking to do it, and dropping that text
 * would lose the only narration the user gets.
 */
class ConversationReply
{
    /** @var string */
    private $text;

    /** @var ToolCall[] */
    private $toolCalls;

    /** @var string */
    private $stopReason;

    /** @var int */
    private $inputTokens;

    /** @var int */
    private $outputTokens;

    /** @var string */
    private $model;

    /** @var string */
    private $providerSlug;

    /**
     * @param string     $text         Assistant prose.
     * @param ToolCall[] $toolCalls    Calls requested.
     * @param string     $stopReason   Provider stop reason.
     * @param int        $inputTokens  Prompt tokens billed.
     * @param int        $outputTokens Completion tokens billed.
     * @param string     $model        Model that answered.
     * @param string     $providerSlug Provider that answered.
     */
    public function __construct(
        $text,
        array $toolCalls,
        $stopReason,
        $inputTokens,
        $outputTokens,
        $model,
        $providerSlug
    ) {
        $this->text         = (string) $text;
        $this->toolCalls    = $toolCalls;
        $this->stopReason   = (string) $stopReason;
        $this->inputTokens  = (int) $inputTokens;
        $this->outputTokens = (int) $outputTokens;
        $this->model        = (string) $model;
        $this->providerSlug = (string) $providerSlug;
    }

    public function getText()
    {
        return $this->text;
    }

    /**
     * @return ToolCall[]
     */
    public function getToolCalls()
    {
        return $this->toolCalls;
    }

    public function hasToolCalls()
    {
        return $this->toolCalls !== [];
    }

    public function getStopReason()
    {
        return $this->stopReason;
    }

    public function getInputTokens()
    {
        return $this->inputTokens;
    }

    public function getOutputTokens()
    {
        return $this->outputTokens;
    }

    public function getModel()
    {
        return $this->model;
    }

    public function getProviderSlug()
    {
        return $this->providerSlug;
    }
}
