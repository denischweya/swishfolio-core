<?php
/**
 * A provider that replays a committed response instead of calling anyone.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Tests\Evals\Support;

use SwishfolioCore\AI\AIRequest;
use SwishfolioCore\AI\AIResponse;
use SwishfolioCore\AI\Contracts\AIProviderInterface;
use SwishfolioCore\AI\Conversation\ConversationReply;
use SwishfolioCore\AI\Conversation\ConversationRequest;
use SwishfolioCore\AI\Conversation\ToolCall;

/**
 * Recorded mode is the mode that has to work every day: deterministic, free, and
 * safe to run in CI. What it cannot tell you is whether a real model still
 * produces answers of this shape — that is what live mode is for, and keeping the
 * two behind the same interface is what makes the same assertions serve both.
 *
 * The request is kept so a test can prove the prompt was actually assembled —
 * otherwise a fixture would pass even if the assembler threw its work away.
 */
final class RecordedProvider implements AIProviderInterface
{
    /** @var array */
    private $payload;

    /** @var AIRequest|null */
    private $lastRequest;

    /** @var int */
    private $calls = 0;

    /**
     * Recorded conversation turns, consumed one per converse() call.
     *
     * A tool-using turn is a sequence, so one canned answer cannot express
     * "call a tool, read the result, then answer". Each entry is
     * ['text' => string, 'calls' => [['id', 'name', 'input'], ...]].
     *
     * @var array[]
     */
    private $turns = [];

    /** @var ConversationRequest[] */
    private $conversations = [];

    /**
     * @param array $payload Decoded response body, exactly as a provider would return it.
     */
    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    public function getSlug()
    {
        return 'recorded';
    }

    /**
     * @param array[] $turns Recorded conversation turns.
     *
     * @return self
     */
    public function withTurns(array $turns)
    {
        $this->turns = $turns;

        return $this;
    }

    /**
     * @return ConversationRequest[] Every conversation this was asked to answer.
     */
    public function conversations()
    {
        return $this->conversations;
    }

    public function supportsTools()
    {
        return true;
    }

    public function converse(ConversationRequest $request)
    {
        $this->conversations[] = $request;
        ++$this->calls;

        if ($this->turns === []) {
            throw new \SwishfolioCore\AI\ProviderException('RecordedProvider ran out of recorded turns.', 0);
        }

        $turn  = array_shift($this->turns);
        $calls = [];

        foreach (isset($turn['calls']) ? $turn['calls'] : [] as $index => $call) {
            $calls[] = new ToolCall(
                isset($call['id']) ? $call['id'] : 'rec_' . $index,
                isset($call['name']) ? $call['name'] : '',
                isset($call['input']) && is_array($call['input']) ? $call['input'] : []
            );
        }

        return new ConversationReply(
            isset($turn['text']) ? (string) $turn['text'] : '',
            $calls,
            $calls === [] ? 'end_turn' : 'tool_use',
            isset($turn['inputTokens']) ? (int) $turn['inputTokens'] : 0,
            isset($turn['outputTokens']) ? (int) $turn['outputTokens'] : 0,
            'recorded',
            'recorded'
        );
    }

    public function getLabel()
    {
        return 'Recorded fixture';
    }

    public function getRequiredFields()
    {
        return [];
    }

    public function getModels()
    {
        return ['recorded' => 'Recorded fixture'];
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
        $this->calls++;

        return new AIResponse($this->payload, 0, 0, 'recorded', $this->getSlug());
    }

    public function getLastError()
    {
        return null;
    }

    /**
     * @return AIRequest|null
     */
    public function getLastRequest()
    {
        return $this->lastRequest;
    }

    /**
     * @return int
     */
    public function getCallCount()
    {
        return $this->calls;
    }
}
