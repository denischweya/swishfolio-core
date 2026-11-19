<?php
/**
 * One turn of a tool-using exchange, as sent to a provider.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Conversation;

/**
 * The counterpart of AIRequest, and deliberately not an extension of it.
 * AIRequest constrains a single reply to a JSON schema; this one carries a
 * message list and a tool catalogue and expects the model to choose. Merging
 * them would mean one object whose fields are half-ignored either way.
 *
 * The system prompt is kept whole and first, so providers that cache a stable
 * prefix can.
 */
class ConversationRequest
{
    /** @var string */
    private $systemPrompt;

    /** @var Message[] */
    private $messages;

    /** @var ToolDefinition[] */
    private $tools;

    /** @var string */
    private $model;

    /** @var int */
    private $maxTokens;

    /**
     * @param string           $systemPrompt Stable prefix.
     * @param Message[]        $messages     Ordered conversation.
     * @param ToolDefinition[] $tools        Tools the model may call.
     * @param string           $model        Model identifier.
     * @param int              $maxTokens    Output cap.
     */
    public function __construct($systemPrompt, array $messages, array $tools, $model, $maxTokens = 4096)
    {
        $this->systemPrompt = (string) $systemPrompt;
        $this->messages     = $messages;
        $this->tools        = $tools;
        $this->model        = (string) $model;
        $this->maxTokens    = (int) $maxTokens;
    }

    public function getSystemPrompt()
    {
        return $this->systemPrompt;
    }

    /**
     * @return Message[]
     */
    public function getMessages()
    {
        return $this->messages;
    }

    /**
     * @return ToolDefinition[]
     */
    public function getTools()
    {
        return $this->tools;
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
