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
