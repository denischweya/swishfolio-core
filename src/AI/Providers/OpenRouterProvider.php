<?php
/**
 * OpenRouter.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Providers;

/**
 * One key that reaches almost every model, which makes it the most useful single
 * entry in this list and the hedge against the rest of the list being wrong: a
 * site owner who wants a model nobody here anticipated can have it without a
 * plugin update.
 *
 * It is also the one preset whose tool support is a property of the *model*
 * rather than the provider — routing to an older open-weight model gets an
 * endpoint that ignores tools. supportsTools() therefore says true, because the
 * provider does support them, and a model that does not produces a provider
 * error naming itself, which is more useful than this class pretending to know
 * which of several hundred models the owner picked.
 *
 * The attribution headers are what OpenRouter asks integrations to send so
 * usage is identifiable in the owner's own dashboard.
 */
class OpenRouterProvider extends AbstractOpenAICompatiblePreset
{
    public function getSlug()
    {
        return 'openrouter';
    }

    public function getLabel()
    {
        return __('OpenRouter', 'swishfolio-core');
    }

    public function getModels()
    {
        return [
            'anthropic/claude-sonnet-5'   => 'Claude Sonnet 5 (via OpenRouter)',
            'openai/gpt-4o'               => 'GPT-4o (via OpenRouter)',
            'google/gemini-2.5-flash'     => 'Gemini 2.5 Flash (via OpenRouter)',
            'meta-llama/llama-3.3-70b-instruct' => 'Llama 3.3 70B (via OpenRouter)',
        ];
    }

    protected function presetBaseUrl()
    {
        return 'https://openrouter.ai/api/v1';
    }

    protected function authHeaders()
    {
        return array_merge(
            parent::authHeaders(),
            [
                'HTTP-Referer' => home_url('/'),
                'X-Title'      => 'Swishfolio',
            ]
        );
    }
}
