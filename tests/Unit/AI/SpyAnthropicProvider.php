<?php

namespace SwishfolioCore\Tests\Unit\AI;

use SwishfolioCore\AI\Providers\AnthropicProvider;

class SpyAnthropicProvider extends AnthropicProvider
{
    public $nextResponse;
    public $lastUrl;
    public $lastBody;
    public $lastHeaders = [];

    protected function post($url, array $args)
    {
        $this->lastUrl     = $url;
        $this->lastBody    = json_decode($args['body'], true);
        $this->lastHeaders = $args['headers'];

        return $this->nextResponse;
    }

    protected function backoff($attempt)
    {
    }
}
