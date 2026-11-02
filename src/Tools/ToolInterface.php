<?php
/**
 * One site operation the AI can perform.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Tools;

/**
 * ServerActionHandlerInterface plus the four things an MCP-shaped tool needs: a
 * namespaced name, a description the model chooses by, a closed input schema and
 * a side-effect class.
 *
 * The description is not documentation. It is the text the model reads when
 * deciding which tool to call, and it is the same string the Abilities bridge
 * registers as meta.description — so a vague one degrades behaviour rather than
 * just readability.
 *
 * authorize() keeps the rule it had as a handler: checked against the specific
 * object being written, never against a capability named in the request.
 */
interface ToolInterface
{
    /** A read: no writes, no journal row with a prior, no confirmation. */
    const EFFECT_READ = 'read';

    /** Writes only drafts or new unpublished objects. */
    const EFFECT_WRITE_DRAFT = 'write_draft';

    /** Changes something visitors can see. Requires confirmation. */
    const EFFECT_WRITE_PUBLISHED = 'write_published';

    /** Removes or reverses. Requires confirmation. */
    const EFFECT_DESTRUCTIVE = 'destructive';

    /**
     * @return string Namespaced name, e.g. "swishfolio/site-info".
     */
    public function getName();

    /**
     * @return string Short human label for the audit log and admin UI.
     */
    public function getTitle();

    /**
     * @return string What the tool does and when to reach for it.
     */
    public function getDescription();

    /**
     * @return array JSON Schema for the input object. Closed: additionalProperties false.
     */
    public function getInputSchema();

    /**
     * @return string One of the EFFECT_* constants.
     */
    public function getSideEffect();

    /**
     * @param array       $input   Validated input.
     * @param ToolContext $context Run state.
     *
     * @throws ToolException When the current user may not perform it.
     *
     * @return void
     */
    public function authorize(array $input, ToolContext $context);

    /**
     * @param array       $input   Validated input.
     * @param ToolContext $context Run state.
     *
     * @throws ToolException When the operation fails.
     *
     * @return array Result describing what happened.
     */
    public function execute(array $input, ToolContext $context);
}
