<?php
/**
 * Runs an agent turn outside the request that asked for it.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Jobs;

use SwishfolioCore\AI\Agent\AgentLoop;
use SwishfolioCore\AI\Agent\AgentRun;
use SwishfolioCore\AI\Conversation\Message;
use SwishfolioCore\AI\ProviderException;
use SwishfolioCore\Tools\ToolContext;

/**
 * An agent turn makes up to eight provider calls, and a reasoning model can take
 * tens of seconds each. That is the real timeout risk in this plugin — not
 * generation's execution, which is a handful of fast database writes — so this
 * is what gets moved off the request.
 *
 * Progress is written to the store after every step rather than at the end,
 * because a progress record that only appears when the work finishes is not
 * progress.
 *
 * The job is attributed to the user who queued it and runs with their identity
 * restored. Cron has no current user, and a tool authorising against "nobody"
 * would either refuse everything or, worse, be made to refuse nothing.
 */
class AgentJobRunner
{
    const HOOK = 'swishfolio_run_agent_job';

    /** @var JobStoreInterface */
    private $store;

    /** @var callable Returns an AgentLoop for a given model and brand. */
    private $loopFactory;

    /**
     * @param JobStoreInterface $store       Progress storage.
     * @param callable          $loopFactory Builds the loop; injected because it needs a live provider.
     */
    public function __construct(JobStoreInterface $store, $loopFactory)
    {
        $this->store       = $store;
        $this->loopFactory = $loopFactory;
    }

    /**
     * @return void
     */
    public function boot()
    {
        add_action(self::HOOK, [$this, 'run'], 10, 1);
    }

    /**
     * Queues a turn and asks WordPress to run it as soon as it can.
     *
     * @param string $instruction  What the owner asked.
     * @param array  $history      Serialised conversation.
     * @param string $confirmToken Token approving pending calls.
     * @param int    $userId       Owner.
     *
     * @return Job
     */
    public function queue($instruction, array $history, $confirmToken, $userId)
    {
        $job = Job::queued(
            ToolContext::newRunId(),
            $userId,
            '' !== trim((string) $instruction)
                ? (string) $instruction
                : __('Applying the approved change', 'swishfolio-core'),
            [
                'instruction'  => (string) $instruction,
                'history'      => $history,
                'confirmToken' => (string) $confirmToken,
            ]
        );

        $this->store->save($job);

        // Same hooks as BackgroundJobs, so metering follows the job to the
        // worker and bills the tokens the turn really used.
        do_action('swishfolio_ai_job_queued', $job->getId(), 'agent');

        wp_schedule_single_event(time(), self::HOOK, [$job->getId()]);

        // WP-Cron only fires on a later page load, which for a single-user
        // admin session may be minutes away or never. Nudging it means the work
        // usually starts within a second or two instead.
        if (function_exists('spawn_cron')) {
            spawn_cron();
        }

        return $job;
    }

    /**
     * @param string $jobId Job to run.
     *
     * @return void
     */
    public function run($jobId)
    {
        $job = $this->store->find($jobId);

        if (null === $job || $job->getStatus() !== Job::STATUS_QUEUED) {
            // Already running, already finished, or expired. Cron can fire the
            // same event twice, and running a turn twice would double its
            // writes.
            return;
        }

        $payload = $job->getPayload();
        $context = new ToolContext($job->getUserId(), ToolContext::currentSiteId());

        // Cron has no current user. A tool authorising against nobody would
        // refuse everything, so the queuing user's identity is restored for the
        // duration — which also means the capability checks are the real ones.
        $previousUser = function_exists('get_current_user_id') ? get_current_user_id() : 0;

        if (function_exists('wp_set_current_user')) {
            wp_set_current_user($job->getUserId());
        }

        // The instruction, history and confirm token are read above; keeping
        // them on the stored job would only leave the conversation (and a live
        // token) sitting in the options table for the job's lifetime.
        $job = $job->with(['status' => Job::STATUS_RUNNING, 'runId' => $context->getRunId(), 'payload' => []]);

        $this->store->save($job);

        do_action('swishfolio_ai_job_started', $job->getId(), 'agent');

        $status = Job::STATUS_FAILED;

        try {
            $run = $this->loop()->run(
                (string) $payload['instruction'],
                $context,
                $this->messagesFrom(isset($payload['history']) ? $payload['history'] : []),
                (string) $payload['confirmToken']
            );

            $this->store->save(
                $job->with(
                    [
                        'status' => Job::STATUS_DONE,
                        'steps'  => $this->stepsOf($run),
                        'result' => $run->toArray(),
                    ]
                )
            );

            $status = Job::STATUS_DONE;
        } catch (ProviderException $e) {
            $this->store->save(
                $job->with(
                    [
                        'status' => Job::STATUS_FAILED,
                        'error'  => [
                            'code'    => 'sfcore_ai_provider_error',
                            'message' => __('The AI could not answer.', 'swishfolio-core'),
                            'detail'  => $e->getMessage(),
                        ],
                    ]
                )
            );
        } catch (\Throwable $e) {
            // A job that dies silently leaves a spinner turning forever, so
            // anything unexpected is recorded as a failure the client can read.
            $this->store->save(
                $job->with(
                    [
                        'status' => Job::STATUS_FAILED,
                        'error'  => [
                            'code'    => 'sfcore_job_failed',
                            'message' => __('That request stopped unexpectedly.', 'swishfolio-core'),
                            'detail'  => $e->getMessage(),
                        ],
                    ]
                )
            );
        } finally {
            do_action('swishfolio_ai_job_finished', $job->getId(), 'agent', $status);

            if (function_exists('wp_set_current_user')) {
                wp_set_current_user($previousUser);
            }
        }
    }

    /**
     * @param AgentRun $run Finished run.
     *
     * @return array[]
     */
    private function stepsOf(AgentRun $run)
    {
        $steps = [];

        foreach ($run->getSteps() as $step) {
            $steps[] = $step->toArray();
        }

        return $steps;
    }

    /**
     * @param array $history Serialised conversation.
     *
     * @return Message[]
     */
    private function messagesFrom($history)
    {
        $messages = [];

        foreach ((array) $history as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $message = Message::fromArray($entry);

            if (null !== $message) {
                $messages[] = $message;
            }
        }

        return $messages;
    }

    /**
     * @return AgentLoop
     */
    private function loop()
    {
        return call_user_func($this->loopFactory);
    }
}
