<?php

namespace SwishfolioCore\Tests\Support;

use SwishfolioCore\Jobs\Job;
use SwishfolioCore\Jobs\JobStoreInterface;

/**
 * Jobs in an array, with every save kept.
 *
 * The history matters: the point of a progress record is that it changes while
 * the work runs, and a store that only remembers the final state cannot prove
 * the intermediate states ever existed.
 */
class InMemoryJobStore implements JobStoreInterface
{
    /** @var Job[] Keyed by id. */
    public $jobs = [];

    /** @var array[] Every save, in order: ['id' => string, 'status' => string]. */
    public $saves = [];

    public function save(Job $job)
    {
        $this->jobs[$job->getId()] = $job;
        $this->saves[]             = ['id' => $job->getId(), 'status' => $job->getStatus()];
    }

    public function find($id)
    {
        return isset($this->jobs[(string) $id]) ? $this->jobs[(string) $id] : null;
    }

    public function forget($id)
    {
        unset($this->jobs[(string) $id]);
    }

    /**
     * @return string[] The status each save recorded, in order.
     */
    public function statusTrail()
    {
        return array_column($this->saves, 'status');
    }
}
