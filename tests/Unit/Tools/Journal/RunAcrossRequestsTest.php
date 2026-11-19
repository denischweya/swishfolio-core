<?php

namespace SwishfolioCore\Tests\Unit\Tools\Journal;

use SwishfolioCore\Tests\Unit\Tools\ToolsTestCase;
use SwishfolioCore\Tools\Journal\Rollback;
use SwishfolioCore\Tools\ToolContext;

/**
 * A build is one run written by several requests (the design, then a colour
 * change, then the finishing touches), each with a fresh context. One undo
 * must still put every post back as it was before the run.
 */
class RunAcrossRequestsTest extends ToolsTestCase
{
    const RUN = 'run0000000000000000000000000009';

    private function request()
    {
        return $this->journal->resume(new ToolContext(7, 1, self::RUN));
    }

    public function test_a_resumed_context_carries_on_after_the_rows_already_written()
    {
        $this->givenPost(40, ['post_content' => 'a']);
        $this->givenPost(41, ['post_content' => 'b']);

        $first = $this->request();
        $this->journal->updatePost($first, 'test/tool', ['ID' => 40, 'post_content' => 'a2']);
        $this->journal->updatePost($first, 'test/tool', ['ID' => 41, 'post_content' => 'b2']);

        $second = $this->request();
        $this->assertSame(3, $second->nextSeq());
    }

    public function test_one_undo_restores_a_post_two_requests_wrote()
    {
        $this->givenPost(40, ['post_content' => 'original styles']);

        // The design writes the styles, then a later request recolours them.
        $this->journal->updatePost($this->request(), 'test/design', ['ID' => 40, 'post_content' => 'kit styles']);
        $this->journal->updatePost($this->request(), 'test/tokens', ['ID' => 40, 'post_content' => 'dark kit styles']);

        $result = (new Rollback($this->repository))->revertRun(self::RUN);

        $this->assertTrue($result['complete']);
        $this->assertSame('original styles', $this->post(40)['post_content']);
    }

    public function test_rows_that_share_a_seq_are_undone_newest_first()
    {
        $this->givenPost(40, ['post_content' => 'original styles']);

        // Two contexts that were not resumed both start at seq 1.
        $this->journal->updatePost(new ToolContext(7, 1, self::RUN), 'test/design', ['ID' => 40, 'post_content' => 'kit styles']);
        $this->journal->updatePost(new ToolContext(7, 1, self::RUN), 'test/tokens', ['ID' => 40, 'post_content' => 'dark kit styles']);

        (new Rollback($this->repository))->revertRun(self::RUN);

        $this->assertSame('original styles', $this->post(40)['post_content']);
    }
}
