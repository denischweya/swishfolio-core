<?php

namespace SwishfolioCore\Tests\Unit\Actions;

use SwishfolioCore\Actions\Stages\ValueStage;
use SwishfolioCore\Actions\ValidationException;

class ValueStageTest extends StageTestCase
{
    private function apply(array $response)
    {
        return (new ValueStage())->process($response, $this->context());
    }

    private function assertRejected(array $changes, $clientId = 'hero-1')
    {
        try {
            $this->apply($this->response([$this->updateAction($clientId, $changes)]));
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('value', $e->getStage());
        }
    }

    public function test_valid_enum_value_passes()
    {
        $result = $this->apply($this->response([$this->updateAction('hero-1', ['headingSize' => 'xlarge'])]));

        $this->assertSame('xlarge', $result['actions'][0]['changes']['headingSize']);
    }

    public function test_enum_value_outside_the_declared_list_is_rejected()
    {
        $this->assertRejected(['headingSize' => 'gigantic']);
    }

    public function test_number_below_minimum_is_rejected()
    {
        $this->assertRejected(['minHeight' => 10]);
    }

    public function test_number_above_maximum_is_rejected()
    {
        $this->assertRejected(['minHeight' => 5000]);
    }

    public function test_number_within_bounds_is_coerced_to_a_number()
    {
        $result = $this->apply($this->response([$this->updateAction('hero-1', ['minHeight' => '640'])]));

        $this->assertSame(640.0, $result['actions'][0]['changes']['minHeight']);
    }

    public function test_string_longer_than_max_length_is_rejected()
    {
        $this->assertRejected(['heading' => str_repeat('a', 121)]);
    }

    public function test_colour_token_is_expanded_to_a_css_custom_property()
    {
        $result = $this->apply($this->response([
            $this->updateAction('hero-1', ['backgroundColor' => 'accent-2']),
        ]));

        $this->assertSame(
            'var(--wp--preset--color--accent-2)',
            $result['actions'][0]['changes']['backgroundColor']
        );
    }

    public function test_colour_slug_absent_from_theme_json_is_rejected()
    {
        $this->assertRejected(['backgroundColor' => 'accent-9']);
    }

    public function test_literal_hex_supplied_for_a_token_field_is_rejected()
    {
        $this->assertRejected(['backgroundColor' => '#FF6B6B']);
    }

    public function test_url_with_a_disallowed_scheme_is_rejected()
    {
        $this->assertRejected(['ctaUrl' => 'javascript:alert(1)']);
    }

    public function test_https_url_is_accepted()
    {
        $result = $this->apply($this->response([
            $this->updateAction('hero-1', ['ctaUrl' => 'https://example.com/pricing']),
        ]));

        $this->assertSame('https://example.com/pricing', $result['actions'][0]['changes']['ctaUrl']);
    }

    public function test_boolean_value_is_coerced()
    {
        $result = $this->apply($this->response([
            $this->updateAction('hero-1', ['headingFullWidth' => 'true']),
        ]));

        $this->assertTrue($result['actions'][0]['changes']['headingFullWidth']);
    }

    public function test_content_longer_than_the_richtext_max_length_is_rejected()
    {
        try {
            $this->apply($this->response([[
                'type'     => 'update_block_content',
                'clientId' => 'para-1',
                'content'  => str_repeat('a', 5001),
            ]]));
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('value', $e->getStage());
        }
    }
}
