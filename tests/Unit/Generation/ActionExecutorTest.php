<?php

namespace SwishfolioCore\Tests\Unit\Generation;

use SwishfolioCore\Actions\ActionExecutor;
use SwishfolioCore\Actions\Handlers\ExecutionContext;
use SwishfolioCore\Actions\Handlers\ExecutionException;
use SwishfolioCore\Actions\Handlers\ServerActionHandlerInterface;

class ActionExecutorTest extends GenerationTestCase
{
    public function test_it_registers_exactly_the_five_server_side_action_types()
    {
        $types = ActionExecutor::withDefaultHandlers()->types();

        sort($types);

        $this->assertSame(
            ['create_page', 'set_front_page', 'set_navigation', 'set_style_variation', 'update_page'],
            $types
        );
    }

    public function test_an_unknown_action_type_is_refused()
    {
        try {
            ActionExecutor::withDefaultHandlers()->execute(
                [['type' => 'update_template']],
                new ExecutionContext(7)
            );
            $this->fail('Expected ExecutionException');
        } catch (ExecutionException $e) {
            $this->assertSame('unknown_action', $e->getReason());
            $this->assertSame('update_template', $e->getActionType());
        }
    }

    public function test_every_action_is_authorized_before_it_runs()
    {
        $spy      = new SpyHandler();
        $executor = new ActionExecutor([$spy]);

        $executor->execute([['type' => 'spy']], new ExecutionContext(7));

        $this->assertSame(['authorize', 'execute'], $spy->calls);
    }

    public function test_a_refused_action_stops_the_run_and_reports_what_was_written()
    {
        $executor = new ActionExecutor([new SpyHandler(), new RefusingHandler()]);
        $context  = new ExecutionContext(7);

        try {
            $executor->execute([['type' => 'spy'], ['type' => 'refuse'], ['type' => 'spy']], $context);
            $this->fail('Expected ExecutionException');
        } catch (ExecutionException $e) {
            $this->assertSame('forbidden', $e->getReason());
            $this->assertSame([981], $e->getCreatedIds());
        }

        $this->assertCount(1, $context->getResults());
    }

    public function test_results_are_collected_in_execution_order()
    {
        $context = (new ActionExecutor([new SpyHandler()]))->execute(
            [['type' => 'spy'], ['type' => 'spy']],
            new ExecutionContext(7)
        );

        $this->assertCount(2, $context->getResults());
        $this->assertSame('spy', $context->getResults()[0]['type']);
    }
}

class SpyHandler implements ServerActionHandlerInterface
{
    /** @var string[] */
    public $calls = [];

    public function getType()
    {
        return 'spy';
    }

    public function authorize(array $action, ExecutionContext $context)
    {
        $this->calls[] = 'authorize';
    }

    public function execute(array $action, ExecutionContext $context)
    {
        $this->calls[] = 'execute';

        $context->recordCreated(981);

        return ['ok' => true];
    }
}

class RefusingHandler implements ServerActionHandlerInterface
{
    public function getType()
    {
        return 'refuse';
    }

    public function authorize(array $action, ExecutionContext $context)
    {
        throw new ExecutionException('refuse', 'forbidden', 'Nope.');
    }

    public function execute(array $action, ExecutionContext $context)
    {
        throw new \LogicException('execute must not be reached after a refusal.');
    }
}
