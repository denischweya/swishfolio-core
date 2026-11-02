<?php
/**
 * Runs the generation engine's server-side actions.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Actions;

use SwishfolioCore\Actions\Handlers\ExecutionContext;
use SwishfolioCore\Actions\Handlers\ExecutionException;
use SwishfolioCore\Tools\Journal\MutationJournal;
use SwishfolioCore\Tools\Journal\WpdbJournalRepository;
use SwishfolioCore\Tools\ToolContext;
use SwishfolioCore\Tools\ToolException;
use SwishfolioCore\Tools\ToolRegistry;
use SwishfolioCore\Tools\ToolRunner;
use SwishfolioCore\Tools\Write\CreatePageTool;
use SwishfolioCore\Tools\Write\SetFrontPageTool;
use SwishfolioCore\Tools\Write\SetNavigationTool;
use SwishfolioCore\Tools\Write\SetStyleVariationTool;
use SwishfolioCore\Tools\Write\UpdatePageTool;

/**
 * Now a façade over ToolRunner rather than its own dispatcher.
 *
 * The five server-side handlers became tools, so there is one write path, one
 * validator and one journal for every mutation whatever calls it. What this
 * class still owns is the generation engine's vocabulary: plan actions are typed
 * "create_page", not "swishfolio/create-page", and they arrive as one array with
 * the type inside it rather than as a name and an input object.
 *
 * It also converts ToolException back to ExecutionException on the way out, so
 * PlanExecutor and GenerationController keep the error handling they already
 * have. The generation path reports one error shape; the tool path reports the
 * other; neither had to learn the other's.
 */
class ActionExecutor
{
    /** Plan action type => tool name. */
    const TOOL_FOR_TYPE = [
        'create_page'         => 'swishfolio/create-page',
        'update_page'         => 'swishfolio/update-page',
        'set_front_page'      => 'swishfolio/set-front-page',
        'set_navigation'      => 'swishfolio/set-navigation',
        'set_style_variation' => 'swishfolio/set-style-variation',
    ];

    /** @var ToolRunner */
    private $runner;

    /**
     * @param ToolRunner $runner Tool pipeline.
     */
    public function __construct(ToolRunner $runner)
    {
        $this->runner = $runner;
    }

    /**
     * The five write tools over the live journal. Read tools are absent on
     * purpose: a plan cannot ask for one, and building the block registry to
     * register them would make generation depend on manifest discovery.
     *
     * @return self
     */
    public static function withDefaultHandlers()
    {
        $journal = new MutationJournal(new WpdbJournalRepository());

        return new self(
            new ToolRunner(
                new ToolRegistry(
                    [
                        new CreatePageTool($journal),
                        new UpdatePageTool($journal),
                        new SetFrontPageTool($journal),
                        new SetNavigationTool($journal),
                        new SetStyleVariationTool($journal),
                    ]
                ),
                $journal
            )
        );
    }

    /**
     * @return string[] Action types this executor can run.
     */
    public function types()
    {
        $types = [];

        foreach (self::TOOL_FOR_TYPE as $type => $name) {
            if ($this->runner->registry()->has($name)) {
                $types[] = $type;
            }
        }

        return $types;
    }

    /**
     * @param string $type Action type.
     *
     * @return bool
     */
    public function supports($type)
    {
        return isset(self::TOOL_FOR_TYPE[$type])
            && $this->runner->registry()->has(self::TOOL_FOR_TYPE[$type]);
    }

    /**
     * Runs the actions in order, stopping at the first failure.
     *
     * A failure part-way still leaves earlier writes in place, but it is no
     * longer true that "trash exactly these" is the only undo available: every
     * write in the run is journalled, so the caller can reverse the whole run by
     * its run ID. The created IDs stay on the exception because callers already
     * read them.
     *
     * @param array            $actions Actions in execution order.
     * @param ExecutionContext $context Run state; must be a ToolContext.
     *
     * @throws ExecutionException When any action is refused or fails.
     *
     * @return ExecutionContext The same context, for chaining.
     */
    public function execute(array $actions, ExecutionContext $context)
    {
        if (! $context instanceof ToolContext) {
            throw new \LogicException(
                'ActionExecutor needs a ToolContext: a run without a run ID cannot be journalled or reversed.'
            );
        }

        foreach ($actions as $action) {
            $type = isset($action['type']) ? (string) $action['type'] : '';

            if (! isset(self::TOOL_FOR_TYPE[$type]) || ! $this->runner->registry()->has(self::TOOL_FOR_TYPE[$type])) {
                throw new ExecutionException(
                    $type,
                    'unknown_action',
                    sprintf('No handler for action type "%s".', $type),
                    $context->getCreatedIds()
                );
            }

            try {
                $result = $this->runner->run(
                    self::TOOL_FOR_TYPE[$type],
                    $this->inputFrom($action),
                    $context
                );
            } catch (ToolException $e) {
                throw new ExecutionException(
                    $type,
                    $e->getError()->getCode(),
                    $e->getMessage(),
                    $context->getCreatedIds()
                );
            }

            $context->addResult($type, $result);
        }

        return $context;
    }

    /**
     * Tool schemas are closed, and the plan builder emits optional keys as null
     * rather than omitting them — "template" => null on every page without one.
     * The handlers read those with empty(), so null and absent meant the same
     * thing; stripping nulls here keeps that true instead of making every
     * untemplated page a validation failure.
     *
     * @param array $action Plan action.
     *
     * @return array Tool input.
     */
    private function inputFrom(array $action)
    {
        $input = [];

        foreach ($action as $key => $value) {
            if ('type' === $key || null === $value) {
                continue;
            }

            $input[$key] = $value;
        }

        return $input;
    }
}
