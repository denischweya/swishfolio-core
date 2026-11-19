<?php
/**
 * The providers this plugin knows how to talk to.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Providers;

use SwishfolioCore\AI\Contracts\AIProviderInterface;

/**
 * One place that answers "which providers exist", and the reason it exists is a
 * bug it fixes.
 *
 * The slug allowlist used to be a hardcoded constant on Settings, enforced in
 * nine places across Settings, SwishAiMenu, SettingsController and
 * ConnectionsController. That made the documented swishfolio_ai_providers filter
 * half-functional: a third party could register a provider class, but
 * getActive(), the connect route and the admin screen all rejected its slug, so
 * no credential could ever be stored against it. Deriving the allowlist from the
 * registered providers instead means adding one is a single change here, and a
 * filtered-in provider works the whole way through.
 *
 * Deliberately has no dependency on Settings: Settings reads this, and
 * ProviderRegistry needs Settings, so a dependency the other way would be a
 * cycle.
 *
 * Not memoised. Six object constructions are nothing next to an HTTP call, and a
 * static cache would capture the filter's result before late-registering plugins
 * had run — and would leak between tests.
 */
class ProviderCatalogue
{
    /**
     * Every provider, keyed by slug.
     *
     * @return AIProviderInterface[]
     */
    public static function providers()
    {
        $providers = [];

        $built = [
            new AnthropicProvider(),
            new OpenAIProvider(),
            new GeminiProvider(),
            new MistralProvider(),
            new OpenRouterProvider(),
            new GroqProvider(),
            // Last: it is the escape hatch for anything not listed above, and
            // it asks the owner to supply a base URL themselves.
            new OpenAICompatibleProvider(),
        ];

        foreach ($built as $provider) {
            $providers[$provider->getSlug()] = $provider;
        }

        /**
         * Filters the AI providers available to this site.
         *
         * A provider added here now works end to end: its slug is accepted by
         * the settings, the connect route and the admin screen.
         *
         * @param AIProviderInterface[] $providers Keyed by slug.
         */
        $filtered = apply_filters('swishfolio_ai_providers', $providers);

        $valid = [];

        foreach ((array) $filtered as $slug => $provider) {
            // A non-provider reaching the registry would be handed credentials
            // and asked to answer a request.
            if ($provider instanceof AIProviderInterface) {
                $valid[(string) $slug] = $provider;
            }
        }

        return $valid === [] ? $providers : $valid;
    }

    /**
     * @return string[] Slugs, in presentation order.
     */
    public static function slugs()
    {
        return array_keys(self::providers());
    }

    /**
     * @param string $slug Provider slug.
     *
     * @return bool
     */
    public static function has($slug)
    {
        return in_array((string) $slug, self::slugs(), true);
    }
}
