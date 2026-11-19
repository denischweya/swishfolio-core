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

    private function withTexts(array $texts, array $attrs = [])
    {
        return $this->plan([
            'pages' => [$this->page(['sections' => [$this->patternSection(['texts' => $texts, 'attrs' => $attrs])]])],
        ]);
    }

    public function test_slot_copy_keeps_only_inline_markup()
    {
        $result = $this->apply($this->withTexts([['id' => 't0', 'html' => 'Bold <em>claim</em><script>x</script>']]));

        $this->assertSame('Bold <em>claim</em>x', $result['pages'][0]['sections'][0]['texts']['t0']);
    }

    public function test_a_kit_link_in_a_button_survives_kses()
    {
        $html   = '<a class="wp-block-button__link" href="{{link:contact}}">Get a quote</a>';
        $result = $this->apply($this->withTexts([['id' => 't4', 'html' => $html]]));

        $this->assertSame($html, $result['pages'][0]['sections'][0]['texts']['t4']);
    }

    public function test_attribute_values_are_plain_text()
    {
        $result = $this->apply($this->withTexts([], [['id' => 'a1', 'value' => 'Call <b>us</b>']]));

        $this->assertSame('Call us', $result['pages'][0]['sections'][0]['attrs']['a1']);
    }

    public function test_page_titles_are_sanitised_and_slugs_are_slugged()
    {
        $result = $this->apply($this->plan([
            'pages' => [$this->page(['title' => 'About <em>Us</em>', 'slug' => 'About Us!'])],
        ]));

        $this->assertSame('About Us', $result['pages'][0]['title']);
        $this->assertSame('about-us', $result['pages'][0]['slug']);
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
}
