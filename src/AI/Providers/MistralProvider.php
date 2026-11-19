<?php
/**
 * Mistral AI.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Providers;

/**
 * Included for a reason that is not model quality: Mistral is EU-headquartered
 * and EU-hosted, so it is the listed provider with a straight answer when a
 * European site owner asks where their content goes. For a WordPress plugin sold
 * into that market that is a feature, and no amount of model benchmarking
 * substitutes for it.
 */
class MistralProvider extends AbstractOpenAICompatiblePreset
{
    public function getSlug()
    {
        return 'mistral';
    }

    public function getLabel()
    {
        return __('Mistral AI', 'swishfolio-core');
    }

    public function getModels()
    {
        return [
            'mistral-large-latest'  => 'Mistral Large',
            'mistral-medium-latest' => 'Mistral Medium',
            'mistral-small-latest'  => 'Mistral Small',
        ];
    }

    protected function presetBaseUrl()
    {
        return 'https://api.mistral.ai/v1';
    }
}
