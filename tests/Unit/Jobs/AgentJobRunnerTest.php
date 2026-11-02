<?php

namespace SwishfolioCore\Tests\Unit\Jobs;

use SwishfolioCore\AI\Agent\AgentLoop;
use SwishfolioCore\AI\Prompt\PromptManager;
use SwishfolioCore\AI\ProviderException;
use SwishfolioCore\Jobs\AgentJobRunner;
use SwishfolioCore\Jobs\Job;
use SwishfolioCore\Tests\Support\InMemoryJobStore;
use SwishfolioCore\Tests\Unit\AI\FakeProvider;
use SwishfolioCore\Tests\Unit\Tools\ToolsTestCase;
use SwishfolioCore\Tools\ToolRegistry;
use SwishfolioCore\Tools\ToolRunner;

class AgentJobRunnerTest extends ToolsTestCase
{
    /** @var InMemoryJobStore */
    private $store;

    /** @var FakeProvider */
    private $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store    = new InMemoryJobStore();
        $this->provider = new FakeProvider();
    }

    private function jobRunner()
    {
        $registry = ToolRegistry::withDefaultTools($this->journal, $this->blocks());
        $provider = $this->provider;
        $toolRunner = new ToolRunner($registry, $this->journal);

        return new AgentJobRunner(
            $this->store,
            static function () use ($provider, $toolRunner) {
                return new AgentLoop(
                    $provider,
                    $toolRunner,
                    new PromptManager(SFCORE_PLUGIN_DIR . 'src/AI/Prompt'),
                    'fake-model'
                );
            }
        );
    }

    public function test_queuing_stores_the_job_before_any_work_starts()
    {
        $job = $this->jobRunner()->queue('rewrite my about page', [], '', 7);

        $this->assertSame(Job::STATUS_QUEUED, $job->getStatus());
        $this->assertSame(7, $job->getUserId());
        $this->assertSame('rewrite my about page', $job->getLabel());
        $this->assertNotNull($this->store->find($job->getId()));
        $this->assertSame([], $this->provider->conversations, 'Queuing must not call the provider.');
    }

    public function test_an_approval_only_turn_gets_a_label_of_its_own()
    {
        $job = $this->jobRunner()->queue('', [], 'token', 7);

        $this->assertNotSame('', $job->getLabel());
    }

    public function test_running_moves_the_job_through_running_to_done()
    {
        $this->provider->replies = [['text' => 'All done.']];

        $runner = $this->jobRunner();
        $job    = $runner->queue('hello', [], '', 7);

        $runner->run($job->getId());

        $this->assertSame(
            [Job::STATUS_QUEUED, Job::STATUS_RUNNING, Job::STATUS_DONE],
            $this->store->statusTrail(),
            'Running must be visible while it runs, not only at the end.'
        );

        $finished = $this->store->find($job->getId());

        $this->assertTrue($finished->isFinished());
        $this->assertSame('All done.', $finished->getResult()['message']);
    }

    public function test_the_finished_job_carries_the_steps_for_the_client()
    {
        $this->givenPost(10, ['post_title' => 'Home']);

        $this->provider->replies = [
            ['calls' => [['id' => 'c1', 'name' => 'swishfolio/site-info']]],
            ['text' => 'Your site is Acme Studio.'],
        ];

        $runner = $this->jobRunner();
        $job    = $runner->queue('what is my site called?', [], '', 7);

        $runner->run($job->getId());

        $finished = $this->store->find($job->getId());

        $this->assertCount(1, $finished->getSteps());
        $this->assertSame('swishfolio/site-info', $finished->getSteps()[0]['tool']);
    }

    public function test_a_provider_failure_is_recorded_rather_than_leaving_a_spinner_turning()
    {
        $this->provider->throw = new ProviderException('upstream exploded', 500);

        $runner = $this->jobRunner();
        $job    = $runner->queue('hello', [], '', 7);

        $runner->run($job->getId());

        $finished = $this->store->find($job->getId());

        $this->assertSame(Job::STATUS_FAILED, $finished->getStatus());
        $this->assertSame('sfcore_ai_provider_error', $finished->getError()['code']);
        $this->assertTrue($finished->isFinished());
    }

    public function test_an_unexpected_error_is_also_recorded_as_a_failure()
    {
        $this->provider->throw = new \RuntimeException('something nobody predicted');

        $runner = $this->jobRunner();
        $job    = $runner->queue('hello', [], '', 7);

        $runner->run($job->getId());

        $finished = $this->store->find($job->getId());

        $this->assertSame(Job::STATUS_FAILED, $finished->getStatus());
        $this->assertSame('sfcore_job_failed', $finished->getError()['code']);
    }

    public function test_running_the_same_job_twice_does_not_do_the_work_twice()
    {
        // WP-Cron can fire the same scheduled event more than once, and a turn
        // run twice doubles its writes.
        $this->provider->replies = [['text' => 'done'], ['text' => 'done again']];

        $runner = $this->jobRunner();
        $job    = $runner->queue('hello', [], '', 7);

        $runner->run($job->getId());
        $runner->run($job->getId());

        $this->assertCount(1, $this->provider->conversations);
    }

    public function test_running_a_job_that_has_expired_is_a_no_op()
    {
        $this->jobRunner()->run('nosuchjob');

        $this->assertSame([], $this->store->jobs);
    }

    public function test_the_turn_runs_as_the_user_who_queued_it()
    {
        // Cron has no current user, so without restoring one every tool would
        // authorise against nobody.
        $GLOBALS['sfcore_test_wp']['userId'] = 0;

        $this->provider->replies = [['text' => 'ok']];

        $runner = $this->jobRunner();
        $job    = $runner->queue('hello', [], '', 7);

        $runner->run($job->getId());

        $this->assertSame(
            Job::STATUS_DONE,
            $this->store->find($job->getId())->getStatus()
        );
    }

    public function test_the_status_payload_withholds_the_conversation_history()
    {
        $job = $this->jobRunner()->queue('hello', [ ['role' => 'user', 'text' => 'earlier'] ], '', 7);

        $status = $job->toStatusArray();

        $this->assertArrayNotHasKey('payload', $status, 'The client already has the history.');
        $this->assertArrayHasKey('status', $status);
        $this->assertArrayHasKey('jobId', $status);
    }
}
