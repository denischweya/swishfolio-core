<?php

namespace SwishfolioCore\Tests\Unit\Tools\Journal;

use SwishfolioCore\Tests\Unit\Tools\ToolsTestCase;
use SwishfolioCore\Tools\Journal\JournalEntry;
use SwishfolioCore\Tools\Journal\Rollback;

/**
 * The inverse matrix. These are the cases where a plausible implementation
 * silently corrupts state rather than failing loudly, so each one exists because
 * getting it wrong is both easy and invisible.
 */
class RollbackTest extends ToolsTestCase
{
    private function rollback()
    {
        return new Rollback($this->repository);
    }

    public function test_an_overwritten_post_is_restored_from_its_revision()
    {
        $this->givenPost(50, ['post_content' => 'original', 'post_title' => 'Original']);

        $context = $this->context();

        $this->journal->updatePost(
            $context,
            'test/tool',
            ['ID' => 50, 'post_content' => 'replaced', 'post_title' => 'Replaced'],
            'Rewrote it'
        );

        $this->assertSame('replaced', $this->post(50)['post_content']);

        $result = $this->rollback()->revertRun($context->getRunId());

        $this->assertTrue($result['complete']);
        $this->assertSame(1, $result['reverted']);
        $this->assertSame('original', $this->post(50)['post_content']);
        $this->assertSame('Original', $this->post(50)['post_title']);
    }

    public function test_a_post_is_still_restored_exactly_when_revisions_are_switched_off()
    {
        // wp_save_post_revision() returns null when WP_POST_REVISIONS is false,
        // when the post is unchanged since its last revision, and when the type
        // does not support them. All three leave nothing to restore, so the
        // prior content is stored inline instead.
        $GLOBALS['sfcore_test_wp']['revisionsDisabled'] = true;

        $this->givenPost(50, ['post_content' => 'original', 'post_title' => 'Original']);

        $context = $this->context();

        $this->journal->updatePost(
            $context,
            'test/tool',
            ['ID' => 50, 'post_content' => 'replaced'],
            'Rewrote it'
        );

        $prior = $this->repository->ofType(JournalEntry::TARGET_POST)[0]->getPrior();

        $this->assertArrayNotHasKey('revision', $prior);
        $this->assertSame('original', $prior['inline']['post_content']);

        $this->rollback()->revertRun($context->getRunId());

        $this->assertSame('original', $this->post(50)['post_content']);
        $this->assertSame('Original', $this->post(50)['post_title']);
    }

    public function test_a_created_post_is_trashed_rather_than_deleted()
    {
        $context = $this->context();

        $postId = $this->journal->createPost(
            $context,
            'test/tool',
            ['post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'New'],
            'Created it'
        );

        $this->rollback()->revertRun($context->getRunId());

        $this->assertSame('trash', $this->post($postId)['post_status']);
    }

    public function test_an_option_that_did_not_exist_is_deleted_not_written_back_as_falsy()
    {
        // update_option('x', null) leaves a row get_option cannot distinguish
        // from a real setting, so the next read would see a setting the owner
        // never made. This is why 'existed' is stored separately.
        $context = $this->context();

        $this->journal->updateOption($context, 'test/tool', 'sfcore_new_option', 'value', 'Set it');

        $this->assertSame('value', $GLOBALS['sfcore_test_options']['sfcore_new_option']);

        $this->rollback()->revertRun($context->getRunId());

        $this->assertArrayNotHasKey('sfcore_new_option', $GLOBALS['sfcore_test_options']);
    }

    public function test_an_option_whose_prior_value_was_false_is_restored_as_false()
    {
        $GLOBALS['sfcore_test_options']['sfcore_flag'] = false;

        $context = $this->context();

        $this->journal->updateOption($context, 'test/tool', 'sfcore_flag', true, 'Set it');

        $this->rollback()->revertRun($context->getRunId());

        $this->assertArrayHasKey('sfcore_flag', $GLOBALS['sfcore_test_options']);
        $this->assertFalse($GLOBALS['sfcore_test_options']['sfcore_flag']);
    }

    public function test_an_option_whose_prior_value_was_empty_string_is_restored_as_empty_string()
    {
        $GLOBALS['sfcore_test_options']['sfcore_text'] = '';

        $context = $this->context();

        $this->journal->updateOption($context, 'test/tool', 'sfcore_text', 'something', 'Set it');

        $this->rollback()->revertRun($context->getRunId());

        $this->assertArrayHasKey('sfcore_text', $GLOBALS['sfcore_test_options']);
        $this->assertSame('', $GLOBALS['sfcore_test_options']['sfcore_text']);
    }

    public function test_targets_are_reverted_newest_first()
    {
        $this->givenPost(50, ['post_content' => 'v1']);

        $context = $this->context();

        $this->journal->updatePost($context, 'test/tool', ['ID' => 50, 'post_content' => 'v2'], 'a');
        $this->journal->updatePost($context, 'test/tool', ['ID' => 50, 'post_content' => 'v3'], 'b');

        $this->rollback()->revertRun($context->getRunId());

        $this->assertSame(
            'v1',
            $this->post(50)['post_content'],
            'Reverting oldest first would leave v2, the state the first write produced.'
        );
    }

    public function test_one_failure_does_not_stop_the_rest_of_the_run_from_reverting()
    {
        $this->givenPost(50, ['post_content' => 'original']);

        $context = $this->context();

        $this->journal->updateOption($context, 'test/tool', 'sfcore_first', 'x', 'first');
        $created = $this->journal->createPost(
            $context,
            'test/tool',
            ['post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Doomed'],
            'created'
        );
        $this->journal->updatePost($context, 'test/tool', ['ID' => 50, 'post_content' => 'replaced'], 'last');

        $GLOBALS['sfcore_test_wp']['trashFails'] = [$created];

        $result = $this->rollback()->revertRun($context->getRunId());

        $this->assertFalse($result['complete']);
        $this->assertSame(2, $result['reverted']);
        $this->assertSame(1, $result['failed']);
        $this->assertSame('original', $this->post(50)['post_content'], 'The later write must still revert.');
        $this->assertArrayNotHasKey('sfcore_first', $GLOBALS['sfcore_test_options'], 'The earlier write must still revert.');
    }

    public function test_a_failure_is_recorded_on_the_row_it_belongs_to()
    {
        $context = $this->context();

        $created = $this->journal->createPost(
            $context,
            'test/tool',
            ['post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Doomed'],
            'created'
        );

        $GLOBALS['sfcore_test_wp']['trashFails'] = [$created];

        $result = $this->rollback()->revertRun($context->getRunId());

        $row = $this->repository->ofType(JournalEntry::TARGET_POST)[0];

        $this->assertNotNull($row->getRevertError());
        $this->assertSame($created, (int) $result['failures'][0]['targetId']);
    }

    public function test_a_post_that_is_already_gone_counts_as_reverted()
    {
        $context = $this->context();

        $postId = $this->journal->createPost(
            $context,
            'test/tool',
            ['post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Gone'],
            'created'
        );

        unset($GLOBALS['sfcore_test_wp']['posts'][$postId]);

        $result = $this->rollback()->revertRun($context->getRunId());

        $this->assertTrue($result['complete'], 'The goal is that the post is not there, and it is not.');
    }

    public function test_a_lost_revision_is_reported_rather_than_silently_skipped()
    {
        $this->givenPost(50, ['post_content' => 'original']);

        $context = $this->context();

        $this->journal->updatePost($context, 'test/tool', ['ID' => 50, 'post_content' => 'replaced'], 'a');

        $prior = $this->repository->ofType(JournalEntry::TARGET_POST)[0]->getPrior();

        // WordPress prunes revisions past WP_POST_REVISIONS, so a revision
        // recorded months ago may simply not be there any more.
        $GLOBALS['sfcore_test_wp']['missingRevisions'] = [$prior['revision']];

        $result = $this->rollback()->revertRun($context->getRunId());

        $this->assertFalse($result['complete']);
        $this->assertStringContainsString('no longer available', $result['failures'][0]['error']);
    }

    public function test_reverting_twice_does_not_revert_anything_twice()
    {
        $this->givenPost(50, ['post_content' => 'original']);

        $context = $this->context();

        $this->journal->updatePost($context, 'test/tool', ['ID' => 50, 'post_content' => 'replaced'], 'a');

        $this->rollback()->revertRun($context->getRunId());

        $GLOBALS['sfcore_test_wp']['posts'][50]['post_content'] = 'edited by hand afterwards';

        $second = $this->rollback()->revertRun($context->getRunId());

        $this->assertSame(0, $second['reverted']);
        $this->assertSame(1, $second['alreadyReverted']);
        $this->assertSame(
            'edited by hand afterwards',
            $this->post(50)['post_content'],
            'A second rollback must not undo work done after the first.'
        );
    }

    public function test_call_rows_are_not_replayed()
    {
        $context = $this->context();

        $this->journal->updateOption($context, 'test/tool', 'sfcore_only', 'x', 'set');

        $result = $this->rollback()->revertRun($context->getRunId());

        $this->assertSame(1, $result['reverted'], 'Only the option target is a replayable row.');
    }
}
