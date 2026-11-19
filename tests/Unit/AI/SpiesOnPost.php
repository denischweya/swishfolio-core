<?php

namespace SwishfolioCore\Tests\Unit\AI;

/**
 * The single HTTP seam, recorded.
 *
 * AbstractAIProvider exposes post() precisely so tests can take it over, and the
 * existing Spy* classes each reimplement this. A trait lets the preset spies be
 * one line apiece instead.
 */
trait SpiesOnPost
{
    /** @var array|null */
    public $nextResponse;

    /** @var string|null */
    public $lastUrl;

    /** @var array|null */
    public $lastBody;

    /** @var array */
    public $lastHeaders = [];

    /**
     * @param array $decoded Body the endpoint should answer with.
     *
     * @return $this
     */
    public function willAnswer(array $decoded)
    {
        $this->nextResponse = [
            'response' => ['code' => 200],
            'body'     => wp_json_encode($decoded),
        ];

        return $this;
    }

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
