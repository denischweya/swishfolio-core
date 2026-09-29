<?php

namespace SwishfolioCore\Tests\Unit\Generation;

use SwishfolioCore\AI\Generation\Stages\PlanEnvelopeStage;
use SwishfolioCore\AI\Generation\Stages\PlanSanitizeStage;

class PlanSanitizeStageTest extends GenerationTestCase
{
    private function apply(array $plan)
    {
        $context = $this->context();
        $plan    = (new PlanEnvelopeStage())->process($plan, $context);

        return (new PlanSanitizeStage())->process($plan, $context);
    }

    public function test_slot_copy_is_stripped_of_markup()
    {
        $result = $this->apply($this->plan([
            'pages' => [$this->page(['sections' => [$this->patternSection(['headline' => 'Bold <b>claim</b>'])]])],
        ]));

        $this->assertSame('Bold claim', $result['pages'][0]['sections'][0]['content']['headline']);
    }

    public function test_page_titles_are_sanitised_and_slugs_are_slugged()
    {
        $result = $this->apply($this->plan([
            'pages' => [$this->page(['title' => 'About <em>Us</em>', 'slug' => 'About Us!'])],
        ]));

        $this->assertSame('About Us', $result['pages'][0]['title']);
        $this->assertSame('about-us', $result['pages'][0]['slug']);
    }

    public function test_a_url_slot_is_escaped()
    {
        $result = $this->apply($this->plan([
            'pages' => [$this->page(['sections' => [$this->patternSection(['ctaUrl' => 'https://example.com/x'])]])],
        ]));

        $this->assertSame('https://example.com/x', $result['pages'][0]['sections'][0]['content']['ctaUrl']);
    }

    public function test_an_internal_page_reference_survives_url_escaping()
    {
        $result = $this->apply($this->plan([
            'pages' => [$this->page(['sections' => [$this->patternSection(['ctaUrl' => '{{page:home}}'])]])],
        ]));

        $this->assertSame('{{page:home}}', $result['pages'][0]['sections'][0]['content']['ctaUrl']);
    }

    public function test_site_title_and_tagline_are_sanitised()
    {
        $result = $this->apply($this->plan([
            'site' => [
                'title'      => 'Acme <script>x</script>',
                'tagline'    => 'We <b>build</b>',
                'frontPage'  => 'home',
                'navigation' => [],
            ],
        ]));

        $this->assertSame('Acme x', $result['site']['title']);
        $this->assertSame('We build', $result['site']['tagline']);
    }

    public function test_a_markup_only_text_slot_is_escaped_so_kses_has_nothing_left_to_rewrite()
    {
        $result = $this->apply($this->plan([
            'pages' => [$this->page(['sections' => [$this->patternSection(['headline' => 'Design & build'])]])],
        ]));

        $this->assertSame('Design &amp; build', $result['pages'][0]['sections'][0]['content']['headline']);
    }

    public function test_a_slot_stored_in_a_block_comment_is_left_raw()
    {
        $result = $this->apply($this->plan([
            'pages' => [$this->page(['sections' => [$this->patternSection(['label' => "Sasha's studio"])]])],
        ]));

        $this->assertSame("Sasha's studio", $result['pages'][0]['sections'][0]['content']['label']);
    }
}
