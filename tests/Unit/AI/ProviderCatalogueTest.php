<?php

namespace SwishfolioCore\Tests\Unit\AI;

use PHPUnit\Framework\TestCase;
use SwishfolioCore\AI\Contracts\AIProviderInterface;
use SwishfolioCore\AI\Providers\AbstractOpenAICompatiblePreset;
use SwishfolioCore\AI\Providers\OpenAICompatibleProvider;
use SwishfolioCore\AI\Providers\ProviderCatalogue;

class ProviderCatalogueTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        unset($GLOBALS['sfcore_test_filters']['swishfolio_ai_providers']);
        unset($GLOBALS['sfcore_test_filters']['swishfolio_ai_compatible_supports_tools']);
    }

    public function test_it_offers_the_six_providers_plus_the_custom_endpoint()
    {
        $slugs = ProviderCatalogue::slugs();

        sort($slugs);

        $this->assertSame(
            [
                'anthropic',
                'gemini',
                'groq',
                'mistral',
                'openai',
                'openai-compatible',
                'openrouter',
            ],
            $slugs
        );
    }

    public function test_the_custom_endpoint_is_offered_last()
    {
        // It is the escape hatch, and it asks the owner for a base URL. Listing
        // it above the named providers would put the hardest option first.
        $slugs = ProviderCatalogue::slugs();

        $this->assertSame('openai-compatible', end($slugs));
    }

    public function test_every_provider_declares_what_the_connection_screen_needs()
    {
        foreach (ProviderCatalogue::providers() as $slug => $provider) {
            $this->assertInstanceOf(AIProviderInterface::class, $provider, $slug);
            $this->assertNotSame('', $provider->getLabel(), $slug);
            $this->assertContains('api_key', $provider->getRequiredFields(), $slug . ' must take a key.');
        }
    }

    public function test_a_named_provider_needs_only_a_key_while_the_custom_one_needs_a_url()
    {
        $providers = ProviderCatalogue::providers();

        foreach (['gemini', 'mistral', 'groq', 'openrouter'] as $slug) {
            $this->assertSame(
                ['api_key'],
                $providers[$slug]->getRequiredFields(),
                $slug . ' has a known endpoint, so asking for it would be asking the owner to retype a constant.'
            );
        }

        $this->assertContains('base_url', $providers['openai-compatible']->getRequiredFields());
    }

    public function test_every_named_provider_offers_models_to_choose_from()
    {
        $providers = ProviderCatalogue::providers();

        foreach ($providers as $slug => $provider) {
            if ('openai-compatible' === $slug) {
                continue;
            }

            $this->assertNotEmpty(
                $provider->getModels(),
                $slug . ' would render an empty model dropdown.'
            );
        }
    }

    public function test_the_four_new_providers_speak_the_openai_format()
    {
        // Which is why tool calling works on them the day they are added:
        // converse() is inherited rather than reimplemented four times.
        $providers = ProviderCatalogue::providers();

        foreach (['gemini', 'mistral', 'groq', 'openrouter'] as $slug) {
            $this->assertInstanceOf(AbstractOpenAICompatiblePreset::class, $providers[$slug], $slug);
            $this->assertTrue($providers[$slug]->supportsTools(), $slug . ' must be able to run the agent.');
        }
    }

    public function test_the_custom_endpoint_does_not_claim_tool_support_it_cannot_know_about()
    {
        // It used to inherit true from OpenAIProvider. Pointed at Ollama that
        // meant the agent failed inside a turn instead of refusing up front.
        $this->assertFalse((new OpenAICompatibleProvider())->supportsTools());
    }

    public function test_a_site_can_declare_its_custom_endpoint_tool_capable()
    {
        $GLOBALS['sfcore_test_filters']['swishfolio_ai_compatible_supports_tools'] = static function () {
            return true;
        };

        $supported = (new OpenAICompatibleProvider())->supportsTools();

        unset($GLOBALS['sfcore_test_filters']['swishfolio_ai_compatible_supports_tools']);

        $this->assertTrue($supported);
    }

    public function test_a_filtered_in_provider_is_offered()
    {
        $GLOBALS['sfcore_test_filters']['swishfolio_ai_providers'] = static function ($providers) {
            $providers['acme'] = new FakeProvider();

            return $providers;
        };

        $slugs = ProviderCatalogue::slugs();

        unset($GLOBALS['sfcore_test_filters']['swishfolio_ai_providers']);

        $this->assertContains('acme', $slugs);
    }

    public function test_a_filter_returning_rubbish_cannot_put_a_non_provider_in_the_registry()
    {
        // The registry hands what it returns credentials and asks it to answer
        // a request.
        $GLOBALS['sfcore_test_filters']['swishfolio_ai_providers'] = static function ($providers) {
            $providers['bogus'] = new \stdClass();

            return $providers;
        };

        $providers = ProviderCatalogue::providers();

        unset($GLOBALS['sfcore_test_filters']['swishfolio_ai_providers']);

        $this->assertArrayNotHasKey('bogus', $providers);
        $this->assertArrayHasKey('openai', $providers, 'The real providers survive.');
    }

    public function test_a_filter_that_empties_the_list_falls_back_rather_than_bricking_the_plugin()
    {
        $GLOBALS['sfcore_test_filters']['swishfolio_ai_providers'] = static function () {
            return [];
        };

        $slugs = ProviderCatalogue::slugs();

        unset($GLOBALS['sfcore_test_filters']['swishfolio_ai_providers']);

        $this->assertContains('openai', $slugs);
    }

    public function test_it_is_not_memoised_so_a_late_registering_plugin_is_seen()
    {
        $before = count(ProviderCatalogue::slugs());

        $GLOBALS['sfcore_test_filters']['swishfolio_ai_providers'] = static function ($providers) {
            $providers['late'] = new FakeProvider();

            return $providers;
        };

        $after = count(ProviderCatalogue::slugs());

        unset($GLOBALS['sfcore_test_filters']['swishfolio_ai_providers']);

        $this->assertSame($before + 1, $after);
    }
}
