<?php

namespace SwishfolioCore\Tests\Unit\Actions;

use SwishfolioCore\Actions\Stages\EnvelopeStage;
use SwishfolioCore\Actions\ValidationException;

class EnvelopeStageTest extends StageTestCase
{
    private function apply(array $response)
    {
        return (new EnvelopeStage())->process($response, $this->context());
    }

    private function assertRejected(array $response)
    {
        try {
            $this->apply($response);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('envelope', $e->getStage());
        }
    }

    public function test_valid_envelope_passes_through()
    {
        $response = $this->response([$this->updateAction('hero-1', ['heading' => 'Hi'])]);

        $this->assertSame($response, $this->apply($response));
    }

    public function test_unknown_schema_version_is_rejected()
    {
        $this->assertRejected(['schemaVersion' => '9.9', 'summary' => 's', 'actions' => []]);
    }

    public function test_missing_actions_key_is_rejected()
    {
        $this->assertRejected(['schemaVersion' => '1.0', 'summary' => 's']);
    }

    public function test_actions_that_are_not_a_list_are_rejected()
    {
        $this->assertRejected(['schemaVersion' => '1.0', 'summary' => 's', 'actions' => ['a' => 1]]);
    }

    public function test_unknown_action_type_is_rejected()
    {
        $this->assertRejected($this->response([['type' => 'run_php', 'clientId' => 'hero-1']]));
    }

    public function test_action_count_above_the_cap_is_rejected()
    {
        $actions = array_fill(0, 26, $this->updateAction('hero-1', ['heading' => 'Hi']));

        $this->assertRejected($this->response($actions));
    }

    public function test_update_without_changes_is_rejected()
    {
        $this->assertRejected($this->response([
            ['type' => 'update_block_attributes', 'clientId' => 'hero-1', 'changes' => []],
        ]));
    }

    public function test_insert_without_block_name_is_rejected()
    {
        $this->assertRejected($this->response([
            ['type' => 'insert_block', 'parentClientId' => 'testimonials-1', 'attributes' => []],
        ]));
    }
}
