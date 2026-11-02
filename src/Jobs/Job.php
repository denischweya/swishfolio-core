<?php
/**
 * One unit of work running outside the request that asked for it.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Jobs;

/**
 * A job is a progress record, not a record of what happened. What happened is in
 * the journal, which is durable; this exists so a browser can ask "how far along
 * is it" and is allowed to evaporate afterwards.
 *
 * That distinction is why jobs live in transients rather than a table: if an
 * object cache drops one, the user loses a progress bar, not an audit trail.
 */
class Job
{
    const STATUS_QUEUED  = 'queued';
    const STATUS_RUNNING = 'running';
    const STATUS_DONE    = 'done';
    const STATUS_FAILED  = 'failed';

    /** @var array */
    private $data;

    /**
     * @param array $data Job fields.
     */
    public function __construct(array $data)
    {
        $this->data = $data + [
            'id'        => '',
            'userId'    => 0,
            'status'    => self::STATUS_QUEUED,
            'label'     => '',
            'payload'   => [],
            'steps'     => [],
            'result'    => null,
            'error'     => null,
            'runId'     => '',
            'createdAt' => 0,
            'updatedAt' => 0,
        ];
    }

    /**
     * @param string $id      Job identifier.
     * @param int    $userId  Owner.
     * @param string $label   Human-readable description.
     * @param array  $payload What the runner needs to do the work.
     *
     * @return self
     */
    public static function queued($id, $userId, $label, array $payload)
    {
        return new self(
            [
                'id'        => (string) $id,
                'userId'    => (int) $userId,
                'status'    => self::STATUS_QUEUED,
                'label'     => (string) $label,
                'payload'   => $payload,
                'createdAt' => time(),
                'updatedAt' => time(),
            ]
        );
    }

    public function getId()
    {
        return (string) $this->data['id'];
    }

    public function getUserId()
    {
        return (int) $this->data['userId'];
    }

    public function getStatus()
    {
        return (string) $this->data['status'];
    }

    public function getLabel()
    {
        return (string) $this->data['label'];
    }

    public function getPayload()
    {
        return (array) $this->data['payload'];
    }

    public function getSteps()
    {
        return (array) $this->data['steps'];
    }

    public function getResult()
    {
        return $this->data['result'];
    }

    public function getError()
    {
        return $this->data['error'];
    }

    public function getRunId()
    {
        return (string) $this->data['runId'];
    }

    public function isFinished()
    {
        return in_array($this->getStatus(), [self::STATUS_DONE, self::STATUS_FAILED], true);
    }

    /**
     * @param array $changes Fields to replace.
     *
     * @return self
     */
    public function with(array $changes)
    {
        return new self(array_merge($this->data, $changes, ['updatedAt' => time()]));
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return $this->data;
    }

    /**
     * The shape a polling client reads. The payload is deliberately absent: it
     * holds the conversation history, which the client already has and which
     * would double the size of every poll.
     *
     * @return array
     */
    public function toStatusArray()
    {
        return [
            'jobId'    => $this->getId(),
            'status'   => $this->getStatus(),
            'label'    => $this->getLabel(),
            'steps'    => $this->getSteps(),
            'result'   => $this->getResult(),
            'error'    => $this->getError(),
            'runId'    => $this->getRunId(),
            'finished' => $this->isFinished(),
        ];
    }
}
