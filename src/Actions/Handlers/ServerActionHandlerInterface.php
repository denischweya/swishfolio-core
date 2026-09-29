<?php
/**
 * One server-side action the generation engine can execute.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Actions\Handlers;

/**
 * Unlike the in-editor actions, these write. So authorization is part of the
 * contract rather than something a caller remembers to do, and it is checked
 * against the specific object being written — never against a capability named
 * in the request.
 */
interface ServerActionHandlerInterface
{
    /**
     * @return string Action type this handler executes.
     */
    public function getType();

    /**
     * @param array            $action  Action payload.
     * @param ExecutionContext $context Run state.
     *
     * @throws ExecutionException When the current user may not perform it.
     *
     * @return void
     */
    public function authorize(array $action, ExecutionContext $context);

    /**
     * @param array            $action  Action payload.
     * @param ExecutionContext $context Run state.
     *
     * @throws ExecutionException When the write fails.
     *
     * @return array Result describing what was written.
     */
    public function execute(array $action, ExecutionContext $context);
}
