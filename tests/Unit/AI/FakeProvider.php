<?php

namespace SwishfolioCore\Tests\Unit\AI;

use SwishfolioCore\AI\AIRequest;
use SwishfolioCore\AI\AIResponse;
use SwishfolioCore\AI\Contracts\AIProviderInterface;
use SwishfolioCore\AI\Conversation\ConversationReply;
use SwishfolioCore\AI\Conversation\ConversationRequest;
use SwishfolioCore\AI\Conversation\ToolCall;
use SwishfolioCore\AI\ProviderException;

/**
 * Canned provider: records the request it was given, returns a fixed payload.
 */
class FakeProvider implements AIProviderInterface
{
    /** @var array */
    public $payload = ['schemaVersion' => '1.0', 'summary' => 'Done.', 'actions' => []];

    /** @var AIRequest|null */
    public $lastRequest;

    /** @var \Exception|null */
    public $throw;

    /**
     * Scripted conversation replies, consumed one per converse() call.
     *
     * A tool-using turn is a sequence of exchanges, so a single canned answer
     * cannot express "call a tool, read the result, then answer". Each entry is
     * ['text' => string, 'calls' => [['id', 'name', 'input'], ...]].
     *
     * @var array[]
     */
    public $replies = [];

    /** @var ConversationRequest[] Every conversation this was asked to answer. */
    public $conversations = [];

    /** @var bool */
    public $supportsTools = true;

    public function getSlug()
    {
        return 'fake';
    }

    public function supportsTools()
    {
        return $this->supportsTools;
    }

    public function converse(ConversationRequest $request)
    {
        $this->conversations[] = $request;

        if ($this->throw !== null) {
            throw $this->throw;
        }

        if ($this->replies === []) {
            throw new ProviderException('FakeProvider ran out of scripted replies.', 0);
        }

        $reply = array_shift($this->replies);
        $calls = [];

        foreach (isset($reply['calls']) ? $reply['calls'] : [] as $index => $call) {
            $calls[] = new ToolCall(
                isset($call['id']) ? $call['id'] : 'call_' . $index,
                isset($call['name']) ? $call['name'] : '',
                isset($call['input']) && is_array($call['input']) ? $call['input'] : []
            );
        }

        return new ConversationReply(
            isset($reply['text']) ? (string) $reply['text'] : '',
            $calls,
            $calls === [] ? 'end_turn' : 'tool_use',
            isset($reply['inputTokens']) ? (int) $reply['inputTokens'] : 10,
            isset($reply['outputTokens']) ? (int) $reply['outputTokens'] : 5,
            'fake-model',
            'fake'
        );
    }

    public function getLabel()
    {
        return 'Fake';
    }

    public function getRequiredFields()
    {
        return [];
    }

    public function getModels()
    {
        return ['fake-1' => 'Fake 1'];
    }

    public function setCredentials(array $credentials)
    {
    }

    public function testConnection()
    {
        return true;
    }

    public function generate(AIRequest $request)
    {
        $this->lastRequest = $request;

        if ($this->throw !== null) {
            throw $this->throw;
        }

        return new AIResponse($this->payload, 1000, 50, 'fake-1', $this->getSlug());
    }

    public function getLastError()
    {
        return null;
    }
}
