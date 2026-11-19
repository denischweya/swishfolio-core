<?php
/**
 * Groq.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Providers;

/**
 * An inference host rather than a model maker: it serves open-weight models very
 * fast and very cheaply, which is the combination that suits the agent loop —
 * eight provider calls in a turn is where latency compounds.
 */
class GroqProvider extends AbstractOpenAICompatiblePreset
{
    public function getSlug()
    {
        return 'groq';
    }

    public function getLabel()
    {
        return __('Groq', 'swishfolio-core');
    }

    public function getModels()
    {
        return [
            'llama-3.3-70b-versatile' => 'Llama 3.3 70B',
            'llama-3.1-8b-instant'    => 'Llama 3.1 8B',
        ];
    }

    protected function presetBaseUrl()
    {
        return 'https://api.groq.com/openai/v1';
    }
}
