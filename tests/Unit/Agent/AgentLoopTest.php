<?php

namespace SwishfolioCore\Tests\Unit\Agent;

use SwishfolioCore\AI\Agent\AgentLoop;
use SwishfolioCore\AI\Agent\AgentRun;
use SwishfolioCore\AI\Conversation\Message;
use SwishfolioCore\AI\Prompt\PromptManager;
use SwishfolioCore\AI\ProviderException;
use SwishfolioCore\Tests\Unit\AI\FakeProvider;
use SwishfolioCore\Tests\Unit\Tools\ToolsTestCase;
use SwishfolioCore\Tools\ToolRegistry;
use SwishfolioCore\Tools\ToolRunner;

class AgentLoopTest extends ToolsTestCase
{
    /** @var FakeProvider */
    private $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = new FakeProvider();
    }

    private function loop($brand = '')
    {
        $registry = ToolRegistry::withDefaultTools($this->journal, $this->blocks());

        return new AgentLoop(
            $this->provider,
            new ToolRunner($registry, $this->journal),
            new PromptManager(SFCORE_PLUGIN_DIR . 'src/AI/Prompt'),
            'fake-model',
            $brand
        );
    }

    public function test_an_answer_with_no_tool_calls_finishes_the_turn()
    {
        $this->provider->replies = [['text' => 'You have three pages.']];

        $run = $this->loop()->run('how many pages do I have?', $this->context());

        $this->assertSame(AgentRun::STATUS_DONE, $run->getStatus());
        $this->assertSame('You have three pages.', $run->getMessage());
        $this->assertSame([], $run->getSteps());
        $this->assertSame(1, $run->getIterations());
    }

    public function test_a_read_is_run_and_its_result_fed_back_before_the_answer()
    {
        $this->givenPost(10, ['post_title' => 'Home', 'post_status' => 'publish']);

        $this->provider->replies = [
            ['text' => 'Let me look.', 'calls' => [['id' => 'c1', 'name' => 'swishfolio/site-info']]],
            ['text' => 'Your site is called Acme Studio.'],
        ];

        $run = $this->loop()->run('what is my site called?', $this->context());

        $this->assertSame(AgentRun::STATUS_DONE, $run->getStatus());
        $this->assertCount(1, $run->getSteps());
        $this->assertTrue($run->getSteps()[0]->isOk());
        $this->assertSame(2, $run->getIterations());

        // The second conversation must carry the result of the first call.
        $second = $this->provider->conversations[1]->getMessages();
        $last   = end($second);

        $this->assertSame(Message::ROLE_TOOL, $last->getRole());
        $this->assertStringContainsString('Acme Studio', $last->getText());
    }

    public function test_a_tool_error_is_handed_back_so_the_model_can_correct_itself()
    {
        // read-page requires postId. The first call omits it; the model should
        // see schema_invalid with the field name and try again.
        $this->givenPost(10, ['post_title' => 'Home']);

        $this->provider->replies = [
            ['calls' => [['id' => 'c1', 'name' => 'swishfolio/read-page', 'input' => []]]],
            ['calls' => [['id' => 'c2', 'name' => 'swishfolio/read-page', 'input' => ['postId' => 10]]]],
            ['text' => 'Your home page has one paragraph.'],
        ];

        $run = $this->loop()->run('what is on my home page?', $this->context());

        $this->assertSame(AgentRun::STATUS_DONE, $run->getStatus());
        $this->assertCount(2, $run->getSteps());
        $this->assertFalse($run->getSteps()[0]->isOk());
        $this->assertSame('schema_invalid', $run->getSteps()[0]->getErrorCode());
        $this->assertTrue($run->getSteps()[1]->isOk());

        $second = $this->provider->conversations[1]->getMessages();
        $result = end($second);

        $this->assertTrue($result->isError());
        $this->assertStringContainsString('postId', $result->getText());
    }

    public function test_a_change_to_the_live_site_pauses_for_approval_and_writes_nothing()
    {
        $this->givenPost(60, ['post_status' => 'publish', 'post_title' => 'Home']);

        $this->provider->replies = [
            [
                'text'  => 'I will point the front page at Home.',
                'calls' => [['id' => 'c1', 'name' => 'swishfolio/set-front-page', 'input' => ['postId' => 60]]],
            ],
        ];

        $run = $this->loop()->run('make Home my front page', $this->context());

        $this->assertSame(AgentRun::STATUS_PENDING, $run->getStatus());
        $this->assertSame('I will point the front page at Home.', $run->getMessage());
        $this->assertSame('swishfolio/set-front-page', $run->getPending()[0]['name']);
        $this->assertNotSame('', $run->getConfirmToken());
        $this->assertSame([], $GLOBALS['sfcore_test_options'], 'Nothing may be written before approval.');
        $this->assertSame([], $this->repository->rows, 'A paused call leaves no journal rows.');
    }

    public function test_approving_resumes_the_run_without_asking_the_model_again()
    {
        $this->givenPost(60, ['post_status' => 'publish', 'post_title' => 'Home']);

        $this->provider->replies = [
            [
                'text'  => 'I will point the front page at Home.',
                'calls' => [['id' => 'c1', 'name' => 'swishfolio/set-front-page', 'input' => ['postId' => 60]]],
            ],
        ];

        $loop    = $this->loop();
        $pending = $loop->run('make Home my front page', $this->context());

        $this->provider->replies = [['text' => 'Done — Home is now your front page.']];

        $resumed = $loop->run(
            '',
            $this->context(),
            $pending->getHistory(),
            $pending->getConfirmToken()
        );

        $this->assertSame(AgentRun::STATUS_DONE, $resumed->getStatus());
        $this->assertSame('page', $GLOBALS['sfcore_test_options']['show_on_front']);
        $this->assertSame(60, $GLOBALS['sfcore_test_options']['page_on_front']);
        $this->assertCount(1, $resumed->getSteps());
        $this->assertTrue($resumed->getSteps()[0]->isOk());
    }

    public function test_an_approval_covers_only_the_approved_calls_not_what_the_model_does_next()
    {
        $this->givenPost(60, ['post_status' => 'publish', 'post_title' => 'Home']);
        $this->givenPost(61, ['post_status' => 'publish', 'post_title' => 'Other']);

        $this->provider->replies = [
            ['calls' => [['id' => 'c1', 'name' => 'swishfolio/set-front-page', 'input' => ['postId' => 60]]]],
        ];

        $loop    = $this->loop();
        $pending = $loop->run('make Home my front page', $this->context());

        // After the approved call runs, the model (say, steered by injected
        // text) tries a second live-site change it was never approved for.
        $this->provider->replies = [
            ['calls' => [['id' => 'c2', 'name' => 'swishfolio/set-front-page', 'input' => ['postId' => 61]]]],
        ];

        $resumed = $loop->run('', $this->context(), $pending->getHistory(), $pending->getConfirmToken());

        $this->assertSame(AgentRun::STATUS_PENDING, $resumed->getStatus(), 'The unapproved call must ask again.');
        $this->assertSame(60, $GLOBALS['sfcore_test_options']['page_on_front'], 'Only the approved page was set.');
        $this->assertSame(61, $resumed->getPending()[0]['input']['postId']);
    }

    public function test_a_token_for_other_calls_does_not_approve_these_ones()
    {
        $this->givenPost(60, ['post_status' => 'publish', 'post_title' => 'Home']);
        $this->givenPost(61, ['post_status' => 'publish', 'post_title' => 'Other']);

        $this->provider->replies = [
            ['calls' => [['id' => 'c1', 'name' => 'swishfolio/set-front-page', 'input' => ['postId' => 60]]]],
        ];

        $loop    = $this->loop();
        $pending = $loop->run('make Home my front page', $this->context());

        // The history is rewritten to point at a different page while keeping
        // the token that approved the original.
        $history   = $pending->getHistory();
        $history[1] = Message::assistant(
            '',
            [new \SwishfolioCore\AI\Conversation\ToolCall('c1', 'swishfolio/set-front-page', ['postId' => 61])]
        );

        // With the token rejected the turn proceeds as an ordinary one, so the
        // model is asked again. Scripting the same write back means the gate is
        // what has to stop it — and it must, because nothing approved postId 61.
        $this->provider->replies = [
            ['calls' => [['id' => 'c1', 'name' => 'swishfolio/set-front-page', 'input' => ['postId' => 61]]]],
        ];

        $resumed = $loop->run('', $this->context(), $history, $pending->getConfirmToken());

        $this->assertSame(AgentRun::STATUS_PENDING, $resumed->getStatus());
        $this->assertSame(
            [],
            $GLOBALS['sfcore_test_options'],
            'A token issued for one page must never write another.'
        );
        $this->assertSame(61, $resumed->getPending()[0]['input']['postId'], 'It asks again for the real target.');
    }

    public function test_the_whole_batch_is_offered_for_approval_not_just_the_gated_call()
    {
        $this->givenPost(60, ['post_status' => 'publish', 'post_title' => 'Home']);

        $this->provider->replies = [
            [
                'calls' => [
                    ['id' => 'c1', 'name' => 'swishfolio/site-info'],
                    ['id' => 'c2', 'name' => 'swishfolio/set-front-page', 'input' => ['postId' => 60]],
                ],
            ],
        ];

        $run = $this->loop()->run('set up my front page', $this->context());

        $this->assertSame(AgentRun::STATUS_PENDING, $run->getStatus());
        $this->assertCount(2, $run->getPending(), 'The model reasoned about these together.');
    }

    public function test_a_model_that_never_stops_calling_tools_is_capped()
    {
        $this->provider->replies = array_fill(
            0,
            20,
            ['calls' => [['id' => 'c', 'name' => 'swishfolio/site-info']]]
        );

        $run = $this->loop()->run('keep going forever', $this->context());

        $this->assertSame(AgentRun::STATUS_CAPPED, $run->getStatus());
        $this->assertSame(AgentLoop::MAX_ITERATIONS, $run->getIterations());
        $this->assertCount(AgentLoop::MAX_ITERATIONS, $run->getSteps());
    }

    public function test_the_iteration_cap_is_filterable()
    {
        $GLOBALS['sfcore_test_filters']['swishfolio_ai_agent_max_iterations'] = static function () {
            return 2;
        };

        $this->provider->replies = array_fill(
            0,
            20,
            ['calls' => [['id' => 'c', 'name' => 'swishfolio/site-info']]]
        );

        $run = $this->loop()->run('keep going', $this->context());

        unset($GLOBALS['sfcore_test_filters']['swishfolio_ai_agent_max_iterations']);

        $this->assertSame(2, $run->getIterations());
    }

    public function test_a_provider_that_cannot_use_tools_is_refused_up_front()
    {
        $this->provider->supportsTools = false;

        $this->expectException(ProviderException::class);

        $this->loop()->run('do something', $this->context());
    }

    public function test_every_registered_tool_is_offered_to_the_model()
    {
        $this->provider->replies = [['text' => 'ok']];

        $this->loop()->run('hello', $this->context());

        $offered = [];

        foreach ($this->provider->conversations[0]->getTools() as $tool) {
            $offered[] = $tool->getName();
        }

        sort($offered);

        $expected = ToolRegistry::withDefaultTools($this->journal, $this->blocks())->names();
        sort($expected);

        $this->assertSame($expected, $offered);
    }

    public function test_the_system_prompt_tells_the_model_to_look_before_acting()
    {
        $this->provider->replies = [['text' => 'ok']];

        $this->loop()->run('hello', $this->context());

        $prompt = $this->provider->conversations[0]->getSystemPrompt();

        $this->assertStringContainsString('swishfolio/site-info', $prompt);
        $this->assertStringContainsString('Never invent an ID', $prompt);
    }

    public function test_brand_context_is_appended_to_the_system_prompt()
    {
        $this->provider->replies = [['text' => 'ok']];

        $this->loop('The site belongs to Acme, a design studio.')->run('hello', $this->context());

        $this->assertStringContainsString(
            'Acme, a design studio',
            $this->provider->conversations[0]->getSystemPrompt()
        );
    }

    public function test_token_usage_accumulates_across_iterations()
    {
        $this->provider->replies = [
            ['calls' => [['id' => 'c1', 'name' => 'swishfolio/site-info']], 'inputTokens' => 100, 'outputTokens' => 20],
            ['text' => 'done', 'inputTokens' => 150, 'outputTokens' => 30],
        ];

        $run = $this->loop()->run('look', $this->context());

        $this->assertSame(250, $run->getInputTokens());
        $this->assertSame(50, $run->getOutputTokens());
    }
}
