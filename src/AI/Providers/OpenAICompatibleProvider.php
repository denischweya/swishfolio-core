<?php
/**
 * Any OpenAI-compatible endpoint: OpenRouter, Groq, Together, Ollama.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Providers;

class OpenAICompatibleProvider extends OpenAIProvider
{
    public function getSlug()
    {
        return 'openai-compatible';
    }

    public function getLabel()
    {
        return __('OpenAI-compatible API', 'swishfolio-core');
    }

    public function getRequiredFields()
    {
        return ['api_key', 'base_url'];
    }

    public function getModels()
    {
        // The endpoint decides; the user types a model name in settings.
        return [];
    }

    /**
     * Unknown, so false.
     *
     * This used to inherit true from OpenAIProvider, which was a guess about an
     * endpoint that could be anything — Together and vLLM support tools, Ollama
     * and older gateways do not. Guessing wrong meant the agent failed somewhere
     * inside a turn with a provider error, rather than refusing up front with a
     * sentence the owner could act on.
     *
     * The six named providers declare their own support, so this only affects
     * the custom-endpoint escape hatch, where only the owner knows the answer.
     * A site pointing at a tool-capable endpoint flips the filter.
     *
     * @return bool
     */
    public function supportsTools()
    {
        /**
         * Filters whether the custom OpenAI-compatible endpoint supports tool calling.
         *
         * @param bool $supported Default false.
         */
        return (bool) apply_filters('swishfolio_ai_compatible_supports_tools', false);
    }

    protected function getBaseUrl()
    {
        return rtrim($this->credential('base_url', parent::getBaseUrl()), '/');
    }
}
