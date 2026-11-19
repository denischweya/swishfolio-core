<?php
/**
 * The single path a tool call takes.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Tools;

use SwishfolioCore\Actions\Handlers\ExecutionException;
use SwishfolioCore\Tools\Journal\JournalEntry;
use SwishfolioCore\Tools\Journal\MutationJournal;

/**
 * resolve → validate → authorize → confirm → execute → record, in that order,
 * with no way around it. Tools are never invoked directly by callers; the REST
 * layer, the generation engine and the editor all come through here, so the gate
 * cannot be bypassed by reaching for a handler.
 *
 * Reads skip the journal entirely. The journal answers "what changed and how do
 * I undo it", and read volume would bury that for no accountability gain.
 */
class ToolRunner
{
    /** @var ToolRegistry */
    private $registry;

    /** @var MutationJournal */
    private $journal;

    /** @var InputValidator */
    private $validator;

    /**
     * @param ToolRegistry    $registry  Available tools.
     * @param MutationJournal $journal   Write gateway and audit sink.
     * @param InputValidator  $validator Schema validator.
     */
    public function __construct(ToolRegistry $registry, MutationJournal $journal, InputValidator $validator = null)
    {
        $this->registry  = $registry;
        $this->journal   = $journal;
        $this->validator = $validator instanceof InputValidator ? $validator : new InputValidator();
    }

    /**
     * @return ToolRegistry
     */
    public function registry()
    {
        return $this->registry;
    }

    /**
     * Runs one tool.
     *
     * @param string      $name    Tool name.
     * @param array       $input   Raw input.
     * @param ToolContext $context Run state.
     *
     * @throws ToolException When the call is refused or fails.
     *
     * @return array Tool result.
     */
    public function run($name, array $input, ToolContext $context)
    {
        $tool  = $this->resolve($name);
        $clean = $this->validateInput($tool, $input);

        $this->authorize($tool, $clean, $context);
        $this->assertConfirmed($tool, $context);

        if (ToolInterface::EFFECT_READ === $tool->getSideEffect()) {
            return $this->execute($tool, $clean, $context);
        }

        // Reserved before execution so the audit row sorts ahead of the target
        // rows the call is about to write.
        $callSeq = $context->nextSeq();
        $summary = CallSummary::describe($tool, $clean);

        try {
            $result = $this->execute($tool, $clean, $context);
        } catch (ToolException $e) {
            $this->journal->recordCall(
                $context,
                $tool,
                $clean,
                $summary,
                JournalEntry::RESULT_ERROR,
                $e->getError()->getCode(),
                $callSeq
            );

            throw $e;
        }

        $this->journal->recordCall($context, $tool, $clean, $summary, JournalEntry::RESULT_OK, null, $callSeq);

        return $result;
    }

    /**
     * Runs a plan of calls in order, stopping at the first failure.
     *
     * @param array       $calls        Each ['name' => string, 'input' => array].
     * @param ToolContext $context      Run state.
     * @param string      $confirmToken Token issued with the preview.
     *
     * @throws ToolException When a call is refused or fails.
     *
     * @return array Per-call results.
     */
    public function runPlan(array $calls, ToolContext $context, $confirmToken = '')
    {
        if ('' !== (string) $confirmToken && ConfirmToken::verify($confirmToken, $calls, $context->getUserId())) {
            $context->setConfirmed(true);
        }

        $results = [];

        foreach ($calls as $call) {
            $name  = isset($call['name']) ? (string) $call['name'] : '';
            $input = isset($call['input']) && is_array($call['input']) ? $call['input'] : [];

            $results[] = [
                'name'   => $name,
                'result' => $this->run($name, $input, $context),
            ];
        }

        return $results;
    }

    /**
     * Describes what a plan would do, and issues the token that approves it.
     *
     * Validation and authorization run here too: a preview that promises
     * something the gate will refuse is worse than no preview.
     *
     * @param array       $calls   Planned calls.
     * @param ToolContext $context Run state.
     *
     * @throws ToolException When a call could never run.
     *
     * @return array
     */
    public function preview(array $calls, ToolContext $context)
    {
        $previews = [];
        $needs    = false;

        foreach ($calls as $call) {
            $name  = isset($call['name']) ? (string) $call['name'] : '';
            $input = isset($call['input']) && is_array($call['input']) ? $call['input'] : [];

            $tool  = $this->resolve($name);
            $clean = $this->validateInput($tool, $input);

            $requires = $this->requiresConfirmation($tool);

            // A tool whose side-effect class depends on its target says so from
            // authorize(), and update-page does: rewriting a draft is harmless,
            // rewriting a published page is not. During a preview that refusal
            // is the answer to the question being asked, not a failure — so it
            // becomes "this one needs confirming" rather than an error. Every
            // other refusal still propagates, because a preview that promises
            // something the gate will refuse is worse than no preview.
            try {
                $this->authorize($tool, $clean, $context);
            } catch (ToolException $e) {
                if (ToolError::CONFIRM_REQUIRED !== $e->getError()->getCode()) {
                    throw $e;
                }

                $requires = true;
            }

            $needs = $needs || $requires;

            $previews[] = [
                'name'                 => $name,
                'title'                => $tool->getTitle(),
                'summary'              => CallSummary::describe($tool, $clean),
                'sideEffect'           => $tool->getSideEffect(),
                'requiresConfirmation' => $requires,
            ];
        }

        return [
            'previews'             => $previews,
            'requiresConfirmation' => $needs,
            'confirmToken'         => ConfirmToken::issue($calls, $context->getUserId()),
        ];
    }

    /**
     * @param ToolInterface $tool Tool to check.
     *
     * @return bool
     */
    public function requiresConfirmation(ToolInterface $tool)
    {
        return in_array(
            $tool->getSideEffect(),
            [ToolInterface::EFFECT_WRITE_PUBLISHED, ToolInterface::EFFECT_DESTRUCTIVE],
            true
        );
    }

    /**
     * @param string $name Tool name.
     *
     * @throws ToolException When no such tool is registered.
     *
     * @return ToolInterface
     */
    private function resolve($name)
    {
        $tool = $this->registry->get($name);

        if (! $tool instanceof ToolInterface) {
            throw ToolException::make(
                (string) $name,
                ToolError::TOOL_NOT_FOUND,
                sprintf(
                    /* translators: %s: tool name. */
                    __('There is no tool called "%s".', 'swishfolio-core'),
                    (string) $name
                ),
                ['available' => $this->registry->names()]
            );
        }

        return $tool;
    }

    /**
     * @param ToolInterface $tool  Tool being called.
     * @param array         $input Raw input.
     *
     * @throws ToolException When the input does not match the schema.
     *
     * @return array Normalised input.
     */
    private function validateInput(ToolInterface $tool, array $input)
    {
        try {
            return $this->validator->validate($tool->getInputSchema(), $input);
        } catch (SchemaViolation $e) {
            throw ToolException::make(
                $tool->getName(),
                ToolError::SCHEMA_INVALID,
                $e->getMessage(),
                '' === $e->getField() ? [] : ['field' => $e->getField()]
            );
        }
    }

    /**
     * @param ToolInterface $tool    Tool being called.
     * @param array         $input   Validated input.
     * @param ToolContext   $context Run state.
     *
     * @throws ToolException When the user may not perform it.
     *
     * @return void
     */
    private function authorize(ToolInterface $tool, array $input, ToolContext $context)
    {
        try {
            $tool->authorize($input, $context);
        } catch (ExecutionException $e) {
            throw ToolException::fromExecution($tool->getName(), $e);
        }
    }

    /**
     * @param ToolInterface $tool    Tool being called.
     * @param ToolContext   $context Run state.
     *
     * @throws ToolException When confirmation is required and absent.
     *
     * @return void
     */
    private function assertConfirmed(ToolInterface $tool, ToolContext $context)
    {
        if (! $this->requiresConfirmation($tool) || $context->isConfirmed()) {
            return;
        }

        throw ToolException::make(
            $tool->getName(),
            ToolError::CONFIRM_REQUIRED,
            __('This change affects the live site, so it needs to be confirmed first.', 'swishfolio-core'),
            ['sideEffect' => $tool->getSideEffect()]
        );
    }

    /**
     * @param ToolInterface $tool    Tool being called.
     * @param array         $input   Validated input.
     * @param ToolContext   $context Run state.
     *
     * @throws ToolException When the tool fails.
     *
     * @return array
     */
    private function execute(ToolInterface $tool, array $input, ToolContext $context)
    {
        try {
            $result = $tool->execute($input, $context);
        } catch (ExecutionException $e) {
            throw ToolException::fromExecution($tool->getName(), $e);
        }

        return is_array($result) ? $result : [];
    }
}
