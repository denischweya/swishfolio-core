<?php
/**
 * Run state for one sequence of tool calls.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Tools;

use SwishfolioCore\Actions\Handlers\ExecutionContext;

/**
 * Extends ExecutionContext rather than replacing it, so plan-key resolution and
 * the createdIds ledger carry over unchanged and the migrated handlers keep the
 * context API they already use.
 *
 * What it adds is the run identity the journal groups by, the site the run is
 * bound to, and whether the user has confirmed the plan.
 *
 * siteId is read from the current site at construction and is never taken from
 * model output: a model-supplied site ID on a multisite install is a tenancy
 * escape, so there is deliberately no setter for it.
 */
class ToolContext extends ExecutionContext
{
    /** @var string */
    private $runId;

    /** @var int */
    private $siteId;

    /** @var bool */
    private $confirmed = false;

    /** @var int */
    private $seq = 0;

    /**
     * @param int    $userId User the run is attributed to.
     * @param int    $siteId Site the run is bound to.
     * @param string $runId  Run identifier; generated when empty.
     */
    public function __construct($userId, $siteId, $runId = '')
    {
        parent::__construct($userId);

        $this->siteId = (int) $siteId;
        $this->runId  = '' !== (string) $runId ? (string) $runId : self::newRunId();
    }

    /**
     * Builds a context for the current user and site.
     *
     * @param string $runId Run identifier; generated when empty.
     *
     * @return self
     */
    public static function current($runId = '')
    {
        return new self(get_current_user_id(), self::currentSiteId(), $runId);
    }

    /**
     * Builds a context for a given user on the current site.
     *
     * @param int    $userId User the run is attributed to.
     * @param string $runId  Run identifier; generated when empty.
     *
     * @return self
     */
    public static function forUser($userId, $runId = '')
    {
        return new self($userId, self::currentSiteId(), $runId);
    }

    /**
     * @return int
     */
    public static function currentSiteId()
    {
        return function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 1;
    }

    /**
     * @return string 32 hex characters.
     */
    public static function newRunId()
    {
        if (function_exists('wp_generate_uuid4')) {
            return str_replace('-', '', wp_generate_uuid4());
        }

        return md5(uniqid((string) mt_rand(), true));
    }

    public function getRunId()
    {
        return $this->runId;
    }

    public function getSiteId()
    {
        return $this->siteId;
    }

    public function isConfirmed()
    {
        return $this->confirmed;
    }

    /**
     * @param bool $confirmed Whether the user approved the plan this run executes.
     *
     * @return void
     */
    public function setConfirmed($confirmed)
    {
        $this->confirmed = (bool) $confirmed;
    }

    /**
     * Position of the next journal row within this run, counting from 1.
     *
     * @return int
     */
    public function nextSeq()
    {
        return ++$this->seq;
    }

    /**
     * Carry on after rows an earlier request already wrote under this run,
     * so the run's rows keep one order (an undo replays them newest first).
     *
     * @param int $seq Highest seq already used by the run.
     *
     * @return void
     */
    public function continueFrom($seq)
    {
        $this->seq = max($this->seq, (int) $seq);
    }
}
