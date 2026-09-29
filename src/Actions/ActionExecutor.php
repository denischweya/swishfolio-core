<?php
/**
 * Runs server-side actions, the ones that actually write.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Actions;

use SwishfolioCore\Actions\Handlers\CreatePageHandler;
use SwishfolioCore\Actions\Handlers\ExecutionContext;
use SwishfolioCore\Actions\Handlers\ExecutionException;
use SwishfolioCore\Actions\Handlers\ServerActionHandlerInterface;
use SwishfolioCore\Actions\Handlers\SetFrontPageHandler;
use SwishfolioCore\Actions\Handlers\SetNavigationHandler;
use SwishfolioCore\Actions\Handlers\SetStyleVariationHandler;
use SwishfolioCore\Actions\Handlers\UpdatePageHandler;

/**
 * The deliberate counterpart of ActionValidator, and kept separate from it.
 *
 * ActionValidator serves in-editor edits, where PHP validates and the editor
 * applies, so native undo reverses everything and PHP never touches post
 * content. That invariant is not weakened here: this executor is only ever
 * reached by the generation endpoints, where there is no editor open to apply
 * anything and the user has approved a plan first.
 *
 * Every action is authorized immediately before it runs, by its own handler,
 * against the object it is about to write. The route-level capability check is
 * the outer door, not the only one.
 */
class ActionExecutor
{
    /** @var ServerActionHandlerInterface[] Keyed by action type. */
    private $handlers = [];

    /**
     * @param ServerActionHandlerInterface[] $handlers Handlers to register.
     */
    public function __construct(array $handlers)
    {
        foreach ($handlers as $handler) {
            $this->handlers[$handler->getType()] = $handler;
        }
    }

    /**
     * @return self
     */
    public static function withDefaultHandlers()
    {
        return new self([
            new CreatePageHandler(),
            new UpdatePageHandler(),
            new SetFrontPageHandler(),
            new SetNavigationHandler(),
            new SetStyleVariationHandler(),
        ]);
    }

    /**
     * @return string[] Action types this executor can run.
     */
    public function types()
    {
        return array_keys($this->handlers);
    }

    /**
     * @param string $type Action type.
     *
     * @return bool
     */
    public function supports($type)
    {
        return isset($this->handlers[$type]);
    }

    /**
     * Runs the actions in order, stopping at the first failure.
     *
     * There is no transaction across wp_insert_post calls, so a failure part-way
     * leaves earlier writes in place. Rather than pretend otherwise, the failure
     * carries the IDs created so far: "trash exactly these" is the only honest
     * undo available.
     *
     * @param array            $actions Actions in execution order.
     * @param ExecutionContext $context Run state.
     *
     * @throws ExecutionException When any action is refused or fails.
     *
     * @return ExecutionContext The same context, for chaining.
     */
    public function execute(array $actions, ExecutionContext $context)
    {
        foreach ($actions as $action) {
            $type = isset($action['type']) ? (string) $action['type'] : '';

            if (! isset($this->handlers[$type])) {
                throw new ExecutionException(
                    $type,
                    'unknown_action',
                    sprintf('No handler for action type "%s".', $type),
                    $context->getCreatedIds()
                );
            }

            $handler = $this->handlers[$type];

            try {
                $handler->authorize($action, $context);
                $result = $handler->execute($action, $context);
            } catch (ExecutionException $e) {
                $e->setCreatedIds($context->getCreatedIds());

                throw $e;
            }

            $context->addResult($type, $result);
        }

        return $context;
    }
}
