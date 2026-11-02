<?php
/**
 * The only write path for journalled targets.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Tools\Journal;

use SwishfolioCore\Tools\ToolContext;
use SwishfolioCore\Tools\ToolError;
use SwishfolioCore\Tools\ToolException;
use SwishfolioCore\Tools\ToolInterface;

/**
 * Tools do not call wp_insert_post, wp_update_post or update_option themselves.
 * They call this, and it captures the prior state before performing the write.
 *
 * That is the whole point of the class. If capturing were left to each tool,
 * every new tool would be one forgotten line away from an unreversible write,
 * and the omission would only surface when a user tried to undo.
 */
class MutationJournal
{
    /** Input stored per call row, in bytes of JSON. */
    const INPUT_CAP = 4096;

    /** Marks an option that did not exist, distinct from one whose value was false. */
    const ABSENT = "\0swishfolio_absent\0";

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
     * @return JournalRepositoryInterface
     */
    public function repository()
    {
        return $this->repository;
    }

    /**
     * Appends the audit record for one tool call.
     *
     * @param ToolContext   $context   Run state.
     * @param ToolInterface $tool      Tool that ran.
     * @param array         $input     Validated input.
     * @param string        $summary   Human-readable description of the call.
     * @param string        $result    JournalEntry::RESULT_* constant.
     * @param string|null   $errorCode ToolError code when the call failed.
     * @param int           $seq       Sequence reserved before execution; 0 takes the next one.
     *
     * @return int Row ID.
     */
    public function recordCall(ToolContext $context, ToolInterface $tool, array $input, $summary, $result = JournalEntry::RESULT_OK, $errorCode = null, $seq = 0)
    {
        return $this->repository->append(
            new JournalEntry(
                [
                    'run_id'      => $context->getRunId(),
                    'seq'         => $seq > 0 ? (int) $seq : $context->nextSeq(),
                    'user_id'     => $context->getUserId(),
                    'tool'        => $tool->getName(),
                    'side_effect' => $tool->getSideEffect(),
                    'summary'     => $this->trim($summary, 300),
                    'input'       => $this->cappedInput($input),
                    'target_type' => JournalEntry::TARGET_CALL,
                    'target_id'   => '',
                    'prior'       => null,
                    'result'      => $result,
                    'error_code'  => $errorCode,
                    'created_at'  => $this->now(),
                ]
            )
        );
    }

    /**
     * Creates a post and records how to undo it.
     *
     * @param ToolContext $context  Run state.
     * @param string      $toolName Tool performing the write.
     * @param array       $postarr  wp_insert_post() arguments, already slashed.
     * @param string      $summary  Description for the audit log.
     *
     * @throws ToolException When the insert fails.
     *
     * @return int New post ID.
     */
    public function createPost(ToolContext $context, $toolName, array $postarr, $summary = '')
    {
        $postId = wp_insert_post($postarr, true);

        if (is_wp_error($postId) || (int) $postId <= 0) {
            throw ToolException::make(
                $toolName,
                ToolError::WRITE_FAILED,
                is_wp_error($postId) ? $postId->get_error_message() : __('The page could not be created.', 'swishfolio-core')
            );
        }

        $postId = (int) $postId;

        $context->recordCreated($postId);

        $this->appendTarget(
            $context,
            $toolName,
            JournalEntry::TARGET_POST,
            (string) $postId,
            ['created' => $postId],
            $summary
        );

        return $postId;
    }

    /**
     * Updates a post, capturing its prior state first.
     *
     * @param ToolContext $context  Run state.
     * @param string      $toolName Tool performing the write.
     * @param array       $postarr  wp_update_post() arguments including ID, already slashed.
     * @param string      $summary  Description for the audit log.
     *
     * @throws ToolException When the update fails.
     *
     * @return int Post ID.
     */
    public function updatePost(ToolContext $context, $toolName, array $postarr, $summary = '')
    {
        $postId = isset($postarr['ID']) ? (int) $postarr['ID'] : 0;

        if ($postId <= 0) {
            throw ToolException::make(
                $toolName,
                ToolError::TARGET_MISSING,
                __('No page was given to update.', 'swishfolio-core')
            );
        }

        $prior = $this->capturePost($postId);

        $result = wp_update_post($postarr, true);

        if (is_wp_error($result) || (int) $result <= 0) {
            throw ToolException::make(
                $toolName,
                ToolError::WRITE_FAILED,
                is_wp_error($result) ? $result->get_error_message() : __('The page could not be updated.', 'swishfolio-core')
            );
        }

        $this->appendTarget($context, $toolName, JournalEntry::TARGET_POST, (string) $postId, $prior, $summary);

        return $postId;
    }

    /**
     * Writes an option, capturing its prior value first.
     *
     * @param ToolContext $context  Run state.
     * @param string      $toolName Tool performing the write.
     * @param string      $name     Option name.
     * @param mixed       $value    New value.
     * @param string      $summary  Description for the audit log.
     *
     * @return void
     */
    public function updateOption(ToolContext $context, $toolName, $name, $value, $summary = '')
    {
        $current = get_option($name, self::ABSENT);
        $existed = self::ABSENT !== $current;

        update_option($name, $value);

        $this->appendTarget(
            $context,
            $toolName,
            JournalEntry::TARGET_OPTION,
            (string) $name,
            [
                'option'  => (string) $name,
                'prior'   => $existed ? $current : null,
                'existed' => $existed,
            ],
            $summary
        );
    }

    /**
     * Captures enough of a post to put it back.
     *
     * wp_save_post_revision() returns null when the post is unchanged since its
     * last revision, when the post type does not support revisions, and when
     * revisions are switched off site-wide with WP_POST_REVISIONS. All three
     * leave us with nothing to restore, so the prior content is stored inline
     * instead. Core supports revisions on wp_global_styles and wp_navigation, so
     * in practice this is the site-configuration path, not a per-type one.
     *
     * @param int $postId Post about to be written.
     *
     * @return array Prior-state payload.
     */
    public function capturePost($postId)
    {
        $postId = (int) $postId;

        if (function_exists('wp_save_post_revision')) {
            $revisionId = wp_save_post_revision($postId);

            if (! empty($revisionId) && ! is_wp_error($revisionId)) {
                return ['revision' => (int) $revisionId];
            }
        }

        return [
            'inline' => [
                'post_content' => (string) get_post_field('post_content', $postId),
                'post_title'   => (string) get_post_field('post_title', $postId),
            ],
        ];
    }

    /**
     * @param ToolContext $context    Run state.
     * @param string      $toolName   Tool performing the write.
     * @param string      $targetType JournalEntry::TARGET_* constant.
     * @param string      $targetId   Post ID or option name.
     * @param array       $prior      Prior-state payload.
     * @param string      $summary    Description for the audit log.
     *
     * @return int Row ID.
     */
    private function appendTarget(ToolContext $context, $toolName, $targetType, $targetId, array $prior, $summary)
    {
        return $this->repository->append(
            new JournalEntry(
                [
                    'run_id'      => $context->getRunId(),
                    'seq'         => $context->nextSeq(),
                    'user_id'     => $context->getUserId(),
                    'tool'        => (string) $toolName,
                    'side_effect' => '',
                    'summary'     => $this->trim($summary, 300),
                    'input'       => null,
                    'target_type' => (string) $targetType,
                    'target_id'   => (string) $targetId,
                    'prior'       => $prior,
                    'result'      => JournalEntry::RESULT_OK,
                    'error_code'  => null,
                    'created_at'  => $this->now(),
                ]
            )
        );
    }

    /**
     * Input is stored for the audit log, so it is capped and redacted rather
     * than kept whole: a generated page body would dwarf every other column,
     * and truncating raw JSON would leave the column unparseable.
     *
     * @param array $input Validated input.
     *
     * @return string JSON.
     */
    private function cappedInput(array $input)
    {
        $json = wp_json_encode($this->redact($input));

        if (! is_string($json)) {
            return wp_json_encode(['_unencodable' => true]);
        }

        if (strlen($json) <= self::INPUT_CAP) {
            return $json;
        }

        return wp_json_encode(
            [
                '_truncated' => true,
                'preview'    => substr($json, 0, self::INPUT_CAP - 64),
            ]
        );
    }

    /**
     * @param array $input Validated input.
     *
     * @return array
     */
    private function redact(array $input)
    {
        $out = [];

        foreach ($input as $key => $value) {
            if (is_string($key) && preg_match('/(key|secret|token|password)/i', $key)) {
                $out[$key] = '[redacted]';

                continue;
            }

            $out[$key] = is_array($value) ? $this->redact($value) : $value;
        }

        return $out;
    }

    /**
     * @param string $text  Text to shorten.
     * @param int    $limit Maximum characters.
     *
     * @return string
     */
    private function trim($text, $limit)
    {
        $text = (string) $text;

        return mb_strlen($text) > $limit ? mb_substr($text, 0, $limit - 1) . '…' : $text;
    }

    /**
     * @return string MySQL datetime in UTC.
     */
    private function now()
    {
        return function_exists('current_time') ? current_time('mysql', true) : gmdate('Y-m-d H:i:s');
    }
}
