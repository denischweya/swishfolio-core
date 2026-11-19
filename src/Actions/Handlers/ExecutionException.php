<?php
/**
 * Raised when a server-side action cannot be executed.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Actions\Handlers;

/**
 * Mirrors ValidationException's shape so the REST layer reports one error format
 * whether a run was refused before writing or stopped part-way through, and
 * carries the IDs created so far so the caller can offer to undo them.
 */
class ExecutionException extends \RuntimeException
{
    /** @var string */
    private $actionType;

    /** @var string */
    private $reason;

    /** @var int[] */
    private $createdIds = [];

    /**
     * @param string $actionType Action that failed, e.g. "create_page".
     * @param string $reason     Reason slug, e.g. "forbidden".
     * @param string $message    Human-readable reason.
     * @param int[]  $createdIds Posts created before the failure.
     */
    public function __construct($actionType, $reason, $message, array $createdIds = [])
    {
        parent::__construct($message);

        $this->actionType = $actionType;
        $this->reason     = $reason;
        $this->createdIds = $createdIds;
    }

    public function getActionType()
    {
        return $this->actionType;
    }

    public function getReason()
    {
        return $this->reason;
    }

    public function getCreatedIds()
    {
        return $this->createdIds;
    }

    /**
     * @param int[] $ids Posts created before the failure.
     *
     * @return void
     */
    public function setCreatedIds(array $ids)
    {
        $this->createdIds = $ids;
    }
}
