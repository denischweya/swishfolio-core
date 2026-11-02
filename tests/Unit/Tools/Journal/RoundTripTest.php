<?php

namespace SwishfolioCore\Tests\Unit\Tools\Journal;

use SwishfolioCore\Tests\Unit\Tools\ToolsTestCase;
use SwishfolioCore\Tools\Journal\Rollback;
use SwishfolioCore\Tools\Write\CreatePageTool;
use SwishfolioCore\Tools\Write\SetFrontPageTool;
use SwishfolioCore\Tools\Write\SetNavigationTool;
use SwishfolioCore\Tools\Write\SetStyleVariationTool;
use SwishfolioCore\Tools\Write\UpdatePageTool;

/**
 * The keystone.
 *
 * A whole run — create pages, overwrite an existing published page, publish a
 * menu, repoint the front page, reskin the site — then one rollback, and the
 * site is back. Every other test in this directory exists to localise a failure
 * here; this is the only one that proves the spine as a whole.
 *
 * "Back" is defined precisely, because one part of it cannot be byte-identical:
 * pages the run created are trashed rather than erased, since a destructive
 * delete is never the right undo for something a user might want to recover.
 * So: everything that existed before is byte-identical, everything created is
 * trashed, and every option is as it was including the ones that did not exist.
 */
class RoundTripTest extends ToolsTestCase
{
    private function siteRunner()
    {
        return $this->runner(
            [
                new CreatePageTool($this->journal),
                new UpdatePageTool($this->journal),
                new SetFrontPageTool($this->journal),
                new SetNavigationTool($this->journal),
                new SetStyleVariationTool(
                    $this->journal,
                    function () {
                        return [
                            [
                                'title'    => 'Swiss International',
                                'version'  => 3,
                                'settings' => ['color' => ['palette' => [['slug' => 'accent-1', 'color' => '#FF3000']]]],
                            ],
                        ];
                    },
                    function () {
                        return 23;
                    }
                ),
            ]
        );
    }

    /**
     * @return array
     */
    private function snapshot()
    {
        return [
            'posts'   => $GLOBALS['sfcore_test_wp']['posts'],
            'options' => $GLOBALS['sfcore_test_options'],
        ];
    }

    public function test_a_whole_run_reverts_to_the_state_it_started_from()
    {
        $this->givenPost(10, [
            'post_status'  => 'publish',
            'post_title'   => 'About',
            'post_content' => '<!-- wp:paragraph --><p>The original about page.</p><!-- /wp:paragraph -->',
        ]);
        $this->givenPost(23, ['post_type' => 'wp_global_styles', 'post_content' => '{"version":3}']);

        $GLOBALS['sfcore_test_options']['show_on_front'] = 'posts';

        $before  = $this->snapshot();
        $context = $this->context(true);
        $runner  = $this->siteRunner();

        $home = $runner->run(
            'swishfolio/create-page',
            [
                'key'     => 'home',
                'title'   => 'Home',
                'slug'    => 'home',
                'content' => '<!-- wp:swishfolio-core/hero {"heading":"We build things"} /-->',
            ],
            $context
        );

        $runner->run(
            'swishfolio/update-page',
            [
                'postId'  => 10,
                'content' => '<!-- wp:paragraph --><p>Rewritten by the AI.</p><!-- /wp:paragraph -->',
                'title'   => 'About us',
            ],
            $context
        );

        $runner->run(
            'swishfolio/set-navigation',
            [
                'title' => 'Main',
                'items' => [
                    ['label' => 'Home', 'url' => 'https://example.test/home/', 'postId' => $home['postId']],
                    ['label' => 'About', 'url' => 'https://example.test/about/', 'postId' => 10],
                ],
            ],
            $context
        );

        $runner->run('swishfolio/set-front-page', ['postId' => 10], $context);
        $runner->run('swishfolio/set-style-variation', ['title' => 'Swiss International'], $context);

        // The run really did change the site.
        $this->assertNotSame($before['posts'][10]['post_content'], $this->post(10)['post_content']);
        $this->assertSame('page', $GLOBALS['sfcore_test_options']['show_on_front']);
        $this->assertNotSame($before['posts'][23]['post_content'], $this->post(23)['post_content']);

        $result = (new Rollback($this->repository))->revertRun($context->getRunId());

        $this->assertTrue($result['complete'], 'Rollback reported failures: ' . wp_json_encode($result['failures']));

        foreach ($before['posts'] as $id => $post) {
            $this->assertSame(
                $post,
                $this->post($id),
                sprintf('Post %d is not byte-identical to its pre-run state.', $id)
            );
        }

        $this->assertSame(
            $before['options'],
            $GLOBALS['sfcore_test_options'],
            'Options are not identical to their pre-run state.'
        );

        foreach ($context->getCreatedIds() as $createdId) {
            $this->assertSame(
                'trash',
                $this->post($createdId)['post_status'],
                sprintf('Page %d was created by the run and should now be in the trash.', $createdId)
            );
        }
    }

    public function test_the_run_is_recorded_as_one_reversible_unit()
    {
        $this->givenPost(10, ['post_status' => 'publish', 'post_content' => 'before']);

        $context = $this->context(true);
        $runner  = $this->siteRunner();

        $runner->run(
            'swishfolio/create-page',
            ['key' => 'home', 'title' => 'Home', 'slug' => 'home', 'content' => 'x'],
            $context
        );
        $runner->run('swishfolio/update-page', ['postId' => 10, 'content' => 'after'], $context);

        $rows = $this->repository->findRun($context->getRunId());
        $seqs = [];

        foreach ($rows as $row) {
            $seqs[] = $row->getSeq();
        }

        $sorted = $seqs;
        sort($sorted);

        $this->assertSame($sorted, $seqs, 'Rows must come back in sequence order.');
        $this->assertSame(count(array_unique($seqs)), count($seqs), 'Every row needs its own sequence.');
    }
}
