<?php
/**
 * Builds the configured AI provider.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI;

use SwishfolioCore\AI\Contracts\AIProviderInterface;
use SwishfolioCore\AI\Providers\OpenAICompatibleProvider;
use SwishfolioCore\AI\Providers\OpenAIProvider;

class ProviderRegistry
{
    /** @var Settings */
    private $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * @return AIProviderInterface[] Keyed by slug.
     */
    public function available()
    {
        $providers = [];

        foreach ([new OpenAIProvider(), new OpenAICompatibleProvider()] as $provider) {
            $providers[$provider->getSlug()] = $provider;
        }

        /**
         * Filters the AI providers available to this site.
         *
         * @param AIProviderInterface[] $providers Keyed by slug.
         */
        return apply_filters('swishfolio_ai_providers', $providers);
    }

    /**
     * The configured provider, with credentials applied.
     *
     * @throws ProviderException When no provider is configured.
     *
     * @return AIProviderInterface
     */
    public function configured()
    {
        $settings  = $this->settings->get();
        $providers = $this->available();
        $slug      = $settings['provider'];

        if (! isset($providers[$slug])) {
            throw new ProviderException(
                sprintf('AI provider "%s" is not available.', $slug)
            );
        }

        $key = $this->settings->getKey();

        if ($key === null) {
            throw new ProviderException('No AI provider API key has been configured.');
        }

        $provider = $providers[$slug];
        $provider->setCredentials([
            'api_key'  => $key,
            'base_url' => $settings['base_url'],
        ]);

        return $provider;
    }
}
