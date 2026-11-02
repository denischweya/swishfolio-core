<?php

namespace SwishfolioCore\Tests\Unit\Tools;

require_once __DIR__ . '/wp-tools-stubs.php';

use PHPUnit\Framework\TestCase;
use SwishfolioCore\Blocks\BlockRegistry;
use SwishfolioCore\Tests\Support\InMemoryJournalRepository;
use SwishfolioCore\Tools\Journal\MutationJournal;
use SwishfolioCore\Tools\ToolContext;
use SwishfolioCore\Tools\ToolRegistry;
use SwishfolioCore\Tools\ToolRunner;

abstract class ToolsTestCase extends TestCase
{
    /** @var InMemoryJournalRepository */
    protected $repository;

    /** @var MutationJournal */
    protected $journal;

    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['sfcore_test_transients'] = [];

        sfcore_test_tools_reset();

        $this->repository = new InMemoryJournalRepository();
        $this->journal    = new MutationJournal($this->repository);
    }

    /**
     * @param bool $confirmed Whether the user approved the plan.
     *
     * @return ToolContext
     */
    protected function context($confirmed = false)
    {
        $context = new ToolContext(7, 1, 'run0000000000000000000000000001');

        $context->setConfirmed($confirmed);

        return $context;
    }

    /**
     * @param array $tools Tools to register.
     *
     * @return ToolRunner
     */
    protected function runner(array $tools)
    {
        return new ToolRunner(new ToolRegistry($tools), $this->journal);
    }

    /**
     * @return BlockRegistry
     */
    protected function blocks()
    {
        return new BlockRegistry(
            [
                SFCORE_PLUGIN_DIR . 'blocks',
                SFCORE_PLUGIN_DIR . 'src/AI/Manifests/core',
            ]
        );
    }

    /**
     * @param int   $id     Post ID.
     * @param array $fields Post fields.
     *
     * @return void
     */
    protected function givenPost($id, array $fields = [])
    {
        $GLOBALS['sfcore_test_wp']['posts'][(int) $id] = array_merge(
            [
                'ID'           => (int) $id,
                'post_type'    => 'page',
                'post_status'  => 'publish',
                'post_title'   => 'Existing',
                'post_name'    => 'existing',
                'post_content' => '',
                'post_author'  => 1,
            ],
            $fields
        );
    }

    /**
     * @param int $id Post ID.
     *
     * @return array
     */
    protected function post($id)
    {
        return $GLOBALS['sfcore_test_wp']['posts'][(int) $id];
    }
}
