<?php
/**
 * Undoes a run.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Tools\Write;

use SwishfolioCore\Tools\Journal\JournalEntry;
use SwishfolioCore\Tools\Journal\JournalRepositoryInterface;
use SwishfolioCore\Tools\Journal\Rollback;
use SwishfolioCore\Tools\ToolContext;
use SwishfolioCore\Tools\ToolError;
use SwishfolioCore\Tools\ToolException;
use SwishfolioCore\Tools\ToolInterface;

/**
 * Authorization is computed from the run rather than fixed, because a rollback
 * is exactly as privileged as the writes it reverses. A run that only created
 * drafts needs no more than the capability to edit those drafts; a run that
 * wrote options needs the capability to write options. Demanding
 * manage_options for every rollback would stop an author undoing their own
 * draft, and demanding only edit_posts would let them revert a site setting.
 *
 * It does not extend AbstractWriteTool: it writes through Rollback, which
 * restores prior state recorded by the journal rather than making new changes,
 * so routing it through the journal's write gateway would be recording an undo
 * as though it were an edit.
 */
class RollbackRunTool implements ToolInterface
{
    /** @var Rollback */
    private $rollback;

    /** @var JournalRepositoryInterface */
    private $repository;

    /**
     * @param Rollback                        $rollback   Reversal engine.
     * @param JournalRepositoryInterface|null $repository Journal storage; taken from the rollback when omitted.
     */
    public function __construct(Rollback $rollback, JournalRepositoryInterface $repository = null)
    {
        $this->rollback   = $rollback;
        $this->repository = $repository;
    }

    public function getName()
    {
        return 'swishfolio/rollback-run';
    }

    public function getTitle()
    {
        return __('Undo a run', 'swishfolio-core');
    }

    public function getDescription()
    {
        return 'Undo everything one earlier run changed, newest change first: restores overwritten pages from '
            . 'their revisions, puts settings back, and trashes pages the run created. Pass the runId from the '
            . 'audit log. Must be confirmed.';
    }

    public function getSideEffect()
    {
        return ToolInterface::EFFECT_DESTRUCTIVE;
    }

    public function getInputSchema()
    {
        return [
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => ['runId'],
            'properties'           => [
                'runId' => [
                    'type'        => 'string',
                    'maxLength'   => 32,
                    'description' => 'Identifier of the run to undo, from the audit log.',
                ],
            ],
        ];
    }

    public function authorize(array $input, ToolContext $context)
    {
        $runId   = isset($input['runId']) ? (string) $input['runId'] : '';
        $entries = null === $this->repository ? [] : $this->repository->findRun($runId);

        foreach ($entries as $entry) {
            if (JournalEntry::TARGET_OPTION === $entry->getTargetType() && ! current_user_can('manage_options')) {
                $this->forbid(__('This run changed site settings, which you cannot undo.', 'swishfolio-core'));
            }

            if (JournalEntry::TARGET_POST === $entry->getTargetType()) {
                $postId = $this->postIdOf($entry);

                if ($postId > 0 && ! current_user_can('edit_post', $postId)) {
                    $this->forbid(__('This run changed a page you cannot edit.', 'swishfolio-core'));
                }
            }
        }

        if ($entries === [] && ! current_user_can('edit_posts')) {
            $this->forbid(__('You cannot undo AI changes on this site.', 'swishfolio-core'));
        }
    }

    public function execute(array $input, ToolContext $context)
    {
        $runId  = (string) $input['runId'];
        $result = $this->rollback->revertRun($runId);

        if (0 === $result['reverted'] && 0 === $result['failed'] && 0 === $result['alreadyReverted']) {
            throw ToolException::make(
                $this->getName(),
                ToolError::TARGET_MISSING,
                sprintf(
                    /* translators: %s: run identifier. */
                    __('There is nothing recorded for run "%s".', 'swishfolio-core'),
                    $runId
                )
            );
        }

        return $result;
    }

    /**
     * A created-post row stores the new ID in its prior payload; an overwrite
     * row stores it as the target. Both have to resolve or the capability check
     * silently passes on a post nobody checked.
     *
     * @param JournalEntry $entry Target row.
     *
     * @return int
     */
    private function postIdOf(JournalEntry $entry)
    {
        $prior = $entry->getPrior();

        if (isset($prior['created'])) {
            return (int) $prior['created'];
        }

        return (int) $entry->getTargetId();
    }

    /**
     * @param string $message Reason the user may not do this.
     *
     * @throws ToolException Always.
     *
     * @return void
     */
    private function forbid($message)
    {
        throw ToolException::make($this->getName(), ToolError::FORBIDDEN, $message);
    }
}
