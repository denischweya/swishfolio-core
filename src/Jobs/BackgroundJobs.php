<?php
/**
 * Runs long AI requests after the HTTP response has been sent.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Jobs;

use SwishfolioCore\Tools\ToolContext;

/**
 * A prompt that writes a whole page can take well over a minute, and a typical
 * nginx + PHP-FPM host cuts a request at 60 seconds. So the route that starts the
 * work queues a job and answers at once with its ID; the browser then polls
 * /ai/jobs/<id> and renders the job's steps as a live progress log.
 *
 * Where it can (PHP-FPM, LiteSpeed) the job runs in the same PHP process right
 * after the response has been flushed and the connection closed, which needs no
 * cron and no loopback request. A cron event is still scheduled a little later
 * as a fallback, and it is the only path on servers without a finish-request
 * function. The status check at the top of run() makes the two paths safe to
 * overlap: whichever starts first owns the job.
 *
 * Handlers are registered per kind on every request, because the cron fallback
 * runs in a fresh request that only has the job ID.
 */
class BackgroundJobs
{
    const HOOK           = 'swishfolio_run_background_job';
    const FALLBACK_DELAY = 45;
    const TIME_LIMIT     = 600;

    /**
     * Seconds a job may sit queued before the poll route calls it lost: the
     * in-process run starts at once and the cron fallback after FALLBACK_DELAY,
     * so this only trips when both failed (no finish-request, WP-Cron off).
     */
    const STALE_QUEUED = 180;

    /**
     * Seconds a running job may go without writing progress before the poll
     * route calls it dead (worker fatal, process killed). Every provider call
     * writes a step when it starts, and the longest single call allowed is a
     * site plan (GenerationController::PLAN_TIMEOUT, 480s), so a live job
     * always writes well inside this.
     */
    const STALE_RUNNING = 600;

    /** @var JobStoreInterface */
    private $store;

    /** @var callable[] kind => handler(array $payload, JobProgress $progress): array|\WP_Error */
    private $handlers = [];

    /** @var string[] Jobs to run at shutdown. */
    private $deferred = [];

    /**
     * @param JobStoreInterface $store Progress storage, shared with the poll route.
     */
    public function __construct(JobStoreInterface $store)
    {
        $this->store = $store;
    }

    /**
     * @return void
     */
    public function boot()
    {
        add_action(self::HOOK, [$this, 'run'], 10, 1);
    }

    /**
     * @param string   $kind    Job kind.
     * @param callable $handler Receives the payload and a JobProgress; returns the result array or a WP_Error.
     *
     * @return void
     */
    public function register($kind, $handler)
    {
        $this->handlers[(string) $kind] = $handler;
    }

    /**
     * @return JobStoreInterface
     */
    public function store()
    {
        return $this->store;
    }

    /**
     * Queue a job and arrange for it to run as soon as this response is sent.
     *
     * @param string $kind    Registered kind.
     * @param string $label   What the job is, for the user.
     * @param array  $payload Everything the handler needs.
     * @param int    $userId  Owner; the job runs as this user.
     * @param array  $steps   Steps to show straight away (already finished work).
     *
     * @return Job
     */
    public function queue($kind, $label, array $payload, $userId, array $steps = [])
    {
        $job = Job::queued(ToolContext::newRunId(), $userId, $label, ['kind' => (string) $kind, 'data' => $payload]);

        if ($steps !== []) {
            $job = $job->with(['steps' => $steps]);
        }

        $this->store->save($job);

        /**
         * Fires inside the request that queued a job, before the response is
         * sent. A hosting layer that meters AI usage per request can carry its
         * reservation over to the job here.
         *
         * @param string $jobId Job identifier.
         * @param string $kind  Job kind.
         */
        do_action('swishfolio_ai_job_queued', $job->getId(), (string) $kind);

        if ($this->canFinishRequest()) {
            $this->deferred[] = $job->getId();

            if (count($this->deferred) === 1) {
                // After wp_ob_end_flush_all (priority 1) has emptied the buffers.
                add_action('shutdown', [$this, 'runDeferred'], 100);
            }

            wp_schedule_single_event(time() + self::FALLBACK_DELAY, self::HOOK, [$job->getId()]);
        } else {
            wp_schedule_single_event(time(), self::HOOK, [$job->getId()]);

            if (function_exists('spawn_cron')) {
                spawn_cron();
            }
        }

        return $job;
    }

    /**
     * Shutdown: hand the response to the client, then do the work.
     *
     * @return void
     */
    public function runDeferred()
    {
        $ids            = $this->deferred;
        $this->deferred = [];

        if ($ids === []) {
            return;
        }

        while (ob_get_level() > 0) {
            @ob_end_flush(); // phpcs:ignore WordPress.PHP.NoSilencedErrors
        }

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } elseif (function_exists('litespeed_finish_request')) {
            litespeed_finish_request();
        }

        foreach ($ids as $id) {
            $this->run($id);
        }
    }

    /**
     * Run one job. Safe to call twice: only a queued job starts.
     *
     * @param string $jobId Job identifier.
     *
     * @return void
     */
    public function run($jobId)
    {
        $job = $this->store->find((string) $jobId);

        if ($job === null || $job->getStatus() !== Job::STATUS_QUEUED) {
            return;
        }

        $payload = $job->getPayload();
        $kind    = isset($payload['kind']) ? (string) $payload['kind'] : '';
        $data    = isset($payload['data']) ? (array) $payload['data'] : [];

        // The payload can be large (a whole page of markup); it is not needed
        // again once read, and every progress write would carry it otherwise.
        $job = $job->with([
            'status'    => Job::STATUS_RUNNING,
            'payload'   => ['kind' => $kind],
            'startedAt' => microtime(true),
        ]);
        $this->store->save($job);

        ignore_user_abort(true);

        if (function_exists('set_time_limit')) {
            @set_time_limit(self::TIME_LIMIT); // phpcs:ignore WordPress.PHP.NoSilencedErrors
        }

        $previousUser = get_current_user_id();

        if ($previousUser !== $job->getUserId()) {
            wp_set_current_user($job->getUserId());
        }

        $progress = new JobProgress($this->store, $job);
        $status   = Job::STATUS_FAILED;

        /**
         * Fires in the worker before a job's provider calls.
         *
         * @param string $jobId Job identifier.
         * @param string $kind  Job kind.
         */
        do_action('swishfolio_ai_job_started', $job->getId(), $kind);

        try {
            if (! isset($this->handlers[$kind])) {
                throw new \RuntimeException(sprintf('No handler for job kind "%s".', $kind));
            }

            $result = call_user_func($this->handlers[$kind], $data, $progress);

            if (is_wp_error($result)) {
                $progress->fail($result->get_error_message());
                $this->store->save($progress->job()->with([
                    'status'     => Job::STATUS_FAILED,
                    'error'      => $this->errorArray($result),
                    'finishedAt' => microtime(true),
                ]));
            } else {
                $progress->done();
                $status = Job::STATUS_DONE;
                $this->store->save($progress->job()->with([
                    'status'     => Job::STATUS_DONE,
                    'result'     => $result,
                    'finishedAt' => microtime(true),
                ]));
            }
        } catch (\Throwable $e) {
            // A job that dies silently leaves a spinner turning forever.
            $progress->fail(__('Stopped unexpectedly.', 'swishfolio-core'));
            $this->store->save($progress->job()->with([
                'status'     => Job::STATUS_FAILED,
                'finishedAt' => microtime(true),
                'error'      => [
                    'code'    => 'sfcore_job_failed',
                    'message' => __('That request stopped unexpectedly. Nothing was changed.', 'swishfolio-core'),
                    'detail'  => $e->getMessage(),
                ],
            ]));
        } finally {
            /**
             * Fires in the worker after a job settles.
             *
             * @param string $jobId  Job identifier.
             * @param string $kind   Job kind.
             * @param string $status "done" or "failed".
             */
            do_action('swishfolio_ai_job_finished', $job->getId(), $kind, $status);

            if ($previousUser !== $job->getUserId()) {
                wp_set_current_user($previousUser);
            }
        }
    }

    /**
     * Settle a job whose worker is gone, so a poller is told instead of
     * waiting forever. Called by the poll route on every read; returns the job
     * unchanged unless it is queued or running and has not been touched for
     * longer than the stale window.
     *
     * A failed job lets the client offer Retry, and the
     * swishfolio_ai_job_abandoned action lets a metering layer refund what it
     * reserved for the job (the worker never reported usage).
     *
     * @param JobStoreInterface $store Job storage.
     * @param Job               $job   Job just read.
     * @param int|null          $now   Unix time (tests).
     *
     * @return Job
     */
    public static function reapIfStalled(JobStoreInterface $store, Job $job, $now = null)
    {
        $now     = $now === null ? time() : (int) $now;
        $data    = $job->toArray();
        $updated = (int) $data['updatedAt'];
        $status  = $job->getStatus();

        if ($status === Job::STATUS_QUEUED) {
            $limit = self::STALE_QUEUED;
        } elseif ($status === Job::STATUS_RUNNING) {
            $limit = self::STALE_RUNNING;
        } else {
            return $job;
        }

        if ($updated <= 0 || ($now - $updated) <= $limit) {
            return $job;
        }

        $steps = $job->getSteps();

        foreach ($steps as $i => $step) {
            if (isset($step['status']) && in_array($step['status'], ['active', 'queued'], true)) {
                $steps[$i]['status'] = 'failed';
            }
        }

        $failed = $job->with([
            'status'     => Job::STATUS_FAILED,
            'steps'      => $steps,
            'finishedAt' => (float) $now,
            'error'  => [
                'code'    => 'sfcore_job_stalled',
                'message' => $status === Job::STATUS_QUEUED
                    ? __('That request never started. Nothing was changed, and you can try again.', 'swishfolio-core')
                    : __('That request stopped responding. You can try again.', 'swishfolio-core'),
                'detail'  => sprintf('No progress for %d seconds while %s.', $now - $updated, $status),
                'data'    => ['status' => 504],
            ],
        ]);
        $store->save($failed);

        $payload = $job->getPayload();

        /**
         * Fires when the poll route gives up on a job whose worker is gone.
         * The worker never reported usage, so a metering layer should release
         * whatever it reserved for the job.
         *
         * @param string $jobId Job identifier.
         * @param string $kind  Job kind ('' when unknown).
         * @param string $was   Status before: "queued" or "running".
         */
        do_action('swishfolio_ai_job_abandoned', $job->getId(), isset($payload['kind']) ? (string) $payload['kind'] : '', $status);

        return $failed;
    }

    /**
     * @param \WP_Error $error Failure.
     *
     * @return array
     */
    private function errorArray(\WP_Error $error)
    {
        $data = (array) $error->get_error_data();

        return [
            'code'    => (string) $error->get_error_code(),
            'message' => (string) $error->get_error_message(),
            'detail'  => isset($data['detail']) ? (string) $data['detail'] : '',
            'data'    => $data,
        ];
    }

    /**
     * @return bool
     */
    private function canFinishRequest()
    {
        $can = function_exists('fastcgi_finish_request') || function_exists('litespeed_finish_request');

        /**
         * Filters whether jobs run in-process after the response is flushed
         * (true) or only through WP-Cron (false).
         *
         * @param bool $can Whether a finish-request function exists.
         */
        return (bool) apply_filters('swishfolio_jobs_run_after_response', $can && PHP_SAPI !== 'cli');
    }
}
