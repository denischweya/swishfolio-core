<?php
/**
 * Raised when an AI provider call fails.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI;

class ProviderException extends \RuntimeException
{
    /** @var int */
    private $statusCode;

    /**
     * @param string $message    Human-readable reason.
     * @param int    $statusCode HTTP status, or 0 for transport failures.
     */
    public function __construct($message, $statusCode = 0)
    {
        parent::__construct($message);

        $this->statusCode = (int) $statusCode;
    }

    public function getStatusCode()
    {
        return $this->statusCode;
    }
}
