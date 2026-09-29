<?php

namespace SwishfolioCore\Tests\Unit\AI;

use SwishfolioCore\AI\Providers\OpenAICompatibleProvider;

class SpyOpenAICompatibleProvider extends OpenAICompatibleProvider
{
    public $nextResponse;
    public $lastUrl;
    public $lastBody;

    protected function post($url, array $args)
    {
        $this->lastUrl  = $url;
        $this->lastBody = json_decode($args['body'], true);

        return $this->nextResponse;
    }

    protected function backoff($attempt)
    {
    }
}
