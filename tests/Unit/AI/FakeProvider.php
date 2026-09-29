<?php

namespace SwishfolioCore\Tests\Unit\AI;

use SwishfolioCore\AI\AIRequest;
use SwishfolioCore\AI\AIResponse;
use SwishfolioCore\AI\Contracts\AIProviderInterface;
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

    public function getSlug()
    {
        return 'fake';
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
