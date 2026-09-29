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

    protected function getBaseUrl()
    {
        return rtrim($this->credential('base_url', parent::getBaseUrl()), '/');
    }
}
