<?php
/**
 * Step-by-step progress for one running job.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Jobs;

/**
 * What a polling client renders as a running log: an ordered list of steps,
 * each one active, done or failed, with a label, an optional detail line and
 * timings measured on the server.
 *
 * Starting a step finishes whichever step was active, so callers only ever say
 * what is happening now. Step changes are written straight away; detail updates
 * (which can arrive many times a second while a model streams) are throttled,
 * and the next step change or flush writes whatever was held back.
 */
class JobProgress
{
    const DETAIL_INTERVAL = 0.4;

    /** @var JobStoreInterface */
    private $store;

    /** @var Job */
    private $job;

    /** @var array[] */
    private $steps = [];

    /** @var float */
    private $startedAt;

    /** @var float */
    private $lastSave = 0.0;

    /** @var bool */
    private $dirty = false;

    /**
     * @param JobStoreInterface|null $store Where progress is written; null records in memory only.
     * @param Job|null               $job   The running job.
     */
    public function __construct(JobStoreInterface $store = null, Job $job = null)
    {
        $this->store     = $store;
        $this->job       = $job;
        $this->startedAt = microtime(true);

        if ($job !== null) {
            $data = $job->toArray();

            if (! empty($data['startedAt'])) {
                $this->startedAt = (float) $data['startedAt'];
            }

            $this->steps = array_values((array) $job->getSteps());
        }
    }

    /**
     * Progress that goes nowhere, for synchronous requests.
     *
     * @return self
     */
    public static function silent()
    {
        return new self(null, null);
    }

    /**
     * Start a step (or re-open one with the same key), finishing the active one.
     *
     * @param string $key    Stable identifier.
     * @param string $label  What is happening, for the user.
     * @param string $detail Optional second line.
     *
     * @return void
     */
    public function step($key, $label, $detail = '')
    {
        $now = $this->now();

        foreach ($this->steps as $i => $step) {
            if ($step['status'] === 'active' && $step['key'] !== $key) {
                $this->steps[$i]['status'] = 'done';
                $this->steps[$i]['d']      = round($now - $step['t'], 1);
            }
        }

        $index = $this->indexOf($key);
        $entry = [
            'key'    => (string) $key,
            'label'  => (string) $label,
            'detail' => (string) $detail,
            'status' => 'active',
            't'      => $now,
            'd'      => 0,
        ];

        if ($index === null) {
            $this->steps[] = $entry;
        } else {
            $entry['t']            = $this->steps[$index]['t'];
            $this->steps[$index] = $entry;
        }

        $this->save(true);
    }

    /**
     * Start a step that runs alongside others (one page of a staged site
     * plan), leaving every other active step running. Re-opening a step keeps
     * its original start time, so a retried page shows its whole duration.
     *
     * @param string $key    Stable identifier.
     * @param string $label  What is happening, for the user.
     * @param string $detail Optional second line.
     *
     * @return void
     */
    public function begin($key, $label, $detail = '')
    {
        $index = $this->indexOf($key);
        $entry = [
            'key'    => (string) $key,
            'label'  => (string) $label,
            'detail' => (string) $detail,
            'status' => 'active',
            't'      => $this->now(),
            'd'      => 0,
        ];

        if ($index === null) {
            $this->steps[] = $entry;
        } else {
            $entry['t']          = $this->steps[$index]['t'];
            $this->steps[$index] = $entry;
        }

        $this->save(true);
    }

    /**
     * Settle one step, whatever its status was (a parallel step that finished,
     * or a finished page the final check had to drop after all).
     *
     * @param string      $key    Step key.
     * @param string      $status "done" or "failed".
     * @param string|null $detail Final detail, or null to keep it.
     * @param string|null $label  Final label, or null to keep it.
     *
     * @return void
     */
    public function finish($key, $status, $detail = null, $label = null)
    {
        $index = $this->indexOf($key);

        if ($index === null) {
            return;
        }

        if ($this->steps[$index]['status'] === 'active') {
            $this->steps[$index]['d'] = round($this->now() - $this->steps[$index]['t'], 1);
        }

        $this->steps[$index]['status'] = $status === 'failed' ? 'failed' : 'done';

        if ($detail !== null) {
            $this->steps[$index]['detail'] = (string) $detail;
        }
        if ($label !== null) {
            $this->steps[$index]['label'] = (string) $label;
        }

        $this->save(true);
    }

    /**
     * Change a step's detail line (and optionally its label).
     *
     * @param string      $key    Step key.
     * @param string      $detail New detail.
     * @param string|null $label  New label, or null to keep it.
     *
     * @return void
     */
    public function detail($key, $detail, $label = null)
    {
        $index = $this->indexOf($key);

        if ($index === null) {
            return;
        }

        $detail = (string) $detail;

        if ($this->steps[$index]['detail'] === $detail && ($label === null || $this->steps[$index]['label'] === $label)) {
            return;
        }

        $this->steps[$index]['detail'] = $detail;

        if ($label !== null) {
            $this->steps[$index]['label'] = (string) $label;
        }

        $this->save(false);
    }

    /**
     * Mark one step (or the active one) done.
     *
     * @param string|null $key    Step key; null for every active step.
     * @param string|null $detail Final detail, or null to keep it.
     *
     * @return void
     */
    public function done($key = null, $detail = null)
    {
        $now = $this->now();

        foreach ($this->steps as $i => $step) {
            if ($step['status'] !== 'active' || ($key !== null && $step['key'] !== $key)) {
                continue;
            }

            $this->steps[$i]['status'] = 'done';
            $this->steps[$i]['d']      = round($now - $step['t'], 1);

            if ($detail !== null) {
                $this->steps[$i]['detail'] = (string) $detail;
            }
        }

        $this->save(true);
    }

    /**
     * Record a step that finished the moment it started.
     *
     * @param string $key    Step key.
     * @param string $label  Label.
     * @param string $detail Detail.
     *
     * @return void
     */
    public function instant($key, $label, $detail = '')
    {
        $this->step($key, $label, $detail);
        $this->done($key);
    }

    /**
     * Mark the active step failed.
     *
     * @param string $detail Why, for the user.
     *
     * @return void
     */
    public function fail($detail = '')
    {
        $now = $this->now();

        foreach ($this->steps as $i => $step) {
            if ($step['status'] === 'active') {
                $this->steps[$i]['status'] = 'failed';
                $this->steps[$i]['d']      = round($now - $step['t'], 1);

                if ($detail !== '') {
                    $this->steps[$i]['detail'] = (string) $detail;
                }
            }
        }

        $this->save(true);
    }

    /**
     * @return array[]
     */
    public function steps()
    {
        return $this->steps;
    }

    /**
     * The job with the latest steps applied.
     *
     * @return Job|null
     */
    public function job()
    {
        return $this->job === null ? null : $this->job->with(['steps' => $this->steps]);
    }

    /**
     * Write anything a throttled detail update held back.
     *
     * @return void
     */
    public function flush()
    {
        if ($this->dirty) {
            $this->save(true);
        }
    }

    /**
     * @param bool $force Write even inside the throttle window.
     *
     * @return void
     */
    private function save($force)
    {
        if ($this->store === null || $this->job === null) {
            return;
        }

        $now = microtime(true);

        if (! $force && ($now - $this->lastSave) < self::DETAIL_INTERVAL) {
            $this->dirty = true;

            return;
        }

        $this->job      = $this->job->with(['steps' => $this->steps]);
        $this->lastSave = $now;
        $this->dirty    = false;

        $this->store->save($this->job);
    }

    /**
     * @param string $key Step key.
     *
     * @return int|null
     */
    private function indexOf($key)
    {
        foreach ($this->steps as $i => $step) {
            if ($step['key'] === $key) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @return float Seconds since the job started, one decimal.
     */
    private function now()
    {
        return round(microtime(true) - $this->startedAt, 1);
    }
}
