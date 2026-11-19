<?php

namespace SwishfolioCore\Tests\Unit\Actions;

use SwishfolioCore\Actions\Stages\AllowlistStage;
use SwishfolioCore\Actions\ValidationException;

class AllowlistStageTest extends StageTestCase
{
    private function apply(array $response)
    {
        return (new AllowlistStage())->process($response, $this->context());
    }

    private function assertRejected(array $response)
    {
        try {
            $this->apply($response);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('allowlist', $e->getStage());
        }
    }

    public function test_manifest_attribute_is_allowed()
    {
        $response = $this->response([$this->updateAction('hero-1', ['heading' => 'Hi'])]);

        $this->assertSame($response, $this->apply($response));
    }

    public function test_attribute_absent_from_the_manifest_is_rejected()
    {
        // The canonical case: a real hero attribute that is deliberately not exposed.
        $this->assertRejected($this->response([
            $this->updateAction('hero-1', ['decoShape1Color' => 'accent-1']),
        ]));
    }

    public function test_attribute_update_against_a_content_tier_block_is_rejected()
    {
        $this->assertRejected($this->response([
            $this->updateAction('para-1', ['content' => 'Hi']),
        ]));
    }

    public function test_content_update_against_a_content_tier_block_is_allowed()
    {
        $response = $this->response([
            ['type' => 'update_block_content', 'clientId' => 'para-1', 'content' => 'Fresh copy'],
        ]);

        $this->assertSame($response, $this->apply($response));
    }

    public function test_content_update_against_a_block_with_no_richtext_field_is_rejected()
    {
        // testimonials is a layout container: no content group at all.
        $this->assertRejected($this->response([
            ['type' => 'update_block_content', 'clientId' => 'testimonials-1', 'content' => 'Nope'],
        ]));
    }

    public function test_inserting_a_permitted_child_is_allowed()
    {
        $response = $this->response([[
            'type'           => 'insert_block',
            'parentClientId' => 'testimonials-1',
            'blockName'      => 'swishfolio-core/testimonial-card',
            'attributes'     => ['quote' => 'Superb'],
        ]]);

        $this->assertSame($response, $this->apply($response));
    }

    public function test_inserting_a_child_the_parent_does_not_allow_is_rejected()
    {
        $this->assertRejected($this->response([[
            'type'           => 'insert_block',
            'parentClientId' => 'testimonials-1',
            'blockName'      => 'swishfolio-core/hero',
            'attributes'     => [],
        ]]));
    }

    public function test_inserted_child_attributes_are_allowlisted_too()
    {
        $this->assertRejected($this->response([[
            'type'           => 'insert_block',
            'parentClientId' => 'testimonials-1',
            'blockName'      => 'swishfolio-core/testimonial-card',
            'attributes'     => ['avatarImageId' => 12],
        ]]));
    }

    public function test_block_with_no_manifest_is_rejected()
    {
        $context  = $this->context();
        $response = $this->response([$this->updateAction('unknown-1', ['x' => 1])]);

        $tree                = $this->blockTree();
        $tree['unknown-1']   = ['name' => 'acme/widget', 'attributes' => [], 'parentClientId' => null];

        $context = new \SwishfolioCore\Actions\ValidationContext(
            $tree,
            'testimonials-1',
            $this->registry(),
            $this->tokens()
        );

        try {
            (new AllowlistStage())->process($response, $context);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('allowlist', $e->getStage());
        }
    }
}
