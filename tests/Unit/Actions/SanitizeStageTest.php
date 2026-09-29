<?php

namespace SwishfolioCore\Tests\Unit\Actions;

use SwishfolioCore\Actions\Stages\SanitizeStage;

class SanitizeStageTest extends StageTestCase
{
    private function apply(array $response)
    {
        return (new SanitizeStage())->process($response, $this->context());
    }

    public function test_script_tags_are_stripped_from_richtext()
    {
        $result = $this->apply($this->response([[
            'type'     => 'update_block_content',
            'clientId' => 'para-1',
            'content'  => 'Hello <script>alert(1)</script><strong>world</strong>',
        ]]));

        $content = $result['actions'][0]['content'];

        $this->assertStringNotContainsString('<script>', $content);
        $this->assertStringContainsString('<strong>world</strong>', $content);
    }

    public function test_markup_is_stripped_from_plain_text_fields()
    {
        $result = $this->apply($this->response([
            $this->updateAction('hero-1', ['heading' => '<b>Bold</b> heading']),
        ]));

        $this->assertSame('Bold heading', $result['actions'][0]['changes']['heading']);
    }

    public function test_urls_pass_through_esc_url_raw()
    {
        $result = $this->apply($this->response([
            $this->updateAction('hero-1', ['ctaUrl' => '  https://example.com/x  ']),
        ]));

        $this->assertSame('https://example.com/x', $result['actions'][0]['changes']['ctaUrl']);
    }

    public function test_non_string_values_are_left_untouched()
    {
        $result = $this->apply($this->response([
            $this->updateAction('hero-1', ['minHeight' => 640.0, 'headingFullWidth' => true]),
        ]));

        $this->assertSame(640.0, $result['actions'][0]['changes']['minHeight']);
        $this->assertTrue($result['actions'][0]['changes']['headingFullWidth']);
    }
}
