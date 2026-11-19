<?php
/**
 * Mutable state shared by one run of server-side actions.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Actions\Handlers;

/**
 * Plan keys are the only currency a plan has for cross-references, because real
 * post IDs do not exist until pages are created. This object is where a key
 * becomes an ID, and it is also the ledger of what a run created: no transaction
 * spans wp_insert_post calls, so the only possible undo is "trash exactly these".
 */
class ExecutionContext
{
    /** @var int */
    private $userId;

    /** @var array Plan key => post ID. */
    private $pageIds = [];

    /** @var int[] */
    private $createdIds = [];

    /** @var array Per-action results, in execution order. */
    private $results = [];

    /**
     * @param int $userId User the run is attributed to.
     */
    public function __construct($userId)
    {
        $this->userId = (int) $userId;
    }

    public function getUserId()
    {
        return $this->userId;
    }

    /**
     * @param string $key    Plan page key.
     * @param int    $postId Post ID it resolved to.
     *
     * @return void
     */
    public function setPageId($key, $postId)
    {
        $this->pageIds[(string) $key] = (int) $postId;
    }

    /**
     * @param string $key Plan page key.
     *
     * @return int|null
     */
    public function getPageId($key)
    {
        return isset($this->pageIds[(string) $key]) ? $this->pageIds[(string) $key] : null;
    }

    /**
     * @return array Plan key => post ID.
     */
    public function getPageIds()
    {
        return $this->pageIds;
    }

    /**
     * @param int $postId Post this run created.
     *
     * @return void
     */
    public function recordCreated($postId)
    {
        $this->createdIds[] = (int) $postId;
    }

    /**
     * @return int[]
     */
    public function getCreatedIds()
    {
        return $this->createdIds;
    }

    /**
     * @param string $type   Action type.
     * @param array  $result Handler result.
     *
     * @return void
     */
    public function addResult($type, array $result)
    {
        $this->results[] = array_merge(['type' => (string) $type], $result);
    }

    /**
     * @return array
     */
    public function getResults()
    {
        return $this->results;
    }
}
