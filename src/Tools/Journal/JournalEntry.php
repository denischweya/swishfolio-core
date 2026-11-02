<?php
/**
 * One row of the AI journal.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Tools\Journal;

/**
 * Three kinds of row share this table, distinguished by targetType.
 *
 * A "call" row is the audit record of one tool call: what was asked, what the
 * tool reported, whether it failed. A "post" or "option" row is an undo record
 * for one object the call actually wrote, carrying the prior state. One call row
 * can therefore have several target rows beneath it — SetFrontPage writes three
 * options — and rollback walks only the target rows.
 *
 * Reads are never journalled. The table exists to answer "what changed and how
 * do I undo it", and read volume would bury that for no accountability gain.
 */
class JournalEntry
{
    const TARGET_CALL   = 'call';
    const TARGET_POST   = 'post';
    const TARGET_OPTION = 'option';

    const RESULT_OK    = 'ok';
    const RESULT_ERROR = 'error';

    /** @var array */
    private $data;

    /**
     * @param array $data Row fields.
     */
    public function __construct(array $data)
    {
        $this->data = $data + [
            'id'          => 0,
            'run_id'      => '',
            'seq'         => 0,
            'user_id'     => 0,
            'tool'        => '',
            'side_effect' => '',
            'summary'     => '',
            'input'       => null,
            'target_type' => self::TARGET_CALL,
            'target_id'   => '',
            'prior'       => null,
            'result'      => self::RESULT_OK,
            'error_code'  => null,
            'reverted_at' => null,
            'revert_error' => null,
            'created_at'  => '',
        ];
    }

    /**
     * @param string $key Field name.
     *
     * @return mixed
     */
    public function get($key)
    {
        return isset($this->data[$key]) ? $this->data[$key] : null;
    }

    public function getId()
    {
        return (int) $this->data['id'];
    }

    public function getRunId()
    {
        return (string) $this->data['run_id'];
    }

    public function getSeq()
    {
        return (int) $this->data['seq'];
    }

    public function getTool()
    {
        return (string) $this->data['tool'];
    }

    public function getTargetType()
    {
        return (string) $this->data['target_type'];
    }

    public function getTargetId()
    {
        return (string) $this->data['target_id'];
    }

    public function getSummary()
    {
        return (string) $this->data['summary'];
    }

    public function getResult()
    {
        return (string) $this->data['result'];
    }

    public function getErrorCode()
    {
        return null === $this->data['error_code'] ? null : (string) $this->data['error_code'];
    }

    public function getRevertedAt()
    {
        return $this->data['reverted_at'];
    }

    public function getRevertError()
    {
        return $this->data['revert_error'];
    }

    public function isReverted()
    {
        return ! empty($this->data['reverted_at']);
    }

    /**
     * @return array Decoded prior-state payload, empty when there is none.
     */
    public function getPrior()
    {
        if (empty($this->data['prior'])) {
            return [];
        }

        if (is_array($this->data['prior'])) {
            return $this->data['prior'];
        }

        $decoded = json_decode((string) $this->data['prior'], true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array Row as stored, for the repository.
     */
    public function toRow()
    {
        $row = $this->data;

        unset($row['id']);

        if (is_array($row['prior'])) {
            $row['prior'] = wp_json_encode($row['prior']);
        }

        if (is_array($row['input'])) {
            $row['input'] = wp_json_encode($row['input']);
        }

        return $row;
    }

    /**
     * @param array $data Fields to override.
     *
     * @return self
     */
    public function with(array $data)
    {
        return new self($data + $this->data);
    }
}
