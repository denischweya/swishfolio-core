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

    /** @var array{code:string,message:string,detail:string} */
    private $guidance;

    /**
     * @param string $message    The provider's own message.
     * @param int    $statusCode HTTP status, or 0 for transport failures.
     */
    public function __construct($message, $statusCode = 0)
    {
        parent::__construct($message);

        $this->statusCode = (int) $statusCode;
        $this->guidance   = ProviderError::guidance($this->statusCode, $message);
    }

    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * @return string A code such as no_credit or bad_key.
     */
    public function getGuidanceCode()
    {
        return $this->guidance['code'];
    }

    /**
     * What to tell the site owner, and what they can do about it.
     *
     * @return string
     */
    public function getGuidance()
    {
        return $this->guidance['message'];
    }
}
