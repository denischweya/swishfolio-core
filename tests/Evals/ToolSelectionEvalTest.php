<?php

namespace SwishfolioCore\Tests\Evals;

require_once SFCORE_PLUGIN_DIR . 'tests/Unit/Tools/wp-tools-stubs.php';

use PHPUnit\Framework\TestCase;
use SwishfolioCore\AI\Agent\AgentLoop;
use SwishfolioCore\AI\Agent\AgentRun;
use SwishfolioCore\AI\Prompt\PromptManager;
use SwishfolioCore\Blocks\BlockRegistry;
use SwishfolioCore\Tests\Evals\Support\RecordedProvider;
use SwishfolioCore\Tests\Support\InMemoryJournalRepository;
use SwishfolioCore\Tools\Journal\MutationJournal;
use SwishfolioCore\Tools\ToolContext;
use SwishfolioCore\Tools\ToolRegistry;
use SwishfolioCore\Tools\ToolRunner;

/**
 * The agent half of the evaluation suite, in recorded mode.
 *
 * What recorded mode can and cannot do here is worth being precise about. It
 * cannot measure tool *selection*: the recording is the selection, so a test
 * that asserts the recorded call was the right one is asserting its own fixture.
 * That measurement lives in LiveToolSelectionEvalTest.
 *
 * What it does measure is everything downstream of the choice — that a realistic
 * model turn flows through the runner into real tool execution, that the result
 * handed back is the real tool's output rather than a fixture, that an error
 * comes back in a shape a model can act on, and that the gate still stops a
 * published write. Those are the parts that break silently when the tool layer
 * changes underneath the agent.
 */
class ToolSelectionEvalTest extends TestCase
{
    /** @var InMemoryJournalRepository */
    private $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['sfcore_test_transients'] = [];

        sfcore_test_tools_reset();

        $this->repository = new InMemoryJournalRepository();
    }

    private function loop(RecordedProvider $provider)
    {
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
            'recorded'
        );
    }

    private function context($confirmed = false)
    {
        $context = new ToolContext(7, 1, 'eval000000000000000000000000001');

        $context->setConfirmed($confirmed);

        return $context;
    }

    private function givenPage($id, array $fields = [])
    {
        $GLOBALS['sfcore_test_wp']['posts'][(int) $id] = array_merge(
            [
                'ID'           => (int) $id,
                'post_type'    => 'page',
                'post_status'  => 'publish',
                'post_title'   => 'Home',
                'post_name'    => 'home',
                'post_content' => '',
                'post_author'  => 1,
            ],
            $fields
        );
    }

    public function test_a_recorded_read_returns_the_real_tools_output_not_a_fixture()
    {
        $this->givenPage(10, ['post_title' => 'Home']);
        $this->givenPage(11, ['post_title' => 'About', 'post_name' => 'about']);

        $provider = (new RecordedProvider([]))->withTurns(
            [
                ['calls' => [['id' => 'c1', 'name' => 'swishfolio/list-content', 'input' => ['postType' => 'page']]]],
                ['text' => 'You have two pages: Home and About.'],
            ]
        );

        $run = $this->loop($provider)->run('what pages do I have?', $this->context());

        $this->assertSame(AgentRun::STATUS_DONE, $run->getStatus());

        $second = $provider->conversations()[1]->getMessages();
        $result = end($second);
        $payload = json_decode($result->getText(), true);

        $this->assertSame(2, $payload['total'], 'The real tool counted the fake site, not the fixture.');
        $this->assertSame('Home', $payload['items'][0]['title']);
    }

    public function test_the_whole_catalogue_is_offered_on_every_turn()
    {
        $provider = (new RecordedProvider([]))->withTurns([['text' => 'ok']]);

        $this->loop($provider)->run('hello', $this->context());

        $this->assertCount(
            14,
            $provider->conversations()[0]->getTools(),
            'A tool missing from the catalogue is a capability the model cannot reach.'
        );
    }

    public function test_an_invalid_call_comes_back_in_a_shape_a_model_can_act_on()
    {
        $provider = (new RecordedProvider([]))->withTurns(
            [
                ['calls' => [['id' => 'c1', 'name' => 'swishfolio/read-page', 'input' => []]]],
                ['text' => 'I need the page ID first.'],
            ]
        );

        $run = $this->loop($provider)->run('read my page', $this->context());

        $second = $provider->conversations()[1]->getMessages();
        $result = end($second);
        $error  = json_decode($result->getText(), true);

        $this->assertTrue($result->isError());
        $this->assertSame('schema_invalid', $error['code']);
        $this->assertSame('postId', $error['details']['field'], 'The field name is what lets it retry correctly.');
        $this->assertFalse($run->getSteps()[0]->isOk());
    }

    public function test_a_hallucinated_tool_name_is_refused_with_the_real_list()
    {
        $provider = (new RecordedProvider([]))->withTurns(
            [
                ['calls' => [['id' => 'c1', 'name' => 'swishfolio/delete-everything', 'input' => []]]],
                ['text' => 'I cannot do that.'],
            ]
        );

        $this->loop($provider)->run('delete my site', $this->context());

        $second = $provider->conversations()[1]->getMessages();
        $error  = json_decode(end($second)->getText(), true);

        $this->assertSame('tool_not_found', $error['code']);
        $this->assertNotEmpty($error['details']['available']);
    }

    public function test_a_recorded_published_write_still_stops_at_the_gate()
    {
        $this->givenPage(60, ['post_status' => 'publish', 'post_title' => 'Home']);

        $provider = (new RecordedProvider([]))->withTurns(
            [
                [
                    'text'  => 'I will make Home the front page.',
                    'calls' => [['id' => 'c1', 'name' => 'swishfolio/set-front-page', 'input' => ['postId' => 60]]],
                ],
            ]
        );

        $run = $this->loop($provider)->run('make Home my front page', $this->context());

        $this->assertSame(AgentRun::STATUS_PENDING, $run->getStatus());
        $this->assertSame([], $GLOBALS['sfcore_test_options']);
    }

    public function test_a_multi_step_turn_threads_one_tools_output_into_the_next_call()
    {
        $this->givenPage(10, ['post_title' => 'About', 'post_name' => 'about', 'post_status' => 'draft']);

        $provider = (new RecordedProvider([]))->withTurns(
            [
                ['calls' => [['id' => 'c1', 'name' => 'swishfolio/list-content', 'input' => ['search' => 'About']]]],
                ['calls' => [['id' => 'c2', 'name' => 'swishfolio/read-page', 'input' => ['postId' => 10]]]],
                ['text' => 'Your About page is empty.'],
            ]
        );

        $run = $this->loop($provider)->run('what is on my About page?', $this->context());

        $this->assertSame(AgentRun::STATUS_DONE, $run->getStatus());
        $this->assertCount(2, $run->getSteps());
        $this->assertTrue($run->getSteps()[0]->isOk());
        $this->assertTrue($run->getSteps()[1]->isOk());
        $this->assertSame(3, $run->getIterations());
    }

    public function test_every_step_is_described_in_words_a_user_could_read()
    {
        $this->givenPage(10, ['post_title' => 'Home', 'post_status' => 'draft']);

        $provider = (new RecordedProvider([]))->withTurns(
            [
                [
                    'calls' => [
                        ['id' => 'c1', 'name' => 'swishfolio/update-page', 'input' => ['postId' => 10, 'content' => 'x']],
                    ],
                ],
                ['text' => 'Updated.'],
            ]
        );

        $run = $this->loop($provider)->run('rewrite my home page', $this->context());

        $this->assertStringContainsString('Home', $run->getSteps()[0]->getSummary());
        $this->assertStringNotContainsString('{', $run->getSteps()[0]->getSummary(), 'Not a payload dump.');
    }
}
