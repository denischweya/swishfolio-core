<?php
/**
 * Job progress in transients.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Jobs;

/**
 * Transients rather than a table, because a job is ephemeral by nature: it
 * exists to answer "how far along is it" for the minute or two the work takes,
 * and the durable record of what the work did is the journal. Losing a transient
 * costs a progress bar, not history.
 *
 * The TTL is generous relative to the work, so a user who leaves the tab and
 * comes back still sees the outcome rather than a job that vanished.
 */
class TransientJobStore implements JobStoreInterface
{
    const PREFIX = 'sfcore_job_';
    const TTL    = 3600;

    public function save(Job $job)
    {
        set_transient(self::PREFIX . $job->getId(), $job->toArray(), self::TTL);
    }

    public function find($id)
    {
        $stored = get_transient(self::PREFIX . (string) $id);

        return is_array($stored) ? new Job($stored) : null;
    }

    public function forget($id)
    {
        if (function_exists('delete_transient')) {
            delete_transient(self::PREFIX . (string) $id);
        }
    }
}
