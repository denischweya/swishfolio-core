<?php

namespace SwishfolioCore\Tests\Unit\AI;

/**
 * Stands in for WP_REST_Request: only the accessors the controllers use.
 */
class FakeRestRequest
{
    /** @var array */
    private $params;

    public function __construct(array $params = [])
    {
        $this->params = $params;
    }

    public function get_param($key)
    {
        return isset($this->params[$key]) ? $this->params[$key] : null;
    }

    public function set_param($key, $value)
    {
        $this->params[$key] = $value;
    }
}
