<?php
/**
 * Reverses a run by replaying its journal backwards.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Tools\Journal;

/**
 * Best-effort on purpose. If entry 3 of 7 cannot be reverted, stopping there
 * leaves the site in a state nobody asked for — half the run undone, with the
 * remaining four changes still live and no record of which. So the walk
 * continues, each failure is written to its own row's revert_error, and the
 * caller is told the result was partial.
 *
 * Only target rows are replayed. Call rows are the audit record of what was
 * asked and carry no prior state.
 */
class Rollback
{
    /** @var JournalRepositoryInterface */
    private $repository;

    /**
     * @param JournalRepositoryInterface $repository Storage.
     */
    public function __construct(JournalRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @param string $runId Run to reverse.
     *
     * @return array Outcome: runId, reverted, failed, alreadyReverted, complete, failures.
     */
    public function revertRun($runId)
    {
        $entries = $this->repository->findRun($runId);
        $targets = [];

        foreach ($entries as $entry) {
            if (JournalEntry::TARGET_CALL !== $entry->getTargetType()) {
                $targets[] = $entry;
            }
        }

        usort(
            $targets,
            static function (JournalEntry $a, JournalEntry $b) {
                return $b->getSeq() - $a->getSeq();
            }
        );

        $reverted = 0;
        $failed   = 0;
        $already  = 0;
        $failures = [];

        foreach ($targets as $entry) {
            if ($entry->isReverted()) {
                ++$already;

                continue;
            }

            try {
                $error = $this->revert($entry);
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }

            if ('' === $error) {
                ++$reverted;
                $this->repository->markReverted($entry->getId());

                continue;
            }

            ++$failed;
            $failures[] = [
                'seq'        => $entry->getSeq(),
                'targetType' => $entry->getTargetType(),
                'targetId'   => $entry->getTargetId(),
                'error'      => $error,
            ];

            $this->repository->markReverted($entry->getId(), $error);
        }

        return [
            'runId'           => (string) $runId,
            'reverted'        => $reverted,
            'failed'          => $failed,
            'alreadyReverted' => $already,
            'complete'        => 0 === $failed,
            'failures'        => $failures,
        ];
    }

    /**
     * @param JournalEntry $entry Target row.
     *
     * @return string '' on success, otherwise the failure message.
     */
    private function revert(JournalEntry $entry)
    {
        if (JournalEntry::TARGET_OPTION === $entry->getTargetType()) {
            return $this->revertOption($entry);
        }

        if (JournalEntry::TARGET_POST === $entry->getTargetType()) {
            return $this->revertPost($entry);
        }

        if (JournalEntry::TARGET_META === $entry->getTargetType()) {
            return $this->revertMeta($entry);
        }

        return sprintf('Unknown target type "%s".', $entry->getTargetType());
    }

    /**
     * An option that did not exist is deleted rather than written back as a
     * falsy value: update_option('x', null) leaves a row behind that get_option
     * cannot distinguish from a real setting, and the next read would see a
     * setting the owner never made.
     *
     * @param JournalEntry $entry Option row.
     *
     * @return string
     */
    private function revertOption(JournalEntry $entry)
    {
        $prior = $entry->getPrior();
        $name  = isset($prior['option']) ? (string) $prior['option'] : $entry->getTargetId();

        if ('' === $name) {
            return 'The journal row names no option.';
        }

        if (empty($prior['existed'])) {
            delete_option($name);

            return '';
        }

        update_option($name, isset($prior['prior']) ? $prior['prior'] : null);

        return '';
    }

    /**
     * Meta that did not exist is deleted rather than written back as an empty
     * string, for the same reason an absent option is deleted: get_post_meta
     * cannot tell an empty value from an absent key, and the next read would
     * see a value the owner never set.
     *
     * @param JournalEntry $entry Meta row.
     *
     * @return string
     */
    private function revertMeta(JournalEntry $entry)
    {
        $prior  = $entry->getPrior();
        $postId = isset($prior['postId']) ? (int) $prior['postId'] : 0;
        $key    = isset($prior['key']) ? (string) $prior['key'] : '';

        if ($postId <= 0 || '' === $key) {
            return 'The journal row names no meta field.';
        }

        if (empty($prior['existed'])) {
            delete_post_meta($postId, $key);

            return '';
        }

        update_post_meta($postId, $key, isset($prior['prior']) ? $prior['prior'] : '');

        return '';
    }

    /**
     * @param JournalEntry $entry Post row.
     *
     * @return string
     */
    private function revertPost(JournalEntry $entry)
    {
        $prior  = $entry->getPrior();
        $postId = (int) $entry->getTargetId();

        if (isset($prior['created'])) {
            return $this->trashCreated((int) $prior['created']);
        }

        if ($postId <= 0) {
            return 'The journal row names no post.';
        }

        if (isset($prior['revision'])) {
            $restored = wp_restore_post_revision((int) $prior['revision']);

            if (! empty($restored) && ! is_wp_error($restored)) {
                return '';
            }

            if (! isset($prior['inline'])) {
                return sprintf(
                    'Revision %d is no longer available, so this change cannot be undone automatically.',
                    (int) $prior['revision']
                );
            }
        }

        if (isset($prior['inline']) && is_array($prior['inline'])) {
            $result = wp_update_post(
                wp_slash(
                    [
                        'ID'           => $postId,
                        'post_content' => isset($prior['inline']['post_content']) ? $prior['inline']['post_content'] : '',
                        'post_title'   => isset($prior['inline']['post_title']) ? $prior['inline']['post_title'] : '',
                    ]
                ),
                true
            );

            if (is_wp_error($result) || (int) $result <= 0) {
                return is_wp_error($result) ? $result->get_error_message() : 'The post could not be restored.';
            }

            return '';
        }

        return 'The journal row carries no prior state.';
    }

    /**
     * A post that is already gone satisfies the goal, so its absence is success
     * rather than a failure to report.
     *
     * @param int $postId Post the run created.
     *
     * @return string
     */
    private function trashCreated($postId)
    {
        if ($postId <= 0) {
            return 'The journal row names no post.';
        }

        $status = get_post_status($postId);

        if (false === $status || 'trash' === $status) {
            return '';
        }

        $trashed = wp_trash_post($postId);

        return empty($trashed) ? sprintf('Page %d could not be trashed.', $postId) : '';
    }
}
