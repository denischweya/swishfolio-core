<?php

namespace SwishfolioCore\Tests\Unit\Jobs;

use PHPUnit\Framework\TestCase;
use SwishfolioCore\AI\Providers\AbstractAIProvider;
use SwishfolioCore\Jobs\BackgroundJobs;
use SwishfolioCore\Jobs\Job;
use SwishfolioCore\Tests\Support\InMemoryJobStore;

/**
 * A job whose worker is gone must end as "failed" so the onboarding wizard
 * stops showing "Building your site" and offers Retry.
 */
class StalledJobTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['sfcore_test_actions'] = [];
    }

    private function job($status, $updatedAt, array $steps = [])
    {
        return new Job([
            'id'        => 'abc',
            'userId'    => 1,
            'status'    => $status,
            'payload'   => ['kind' => 'plan'],
            'steps'     => $steps,
            'createdAt' => $updatedAt,
            'updatedAt' => $updatedAt,
        ]);
    }

    private function abandoned()
    {
        return array_values(array_filter($GLOBALS['sfcore_test_actions'], static function ($a) {
            return $a[0] === 'swishfolio_ai_job_abandoned';
        }));
    }

    public function test_a_running_job_that_still_writes_progress_is_left_alone()
    {
        $store = new InMemoryJobStore();
        $job   = $this->job(Job::STATUS_RUNNING, 1000);

        $out = BackgroundJobs::reapIfStalled($store, $job, 1000 + BackgroundJobs::STALE_RUNNING);

        $this->assertSame(Job::STATUS_RUNNING, $out->getStatus());
        $this->assertSame([], $this->abandoned());
    }

    public function test_a_running_job_gone_quiet_fails_with_retryable_error_and_releases_credits()
    {
        $store = new InMemoryJobStore();
        $job   = $this->job(Job::STATUS_RUNNING, 1000, [
            ['key' => 'read', 'label' => 'Reading', 'status' => 'done'],
            ['key' => 'ask', 'label' => 'Asking', 'status' => 'active'],
        ]);

        $out = BackgroundJobs::reapIfStalled($store, $job, 1001 + BackgroundJobs::STALE_RUNNING);

        $this->assertSame(Job::STATUS_FAILED, $out->getStatus());
        $this->assertSame('sfcore_job_stalled', $out->getError()['code']);
        $this->assertSame('failed', $out->getSteps()[1]['status']);
        $this->assertSame('done', $out->getSteps()[0]['status']);
        $this->assertSame(Job::STATUS_FAILED, $store->find('abc')->getStatus(), 'The failure is saved for the next poll.');
        $this->assertSame([['swishfolio_ai_job_abandoned', ['abc', 'plan', 'running']]], $this->abandoned());
    }

    public function test_a_job_that_never_started_fails_sooner()
    {
        $store = new InMemoryJobStore();

        $out = BackgroundJobs::reapIfStalled($store, $this->job(Job::STATUS_QUEUED, 1000), 1001 + BackgroundJobs::STALE_QUEUED);

        $this->assertSame(Job::STATUS_FAILED, $out->getStatus());
        $this->assertCount(1, $this->abandoned());
    }

    public function test_finished_jobs_are_never_touched()
    {
        $store = new InMemoryJobStore();

        foreach ([Job::STATUS_DONE, Job::STATUS_FAILED] as $status) {
            $out = BackgroundJobs::reapIfStalled($store, $this->job($status, 1), 999999);
            $this->assertSame($status, $out->getStatus());
        }

        $this->assertSame([], $this->abandoned());
    }

    public function test_the_stale_window_outlasts_the_longest_provider_call()
    {
        $this->assertGreaterThan(
            \SwishfolioCore\REST\GenerationController::PLAN_TIMEOUT,
            BackgroundJobs::STALE_RUNNING
        );
    }

    public function test_a_reply_timeout_is_told_apart_from_a_connection_failure()
    {
        $this->assertTrue(AbstractAIProvider::isReplyTimeout('cURL error 28: Operation timed out after 300000 milliseconds with 79027 bytes received'));
        $this->assertTrue(AbstractAIProvider::isReplyTimeout('cURL error 28: Operation timed out after 120001 milliseconds with 0 bytes received'));
        $this->assertFalse(AbstractAIProvider::isReplyTimeout('cURL error 28: Connection timed out after 10001 milliseconds'));
        $this->assertFalse(AbstractAIProvider::isReplyTimeout('cURL error 7: Failed to connect'));
    }
}
