<?php

namespace SwishfolioCore\Tests\Unit\Onboarding;

require_once SFCORE_PLUGIN_DIR . 'tests/Unit/Tools/wp-tools-stubs.php';

use PHPUnit\Framework\TestCase;
use SwishfolioCore\AI\Onboarding\ProfileExtractor;
use SwishfolioCore\AI\Onboarding\ProfileSchema;
use SwishfolioCore\AI\Prompt\PromptManager;
use SwishfolioCore\AI\ProviderException;
use SwishfolioCore\AI\Settings;
use SwishfolioCore\Tests\Unit\AI\FakeProvider;

class ProfileExtractorTest extends TestCase
{
    /** @var FakeProvider */
    private $provider;

    protected function setUp(): void
    {
        parent::setUp();

        sfcore_test_tools_reset();

        $this->provider = new FakeProvider();
    }

    private function extractor()
    {
        return new ProfileExtractor(
            $this->provider,
            new PromptManager(SFCORE_PLUGIN_DIR . 'src/AI/Prompt')
        );
    }

    private function cv()
    {
        return 'Priya Raman. Independent operations consultant, nine years. Previously head of operations at a '
            . 'logistics firm. Works with companies of fifty to three hundred people on process and hiring. '
            . 'Based in Manchester. Delivered forty engagements across retail and healthcare.';
    }

    public function test_a_pasted_cv_becomes_a_structured_profile()
    {
        $this->provider->payload = [
            'name'            => 'Priya Raman',
            'headline'        => 'Operations consulting',
            'summary'         => 'I help companies of fifty to three hundred people fix their processes.',
            'designStyle'     => 'minimalist',
            'copywritingTone' => 'professional',
            'missing'         => ['A photograph', 'Client testimonials'],
        ];

        $result = $this->extractor()->extract($this->cv(), 'fake-model');

        $this->assertSame('Priya Raman', $result['profile']['name']);
        $this->assertSame(['A photograph', 'Client testimonials'], $result['profile']['missing']);
    }

    public function test_the_brand_fields_it_implies_come_back_separately_and_unsaved()
    {
        // A CV is full of things nobody wants on their website, so the owner
        // corrects the extraction before any of it is stored.
        $this->provider->payload = [
            'name'            => 'Priya Raman',
            'headline'        => 'Operations consulting',
            'summary'         => 'Nine years independent.',
            'designStyle'     => 'minimalist',
            'copywritingTone' => 'professional',
        ];

        $result = $this->extractor()->extract($this->cv(), 'fake-model');

        $this->assertSame(
            [
                'siteName'        => 'Priya Raman',
                'siteDescription' => 'Operations consulting',
                'designStyle'     => 'minimalist',
                'copywritingTone' => 'professional',
            ],
            $result['brand']
        );
    }

    public function test_the_suggested_style_and_tone_are_values_the_brand_screen_accepts()
    {
        // A schema that let the model invent "elegant-modern" would produce an
        // answer saveBrand silently discarded, and the owner would see their
        // choice not stick with no explanation.
        $schema = ProfileSchema::build();

        $this->assertSame(Settings::DESIGN_STYLES, $schema['properties']['designStyle']['enum']);
        $this->assertSame(Settings::TONES, $schema['properties']['copywritingTone']['enum']);
    }

    public function test_the_schema_is_closed_so_invented_fields_are_rejected()
    {
        $schema = ProfileSchema::build();

        $this->assertFalse($schema['additionalProperties']);
        $this->assertSame(['name', 'headline', 'summary'], $schema['required']);
    }

    public function test_too_little_text_is_refused_before_a_provider_is_called()
    {
        try {
            $this->extractor()->extract('Priya Raman, consultant.', 'fake-model');
            $this->fail('Expected InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Paste a bit more', $e->getMessage());
        }

        $this->assertNull($this->provider->lastRequest, 'No provider call for a two-word paste.');
    }

    public function test_an_enormous_paste_is_capped_before_it_costs_the_owner_money()
    {
        $this->provider->payload = ['name' => 'X', 'headline' => 'Y', 'summary' => 'Z'];

        $paste = str_repeat('word ', 20000);

        $this->assertSame(100000, strlen($paste), 'Five times the cap.');

        $this->extractor()->extract($paste, 'fake-model');

        $prompt = strlen($this->provider->lastRequest->getUserPrompt());

        // The prompt is the capped text plus the task template, so it lands a
        // little over the cap and nowhere near the paste.
        $this->assertGreaterThan(ProfileExtractor::MAX_LENGTH, $prompt);
        $this->assertLessThan(strlen($paste) / 4, $prompt);
    }

    public function test_the_pasted_text_actually_reaches_the_prompt()
    {
        $this->provider->payload = ['name' => 'X', 'headline' => 'Y', 'summary' => 'Z'];

        $this->extractor()->extract($this->cv(), 'fake-model');

        $this->assertStringContainsString(
            'Priya Raman',
            $this->provider->lastRequest->getUserPrompt(),
            'A prompt that threw the CV away would still return a plausible-looking profile.'
        );
    }

    public function test_the_schema_is_handed_to_the_provider_to_enforce()
    {
        $this->provider->payload = ['name' => 'X', 'headline' => 'Y', 'summary' => 'Z'];

        $this->extractor()->extract($this->cv(), 'fake-model');

        $this->assertSame(
            ProfileSchema::build(),
            $this->provider->lastRequest->getSchema()
        );
    }

    public function test_a_provider_failure_propagates_rather_than_returning_an_empty_profile()
    {
        $this->provider->throw = new ProviderException('upstream exploded', 500);

        $this->expectException(ProviderException::class);

        $this->extractor()->extract($this->cv(), 'fake-model');
    }
}
