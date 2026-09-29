<?php
/**
 * One AI generation response.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI;

/**
 * Token counts are recorded from the first request even though nothing bills
 * yet, so credit pricing can later be set from measurement rather than guesses.
 */
class AIResponse
{
    /** @var array */
    private $decoded;

    /** @var int */
    private $inputTokens;

    /** @var int */
    private $outputTokens;

    /** @var string */
    private $model;

    /** @var string */
    private $providerSlug;

    /**
     * @param array  $decoded      Decoded JSON payload.
     * @param int    $inputTokens  Prompt tokens billed.
     * @param int    $outputTokens Completion tokens billed.
     * @param string $model        Model that answered.
     * @param string $providerSlug Provider that answered.
     */
    public function __construct(array $decoded, $inputTokens, $outputTokens, $model, $providerSlug)
    {
        $this->decoded      = $decoded;
        $this->inputTokens  = (int) $inputTokens;
        $this->outputTokens = (int) $outputTokens;
        $this->model        = (string) $model;
        $this->providerSlug = (string) $providerSlug;
    }

    public function getDecoded()
    {
        return $this->decoded;
    }

    public function getInputTokens()
    {
        return $this->inputTokens;
    }

    public function getOutputTokens()
    {
        return $this->outputTokens;
    }

    public function getModel()
    {
        return $this->model;
    }

    public function getProviderSlug()
    {
        return $this->providerSlug;
    }
}
