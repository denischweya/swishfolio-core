<?php

namespace SwishfolioCore\Tests\Unit\Onboarding;

require_once SFCORE_PLUGIN_DIR . 'tests/Unit/Tools/wp-tools-stubs.php';

use PHPUnit\Framework\TestCase;
use SwishfolioCore\AI\Onboarding\SiteTemplates;
use SwishfolioCore\AI\Onboarding\TemplateMatcher;
use SwishfolioCore\AI\Prompt\PromptManager;
use SwishfolioCore\AI\ProviderException;
use SwishfolioCore\Tests\Unit\AI\FakeProvider;

class TemplateMatcherTest extends TestCase
{
    private function templates()
    {
        $raw = [
            ['slug' => 'consultancy', 'blogId' => 8, 'name' => 'Soft Precision', 'title' => 'Consulting Firm', 'industry' => 'professional', 'keywords' => ['consultant', 'strategy'], 'default' => true],
            ['slug' => 'restaurant', 'blogId' => 6, 'name' => 'Open Hearth', 'title' => 'Restaurant', 'industry' => 'hospitality', 'keywords' => ['restaurant', 'cafe', 'coffee', 'coffee shop', 'bar']],
            ['slug' => 'chef', 'blogId' => 24, 'name' => 'Candlelight', 'title' => 'Private Chef', 'industry' => 'hospitality', 'keywords' => ['chef', 'catering']],
            ['slug' => 'logistics', 'blogId' => 25, 'name' => 'Manifest', 'title' => 'Logistics Company', 'industry' => 'trades', 'keywords' => ['import', 'freight']],
            ['slug' => 'fitness', 'blogId' => 26, 'name' => 'Momentum', 'title' => 'Fitness Studio', 'industry' => 'wellness', 'keywords' => ['gym', 'physiotherapist', 'physio']],
            ['slug' => 'photographer', 'blogId' => 7, 'name' => 'Still Frame', 'title' => 'Photography', 'industry' => 'creative', 'keywords' => ['photographer', 'wedding photographer']],
        ];
        $out = [];
        foreach ($raw as $entry) {
            $entry = SiteTemplates::normalise($entry);
            $out[$entry['slug']] = $entry;
        }
        return $out;
    }

    private function coffee()
    {
        return ['business' => ['name' => 'Coffezone', 'brief' => 'We are an independent coffee shop based in Lagos Nigeria, we import our coffee from Ethiopia. We want a website that reflects our young and successful clientele.']];
    }

    public function test_keywords_rank_the_coffee_shop_as_a_restaurant_with_phrases_counting_more()
    {
        $scores = TemplateMatcher::scores(TemplateMatcher::briefText($this->coffee()), $this->templates());

        $this->assertSame('restaurant', array_keys($scores)[0]);
        $this->assertSame(5, $scores['restaurant']); // "coffee" + "coffee shop".
        $this->assertSame(2, $scores['logistics']);  // "import".
    }

    public function test_keywords_match_word_starts_only()
    {
        $scores = TemplateMatcher::scores('A barber in Leeds', $this->templates());

        $this->assertSame(0, $scores['restaurant']); // "bar" is not "barber".
    }

    public function test_without_a_provider_the_fallback_answers_with_alternatives()
    {
        $result = (new TemplateMatcher())->match($this->coffee(), $this->templates());

        $this->assertSame('restaurant', $result['slug']);
        $this->assertSame('keywords', $result['source']);
        $this->assertCount(3, $result['alternatives']);
        $this->assertNotContains('restaurant', $result['alternatives']);
        // Next best by keywords first, then the same industry.
        $this->assertSame(['logistics', 'chef'], array_slice($result['alternatives'], 0, 2));
    }

    public function test_a_brief_with_no_signal_gets_the_default_design()
    {
        $result = (new TemplateMatcher())->match(['business' => ['brief' => 'Something lovely']], $this->templates());

        $this->assertSame('consultancy', $result['slug']);
    }

    public function test_the_model_pick_is_used_and_cleaned()
    {
        $provider          = new FakeProvider();
        $provider->payload = [
            'slug'         => 'Fitness',
            'alternatives' => ['fitness', 'nope', 'photographer'],
            'reason'       => 'Your clinic needs bookings — and a warm, energetic look.',
            'designWishes' => 'dark, neon green',
        ];

        $result = (new TemplateMatcher($provider, new PromptManager(SFCORE_PLUGIN_DIR . 'src/AI/Prompt')))
            ->match(['business' => ['brief' => 'Sports physiotherapist in Bath, dark and neon green please']], $this->templates(), 'follow-up-model');

        $this->assertSame('fitness', $result['slug']);
        $this->assertSame('ai', $result['source']);
        $this->assertSame('photographer', $result['alternatives'][0]);
        $this->assertNotContains('nope', $result['alternatives']);
        $this->assertNotContains('fitness', $result['alternatives']);
        $this->assertCount(3, $result['alternatives']);
        $this->assertStringNotContainsString("\u{2014}", $result['reason']);
        $this->assertSame('dark, neon green', $result['designWishes']);
        $this->assertSame('follow-up-model', $provider->lastRequest->getModel());
        $this->assertStringContainsString('- restaurant: "Open Hearth"', $provider->lastRequest->getUserPrompt());
        $this->assertSame(array_keys($this->templates()), $provider->lastRequest->getSchema()['properties']['slug']['enum']);
    }

    public function test_an_unknown_model_pick_falls_back_to_keywords()
    {
        $provider          = new FakeProvider();
        $provider->payload = ['slug' => 'bakery', 'alternatives' => [], 'reason' => '', 'designWishes' => ''];

        $result = (new TemplateMatcher($provider, new PromptManager(SFCORE_PLUGIN_DIR . 'src/AI/Prompt')))->match($this->coffee(), $this->templates());

        $this->assertSame('restaurant', $result['slug']);
        $this->assertSame('keywords', $result['source']);
    }

    public function test_a_provider_failure_falls_back_to_keywords()
    {
        $provider        = new FakeProvider();
        $provider->throw = new ProviderException('down', 0);

        $result = (new TemplateMatcher($provider, new PromptManager(SFCORE_PLUGIN_DIR . 'src/AI/Prompt')))->match($this->coffee(), $this->templates());

        $this->assertSame('restaurant', $result['slug']);
        $this->assertSame('keywords', $result['source']);
    }

    public function test_a_low_fit_from_the_model_recommends_a_custom_design()
    {
        $provider          = new FakeProvider();
        $provider->payload = ['slug' => 'consultancy', 'alternatives' => [], 'reason' => 'Closest structure.', 'designWishes' => '', 'fit' => 42, 'business' => 'a wedding florist'];

        $result = (new TemplateMatcher($provider, new PromptManager(SFCORE_PLUGIN_DIR . 'src/AI/Prompt')))->match(['business' => ['brief' => 'Wedding flowers in Bath']], $this->templates());

        $this->assertSame(42, $result['fit']);
        $this->assertTrue($result['lowFit']);
        $this->assertSame('a wedding florist', $result['business']);
        $this->assertContains('fit', TemplateMatcher::schema(['a'])['required']);
    }

    public function test_the_fit_threshold_and_its_edge_cases()
    {
        $this->assertTrue(TemplateMatcher::lowFit(TemplateMatcher::LOW_FIT - 1));
        $this->assertFalse(TemplateMatcher::lowFit(TemplateMatcher::LOW_FIT));

        // Out of range is clamped; a reply without a fit is not nudged.
        $this->assertSame(100, TemplateMatcher::fromModel(['slug' => 'chef', 'fit' => 400], $this->templates(), [])['fit']);
        $this->assertSame(0, TemplateMatcher::fromModel(['slug' => 'chef', 'fit' => -5], $this->templates(), [])['fit']);
        $this->assertFalse(TemplateMatcher::fromModel(['slug' => 'chef'], $this->templates(), [])['lowFit']);
    }

    public function test_keyword_fallback_scores_fit_from_its_best_match()
    {
        $this->assertFalse((new TemplateMatcher())->match($this->coffee(), $this->templates())['lowFit'], 'A clear keyword match is a good fit.');
        $none = (new TemplateMatcher())->match(['business' => ['brief' => 'Something lovely']], $this->templates());
        $this->assertTrue($none['lowFit'], 'Nothing in the brief points at any design.');
    }

    public function test_normalise_rejects_entries_without_a_blog_and_cleans_slugs()
    {
        $this->assertNull(SiteTemplates::normalise(['slug' => 'x']));
        $this->assertNull(SiteTemplates::normalise(['slug' => '', 'blogId' => 3]));

        $entry = SiteTemplates::normalise(['slug' => 'Law/../', 'blogId' => '23', 'keywords' => [' Solicitor ', '', 'solicitor']]);
        $this->assertSame('law', $entry['slug']);
        $this->assertSame(23, $entry['blogId']);
        $this->assertSame(['solicitor'], $entry['keywords']);
    }
}
