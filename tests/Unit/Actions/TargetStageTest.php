<?php

namespace SwishfolioCore\Tests\Unit\Actions;

use SwishfolioCore\Actions\Stages\TargetStage;
use SwishfolioCore\Actions\ValidationException;

class TargetStageTest extends StageTestCase
{
    private function apply(array $response)
    {
        return (new TargetStage())->process($response, $this->context());
    }

    private function assertRejected(array $response)
    {
        try {
            $this->apply($response);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('target', $e->getStage());
        }
    }

    public function test_known_client_id_passes()
    {
        $response = $this->response([$this->updateAction('hero-1', ['heading' => 'Hi'])]);

        $this->assertSame($response, $this->apply($response));
    }

    public function test_client_id_absent_from_the_submitted_tree_is_rejected()
    {
        $this->assertRejected($this->response([$this->updateAction('ghost-9', ['heading' => 'Hi'])]));
    }

    public function test_insert_into_an_unknown_parent_is_rejected()
    {
        $this->assertRejected($this->response([[
            'type'           => 'insert_block',
            'parentClientId' => 'ghost-9',
            'blockName'      => 'swishfolio-core/testimonial-card',
            'attributes'     => [],
        ]]));
    }

    public function test_removing_a_block_outside_the_selection_subtree_is_rejected()
    {
        // hero-1 is a sibling of the selection root, not a descendant of it.
        $this->assertRejected($this->response([
            ['type' => 'remove_block', 'clientId' => 'hero-1'],
        ]));
    }

    public function test_removing_a_descendant_of_the_selection_is_allowed()
    {
        $response = $this->response([
            ['type' => 'remove_block', 'clientId' => 'card-1'],
        ]);

        $this->assertSame($response, $this->apply($response));
    }
}
