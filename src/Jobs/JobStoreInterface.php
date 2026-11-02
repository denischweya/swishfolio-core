<?php
/**
 * Storage for job progress records.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Jobs;

interface JobStoreInterface
{
    /**
     * @param Job $job Job to store.
     *
     * @return void
     */
    public function save(Job $job);

    /**
     * @param string $id Job identifier.
     *
     * @return Job|null
     */
    public function find($id);

    /**
     * @param string $id Job identifier.
     *
     * @return void
     */
    public function forget($id);
}
