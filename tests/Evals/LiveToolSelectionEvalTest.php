<?php

namespace SwishfolioCore\Tests\Evals;

require_once SFCORE_PLUGIN_DIR . 'tests/Unit/Tools/wp-tools-stubs.php';

use PHPUnit\Framework\TestCase;
use SwishfolioCore\AI\Agent\AgentLoop;
use SwishfolioCore\AI\Agent\AgentRun;
use SwishfolioCore\AI\Prompt\PromptManager;
use SwishfolioCore\Blocks\BlockRegistry;
use SwishfolioCore\Tests\Evals\Support\LiveEvalProvider;
use SwishfolioCore\Tests\Evals\Support\LiveMode;
use SwishfolioCore\Tests\Support\InMemoryJournalRepository;
use SwishfolioCore\Tools\Journal\MutationJournal;
use SwishfolioCore\Tools\ToolContext;
use SwishfolioCore\Tools\ToolRegistry;
use SwishfolioCore\Tools\ToolRunner;

/**
 * The eval the tool-layer spec asked for, now that provider tool-calling exists.
 *
 * This is the one measurement no recorded fixture can make: given today's
 * descriptions and today's system prompt, does a real model reach for the right
 * tool? A catalogue where two tools read alike, or a prompt that stopped saying
 * "look before you act", leaves every recorded test green and makes the agent
 * visibly stupid for every user. Only a live model answers that question.
 *
 * The assertion is deliberately loose. There is usually more than one defensible
 * first move — "what pages do I have" is well served by list-content and
 * excusably served by site-info — so each case names a set, and the failure that
 * matters is a model reaching for something unrelated or answering from thin air
 * without looking at all.
 *
 * To run it:
 *
 *     SWISHFOLIO_AI_EVAL_LIVE=1 \
 *     SWISHFOLIO_AI_EVAL_API_KEY=sk-... \
 *     SWISHFOLIO_AI_EVAL_MODEL=gpt-4o-mini \
 *     vendor/bin/phpunit --testsuite evals --group live
 *
 * @group live
 */
class LiveToolSelectionEvalTest extends TestCase
{
    /** @var InMemoryJournalRepository */
    private $repository;

    protected function setUp(): void
    {
        if (! LiveMode::isEnabled()) {
            $this->markTestSkipped(LiveMode::reasonDisabled());
        }

        parent::setUp();

        $GLOBALS['sfcore_test_transients'] = [];

        sfcore_test_tools_reset();

        $this->repository = new InMemoryJournalRepository();

        $this->seedSite();
    }

    /**
     * A small but realistic site, so the model has something true to find.
     *
     * @return void
     */
    private function seedSite()
    {
        $pages = [
            [10, 'Home', 'home', 'publish'],
            [11, 'About', 'about', 'publish'],
            [12, 'Contact', 'contact', 'draft'],
        ];

        foreach ($pages as $page) {
            $GLOBALS['sfcore_test_wp']['posts'][$page[0]] = [
                'ID'           => $page[0],
                'post_type'    => 'page',
                'post_status'  => $page[3],
                'post_title'   => $page[1],
                'post_name'    => $page[2],
                'post_content' => '<!-- wp:paragraph --><p>Placeholder.</p><!-- /wp:paragraph -->',
                'post_author'  => 1,
            ];
        }
    }

    private function loop()
    {
        $env      = LiveMode::environment();
        $provider = new LiveEvalProvider();

        $provider->setCredentials(LiveMode::credentialsFor($env));

        $journal  = new MutationJournal($this->repository);
        $registry = ToolRegistry::withDefaultTools(
            $journal,
            new BlockRegistry(
                [
                    SFCORE_PLUGIN_DIR . 'blocks',
                    SFCORE_PLUGIN_DIR . 'src/AI/Manifests/core',
                ]
            )
        );

        return new AgentLoop(
            $provider,
            new ToolRunner($registry, $journal),
            new PromptManager(SFCORE_PLUGIN_DIR . 'src/AI/Prompt'),
            LiveMode::modelFor($env)
        );
    }

    /**
     * @return array[] instruction => [acceptable first tools]
     */
    public static function selectionCases()
    {
        return [
            'list the pages'       => ['what pages do I have?', ['swishfolio/list-content', 'swishfolio/site-info']],
            'read one page'        => ['what is on my About page?', ['swishfolio/list-content', 'swishfolio/read-page', 'swishfolio/site-info']],
            'site orientation'     => ['tell me about this site', ['swishfolio/site-info']],
            'design tokens'        => ['what colours can I use on this site?', ['swishfolio/get-global-styles', 'swishfolio/site-info']],
            'block vocabulary'     => ['which blocks can you add to a page?', ['swishfolio/get-block-capabilities']],
            'find an image'        => ['what images are already uploaded?', ['swishfolio/list-media']],
            'change the front page' => ['make Home the front page', ['swishfolio/set-front-page', 'swishfolio/list-content', 'swishfolio/site-info']],
        ];
    }

    /**
     * @dataProvider selectionCases
     *
     * @param string   $instruction What the owner asked.
     * @param string[] $acceptable  Tools that are a defensible first move.
     *
     * @return void
     */
    public function test_the_model_reaches_for_a_sensible_tool_first($instruction, array $acceptable)
    {
        $run = $this->loop()->run($instruction, new ToolContext(7, 1));

        $steps = $run->getSteps();

        if ($steps === [] && $run->getStatus() === AgentRun::STATUS_PENDING) {
            // A gated first call never becomes a step, so read the proposal.
            $this->assertContains(
                $run->getPending()[0]['name'],
                $acceptable,
                sprintf('"%s" proposed %s first.', $instruction, $run->getPending()[0]['name'])
            );

            return;
        }

        $this->assertNotEmpty(
            $steps,
            sprintf('"%s" was answered without looking at the site at all.', $instruction)
        );

        $this->assertContains(
            $steps[0]->getTool(),
            $acceptable,
            sprintf('"%s" reached for %s first.', $instruction, $steps[0]->getTool())
        );
    }

    public function test_a_read_only_question_is_answered_without_changing_anything()
    {
        $run = $this->loop()->run('summarise what is on this site', new ToolContext(7, 1));

        $this->assertNotSame(AgentRun::STATUS_CAPPED, $run->getStatus());
        $this->assertSame([], $this->repository->rows, 'A question must not write to the site.');
    }

    public function test_the_model_does_not_invent_a_page_id()
    {
        $run = $this->loop()->run('read the page called About and tell me what it says', new ToolContext(7, 1));

        foreach ($run->getSteps() as $step) {
            if ($step->getTool() !== 'swishfolio/read-page') {
                continue;
            }

            $this->assertTrue(
                $step->isOk(),
                'read-page failed, which usually means an invented ID: ' . $step->getSummary()
            );
        }

        $this->assertNotSame(AgentRun::STATUS_CAPPED, $run->getStatus());
    }
}
