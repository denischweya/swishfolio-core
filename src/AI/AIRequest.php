<?php
/**
 * One AI generation request.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI;

class AIRequest
{
    /** @var string */
    private $systemPrompt;

    /** @var string */
    private $userPrompt;

    /** @var array */
    private $schema;

    /** @var string */
    private $model;

    /** @var int */
    private $maxTokens;

    /** @var callable|null */
    private $onProgress;

    /** @var bool Whether the system prompt is a shared prefix worth caching. */
    private $cacheSystem = false;

    /** @var string[] Image URLs shown with the user prompt. */
    private $images = [];

    /** @var string Reasoning effort for this request ('' = the provider's filtered default). */
    private $effort = '';

    /**
     * @param string $systemPrompt Stable prefix: instructions, manifests, tokens.
     * @param string $userPrompt   Volatile suffix: selection context and instruction.
     * @param array  $schema       JSON Schema the provider must enforce.
     * @param string $model        Model identifier.
     * @param int    $maxTokens    Output cap.
     */
    public function __construct($systemPrompt, $userPrompt, array $schema, $model, $maxTokens = 4096)
    {
        $this->systemPrompt = (string) $systemPrompt;
        $this->userPrompt   = (string) $userPrompt;
        $this->schema       = $schema;
        $this->model        = (string) $model;
        $this->maxTokens    = (int) $maxTokens;
    }

    public function getSystemPrompt()
    {
        return $this->systemPrompt;
    }

    public function getUserPrompt()
    {
        return $this->userPrompt;
    }

    public function getSchema()
    {
        return $this->schema;
    }

    public function getModel()
    {
        return $this->model;
    }

    public function getMaxTokens()
    {
        return $this->maxTokens;
    }

    /**
     * A copy that reports streaming progress to $listener, for providers that
     * can stream. Others ignore it and answer in one piece.
     *
     * The listener receives arrays: ['type' => 'thinking'], ['type' => 'text',
     * 'delta' => string] and ['type' => 'restart'] when a retry starts over.
     *
     * @param callable $listener Progress listener.
     *
     * @return self
     */
    public function withProgress(callable $listener)
    {
        $copy             = clone $this;
        $copy->onProgress = $listener;

        return $copy;
    }

    /**
     * @return callable|null
     */
    public function getProgress()
    {
        return $this->onProgress;
    }

    /**
     * A copy whose system prompt is marked as a cacheable shared prefix, for
     * providers with prompt caching (several requests send the same one: the
     * staged site plan's outline, page and copy-fill calls). Others ignore it.
     *
     * @return self
     */
    public function withCachedSystem()
    {
        $copy              = clone $this;
        $copy->cacheSystem = true;

        return $copy;
    }

    /**
     * @return bool
     */
    public function cachesSystem()
    {
        return $this->cacheSystem;
    }

    /**
     * A copy that shows these images (public https URLs, or data:image/...;base64
     * URIs for images with no public URL) before the user
     * prompt, for providers that accept images. Others ignore them, so a
     * caller that depends on the model seeing them checks the provider first.
     *
     * @param string[] $urls Image URLs.
     *
     * @return self
     */
    public function withImages(array $urls)
    {
        $copy         = clone $this;
        $copy->images = array_values(array_map('strval', $urls));

        return $copy;
    }

    /**
     * @return string[]
     */
    public function getImages()
    {
        return $this->images;
    }

    /**
     * A copy with its own reasoning effort (low|medium|high|xhigh|max), for
     * providers that take one. '' keeps the provider's default.
     *
     * @param string $effort Effort.
     *
     * @return self
     */
    public function withEffort($effort)
    {
        $copy         = clone $this;
        $copy->effort = (string) $effort;

        return $copy;
    }

    /**
     * @return string
     */
    public function getEffort()
    {
        return $this->effort;
    }
}
