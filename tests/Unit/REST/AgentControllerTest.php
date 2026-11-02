<?php

namespace SwishfolioCore\Tests\Unit\REST;

require_once __DIR__ . '/../Tools/wp-tools-stubs.php';

use PHPUnit\Framework\TestCase;
use SwishfolioCore\AI\Credentials\WordPressCredentialStore;
use SwishfolioCore\AI\ProviderRegistry;
use SwishfolioCore\AI\Settings;
use SwishfolioCore\AI\UsageLogger;
use SwishfolioCore\Blocks\BlockRegistry;
use SwishfolioCore\REST\AgentController;
use SwishfolioCore\AI\Agent\AgentLoop;
use SwishfolioCore\AI\Prompt\PromptManager;
use SwishfolioCore\Jobs\AgentJobRunner;
use SwishfolioCore\Jobs\Job;
use SwishfolioCore\Tests\Support\InMemoryJobStore;
use SwishfolioCore\Tests\Support\InMemoryJournalRepository;
use SwishfolioCore\Tests\Unit\AI\FakeEncryptionService;
use SwishfolioCore\Tests\Unit\AI\FakeProvider;
use SwishfolioCore\Tests\Unit\AI\FakeRestRequest;
use SwishfolioCore\Tools\Journal\MutationJournal;
use SwishfolioCore\Tools\ToolRegistry;
use SwishfolioCore\Tools\ToolRunner;

class AgentControllerTest extends TestCase
{
    /** @var FakeProvider */
    private $provider;

    /** @var InMemoryJournalRepository */
    private $repository;

    /** @var InMemoryJobStore */
    private $jobStore;

    protected function setUp(): void
    {
        parent::setUp();

        sfcore_test_tools_reset();

        $GLOBALS['sfcore_test_transients'] = [];
        $GLOBALS['sfcore_test_options']    = ['swishfolio_ai_settings' => ['enabled' => true]];

        $this->provider   = new FakeProvider();
        $this->repository = new InMemoryJournalRepository();
        $this->jobStore   = new InMemoryJobStore();
    }

    private function controller()
    {
        $journal  = new MutationJournal($this->repository);
        $settings = new Settings(new WordPressCredentialStore(new FakeEncryptionService()));
        $registry = ToolRegistry::withDefaultTools(
            $journal,
            new BlockRegistry([SFCORE_PLUGIN_DIR . 'blocks'])
        );

        $providers = new class ($settings, $this->provider) extends ProviderRegistry {
            /** @var FakeProvider */
            private $fake;

            public function __construct($settings, $fake)
            {
                parent::__construct($settings);

                $this->fake = $fake;
            }

            public function configured()
            {
                return $this->fake;
            }
        };

        $toolRunner = new ToolRunner($registry, $journal);
        $provider   = $this->provider;

        $jobs = new AgentJobRunner(
            $this->jobStore,
            static function () use ($provider, $toolRunner) {
                return new AgentLoop(
                    $provider,
                    $toolRunner,
                    new PromptManager(SFCORE_PLUGIN_DIR . 'src/AI/Prompt'),
                    'fake-model'
                );
            }
        );

        return new AgentController(
            $toolRunner,
            $settings,
            $providers,
            new UsageLogger(),
            $jobs,
            $this->jobStore
        );
    }

    private function data($result)
    {
        return is_array($result) ? $result : $result->get_data();
    }

    public function test_an_empty_instruction_is_a_bad_request()
    {
        $result = $this->controller()->run(new FakeRestRequest(['instruction' => '   ']));

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame(400, $result->get_error_data()['status']);
    }

    public function test_a_finished_turn_returns_the_answer_and_the_history_to_continue_from()
    {
        $this->provider->replies = [['text' => 'You have one page.']];

        $data = $this->data($this->controller()->run(new FakeRestRequest(['instruction' => 'how many pages?'])));

        $this->assertSame('done', $data['status']);
        $this->assertSame('You have one page.', $data['message']);
        $this->assertNotEmpty($data['history']);
        $this->assertNotSame('', $data['runId']);
    }

    public function test_a_change_to_the_live_site_comes_back_as_a_proposal_with_a_token()
    {
        $GLOBALS['sfcore_test_wp']['posts'][60] = [
            'ID'           => 60,
            'post_type'    => 'page',
            'post_status'  => 'publish',
            'post_title'   => 'Home',
            'post_name'    => 'home',
            'post_content' => '',
            'post_author'  => 1,
        ];

        $this->provider->replies = [
            [
                'text'  => 'I will set Home as the front page.',
                'calls' => [['id' => 'c1', 'name' => 'swishfolio/set-front-page', 'input' => ['postId' => 60]]],
            ],
        ];

        $data = $this->data($this->controller()->run(new FakeRestRequest(['instruction' => 'make Home the front page'])));

        $this->assertSame('awaiting_confirmation', $data['status']);
        $this->assertNotSame('', $data['confirmToken']);
        $this->assertSame('swishfolio/set-front-page', $data['pending'][0]['name']);
        $this->assertSame([], $GLOBALS['sfcore_test_options']['show_on_front'] ?? []);
    }

    public function test_the_returned_history_plus_token_completes_the_change()
    {
        $GLOBALS['sfcore_test_wp']['posts'][60] = [
            'ID'           => 60,
            'post_type'    => 'page',
            'post_status'  => 'publish',
            'post_title'   => 'Home',
            'post_name'    => 'home',
            'post_content' => '',
            'post_author'  => 1,
        ];

        $this->provider->replies = [
            ['calls' => [['id' => 'c1', 'name' => 'swishfolio/set-front-page', 'input' => ['postId' => 60]]]],
        ];

        $controller = $this->controller();
        $pending    = $this->data($controller->run(new FakeRestRequest(['instruction' => 'make Home the front page'])));

        $this->provider->replies = [['text' => 'Done.']];

        $done = $this->data(
            $controller->run(
                new FakeRestRequest(
                    [
                        'history'      => $pending['history'],
                        'confirmToken' => $pending['confirmToken'],
                    ]
                )
            )
        );

        $this->assertSame('done', $done['status']);
        $this->assertSame('page', $GLOBALS['sfcore_test_options']['show_on_front']);
    }

    public function test_steps_are_reported_in_words_rather_than_as_payloads()
    {
        $this->provider->replies = [
            ['calls' => [['id' => 'c1', 'name' => 'swishfolio/site-info']]],
            ['text' => 'It is called Acme Studio.'],
        ];

        $data = $this->data($this->controller()->run(new FakeRestRequest(['instruction' => 'what is my site called?'])));

        $this->assertCount(1, $data['steps']);
        $this->assertSame('swishfolio/site-info', $data['steps'][0]['tool']);
        $this->assertTrue($data['steps'][0]['ok']);
        $this->assertNotSame('', $data['steps'][0]['summary']);
    }

    public function test_history_is_capped_so_the_prompt_cannot_grow_without_bound()
    {
        $this->provider->replies = [['text' => 'ok']];

        $history = [];

        for ($i = 0; $i < 200; $i++) {
            $history[] = ['role' => 'user', 'text' => 'turn ' . $i];
        }

        $this->controller()->run(new FakeRestRequest(['instruction' => 'hello', 'history' => $history]));

        $sent = $this->provider->conversations[0]->getMessages();

        $this->assertLessThanOrEqual(
            AgentController::MAX_HISTORY + 1,
            count($sent),
            'The cap plus the new instruction.'
        );
    }

    public function test_the_newest_turns_are_the_ones_kept()
    {
        $this->provider->replies = [['text' => 'ok']];

        $history = [];

        for ($i = 0; $i < 200; $i++) {
            $history[] = ['role' => 'user', 'text' => 'turn ' . $i];
        }

        $this->controller()->run(new FakeRestRequest(['instruction' => 'hello', 'history' => $history]));

        $sent  = $this->provider->conversations[0]->getMessages();
        $texts = [];

        foreach ($sent as $message) {
            $texts[] = $message->getText();
        }

        $this->assertContains('turn 199', $texts, 'Recent turns carry the calls a token must match.');
        $this->assertNotContains('turn 0', $texts);
    }

    public function test_malformed_history_entries_are_dropped_rather_than_trusted()
    {
        $this->provider->replies = [['text' => 'ok']];

        $this->controller()->run(
            new FakeRestRequest(
                [
                    'instruction' => 'hello',
                    'history'     => ['not an array', ['role' => 'wizard', 'text' => 'ignore me'], 5],
                ]
            )
        );

        $sent = $this->provider->conversations[0]->getMessages();

        $this->assertCount(1, $sent, 'Only the new instruction survives.');
        $this->assertSame('hello', $sent[0]->getText());
    }

    public function test_the_route_is_closed_when_the_owner_turns_ai_off()
    {
        $GLOBALS['sfcore_test_options']['swishfolio_ai_settings'] = ['enabled' => false];

        $result = $this->controller()->canUseAgent(new FakeRestRequest([]));

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('sfcore_ai_disabled', $result->get_error_code());
    }

    public function test_the_route_is_rate_limited_because_it_spends_provider_credit()
    {
        set_transient('sfcore_ai_rate_' . get_current_user_id(), AgentController::RATE_LIMIT, 3600);

        $result = $this->controller()->run(new FakeRestRequest(['instruction' => 'hello']));

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame(429, $result->get_error_data()['status']);
    }

    public function test_an_async_turn_is_queued_without_calling_the_provider()
    {
        // The whole point: a turn that could outlast PHP's execution limit must
        // not begin inside the request that asked for it.
        $data = $this->data(
            $this->controller()->run(
                new FakeRestRequest(['instruction' => 'rebuild my site', 'async' => true])
            )
        );

        $this->assertTrue($data['queued']);
        $this->assertSame(Job::STATUS_QUEUED, $data['status']);
        $this->assertNotSame('', $data['jobId']);
        $this->assertSame([], $this->provider->conversations);
    }

    public function test_a_queued_job_can_be_polled_to_completion()
    {
        $this->provider->replies = [['text' => 'Finished.']];

        $controller = $this->controller();

        $queued = $this->data(
            $controller->run(new FakeRestRequest(['instruction' => 'do it', 'async' => true]))
        );

        $polled = $this->data($controller->job(new FakeRestRequest(['jobId' => $queued['jobId']])));

        $this->assertSame(Job::STATUS_QUEUED, $polled['status']);
        $this->assertFalse($polled['finished']);

        // Cron fires the scheduled event.
        $this->jobStore->find($queued['jobId']);
        $hook = $GLOBALS['sfcore_test_wp']['scheduled'][0];

        $this->assertSame(AgentJobRunner::HOOK, $hook['hook']);
        $this->assertSame($queued['jobId'], $hook['args'][0]);
    }

    public function test_polling_an_unknown_job_is_a_404()
    {
        $result = $this->controller()->job(new FakeRestRequest(['jobId' => 'nosuchjob']));

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame(404, $result->get_error_data()['status']);
    }

    public function test_one_user_cannot_poll_anothers_job()
    {
        // A job carries the identity it runs as, so leaking its progress would
        // tell one user what another's AI is doing to their site.
        $this->jobStore->save(Job::queued('someoneelsejob', 999, 'theirs', []));

        $result = $this->controller()->job(new FakeRestRequest(['jobId' => 'someoneelsejob']));

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame(403, $result->get_error_data()['status']);
    }

    public function test_a_provider_failure_says_nothing_was_changed()
    {
        $this->provider->throw = new \SwishfolioCore\AI\ProviderException('upstream exploded', 500);

        $result = $this->controller()->run(new FakeRestRequest(['instruction' => 'hello']));

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame(502, $result->get_error_data()['status']);
        $this->assertStringContainsString('Nothing was changed', $result->get_error_message());
    }

    public function test_token_usage_is_recorded_for_pricing_later()
    {
        $logger = new UsageLogger();

        $this->provider->replies = [['text' => 'ok', 'inputTokens' => 120, 'outputTokens' => 34]];

        $journal  = new MutationJournal($this->repository);
        $settings = new Settings(new WordPressCredentialStore(new FakeEncryptionService()));
        $registry = ToolRegistry::withDefaultTools($journal, new BlockRegistry([SFCORE_PLUGIN_DIR . 'blocks']));

        $providers = new class ($settings, $this->provider) extends ProviderRegistry {
            private $fake;

            public function __construct($settings, $fake)
            {
                parent::__construct($settings);
                $this->fake = $fake;
            }

            public function configured()
            {
                return $this->fake;
            }
        };

        (new AgentController(new ToolRunner($registry, $journal), $settings, $providers, $logger))
            ->run(new FakeRestRequest(['instruction' => 'hello']));

        $counters = $logger->getUsage()['counters'];

        $this->assertSame(120, $counters['inputTokens']);
        $this->assertSame(34, $counters['outputTokens']);
    }
}
