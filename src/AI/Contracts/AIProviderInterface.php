<?php
/**
 * Contract every AI provider implements.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Contracts;

use SwishfolioCore\AI\AIRequest;
use SwishfolioCore\AI\AIResponse;
use SwishfolioCore\AI\Conversation\ConversationReply;
use SwishfolioCore\AI\Conversation\ConversationRequest;

/**
 * Mirrors the existing EmailProviderInterface so the two subsystems read alike.
 */
interface AIProviderInterface
{
    /**
     * @return string Stable slug used in settings, e.g. "openai".
     */
    public function getSlug();

    /**
     * @return string Human-readable label.
     */
    public function getLabel();

    /**
     * @return array Credential field names this provider requires.
     */
    public function getRequiredFields();

    /**
     * @return array Model identifier => label.
     */
    public function getModels();

    /**
     * @param array $credentials Provider credentials.
     *
     * @return void
     */
    public function setCredentials(array $credentials);

    /**
     * @return bool Whether the stored credentials work.
     */
    public function testConnection();

    /**
     * @param AIRequest $request Request to fulfil.
     *
     * @throws \SwishfolioCore\AI\ProviderException On transport or provider failure.
     *
     * @return AIResponse
     */
    public function generate(AIRequest $request);

    /**
     * Whether this provider can run a tool-using conversation.
     *
     * Separate from generate(), which constrains one reply to a schema. A
     * provider added through the swishfolio_ai_providers filter may support one
     * and not the other, so the agent loop asks rather than assumes.
     *
     * @return bool
     */
    public function supportsTools();

    /**
     * One turn of a tool-using exchange.
     *
     * @param ConversationRequest $request Conversation so far, plus the tools.
     *
     * @throws \SwishfolioCore\AI\ProviderException On transport or provider failure.
     *
     * @return ConversationReply
     */
    public function converse(ConversationRequest $request);

    /**
     * @return string|null Last error message, if any.
     */
    public function getLastError();
}
