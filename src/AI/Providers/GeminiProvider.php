<?php
/**
 * Google Gemini.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Providers;

/**
 * Through Gemini's OpenAI-compatible endpoint rather than its native API.
 *
 * The native API is a different request and response shape — contents and parts
 * instead of messages, functionDeclarations instead of tools — so supporting it
 * would mean a third transport alongside OpenAI's and Anthropic's, for a model
 * family that already offers the shape this plugin speaks. The compatibility
 * endpoint covers chat, tool calling and response_format, which is everything
 * used here.
 *
 * Gemini's free tier is the reason it earns a preset over the generic
 * compatible provider: it is the one listed provider a site owner can connect
 * without a card, so it is the one most likely to be someone's first.
 */
class GeminiProvider extends AbstractOpenAICompatiblePreset
{
    public function getSlug()
    {
        return 'gemini';
    }

    public function getLabel()
    {
        return __('Google Gemini', 'swishfolio-core');
    }

    public function getModels()
    {
        return [
            'gemini-2.5-pro'   => 'Gemini 2.5 Pro',
            'gemini-2.5-flash' => 'Gemini 2.5 Flash',
        ];
    }

    protected function presetBaseUrl()
    {
        return 'https://generativelanguage.googleapis.com/v1beta/openai';
    }
}
