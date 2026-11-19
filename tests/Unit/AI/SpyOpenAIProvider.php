<?php

namespace SwishfolioCore\Tests\Unit\AI;

use SwishfolioCore\AI\Providers\OpenAIProvider;

/**
 * Overrides the single HTTP seam so no test touches the network.
 */
class SpyOpenAIProvider extends OpenAIProvider
{
    public $nextResponse;
    public $queue = [];
    public $lastUrl;
    public $lastBody;
    public $calls = 0;

    protected function post($url, array $args)
    {
        $this->calls++;
        $this->lastUrl  = $url;
        $this->lastBody = json_decode($args['body'], true);

        if ($this->queue !== []) {
            return array_shift($this->queue);
        }

        return $this->nextResponse;
    }

    protected function backoff($attempt)
    {
        // No sleeping in tests.
    }
}
