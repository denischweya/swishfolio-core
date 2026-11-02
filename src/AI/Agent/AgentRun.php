<?php
/**
 * The outcome of one agent turn.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Agent;

use SwishfolioCore\AI\Conversation\Message;

/**
 * Three outcomes, and the middle one is the interesting one. "Awaiting
 * confirmation" is not a failure: the model proposed a change to the live site,
 * the gate stopped it, and the run is resumable with the token this carries. A
 * design that reported that as an error would make the only safe path through
 * the system look broken.
 */
class AgentRun
{
    const STATUS_DONE    = 'done';
    const STATUS_PENDING = 'awaiting_confirmation';
    const STATUS_CAPPED  = 'capped';

    /** @var string */
    private $status;

    /** @var string */
    private $message;

    /** @var AgentStep[] */
    private $steps;

    /** @var Message[] */
    private $history;

    /** @var array Pending calls, each ['name' => string, 'input' => array]. */
    private $pending;

    /** @var string */
    private $confirmToken;

    /** @var int */
    private $inputTokens;

    /** @var int */
    private $outputTokens;

    /** @var int */
    private $iterations;

    /**
     * @param string      $status       One of the STATUS_* constants.
     * @param string      $message      Assistant prose for the user.
     * @param AgentStep[] $steps        Calls made this turn.
     * @param Message[]   $history      Conversation to send with the next turn.
     * @param array       $pending      Calls awaiting approval.
     * @param string      $confirmToken Token approving exactly those calls.
     * @param int         $inputTokens  Prompt tokens across the turn.
     * @param int         $outputTokens Completion tokens across the turn.
     * @param int         $iterations   Provider round trips used.
     */
    public function __construct(
        $status,
        $message,
        array $steps,
        array $history,
        array $pending = [],
        $confirmToken = '',
        $inputTokens = 0,
        $outputTokens = 0,
        $iterations = 0
    ) {
        $this->status       = (string) $status;
        $this->message      = (string) $message;
        $this->steps        = $steps;
        $this->history      = $history;
        $this->pending      = $pending;
        $this->confirmToken = (string) $confirmToken;
        $this->inputTokens  = (int) $inputTokens;
        $this->outputTokens = (int) $outputTokens;
        $this->iterations   = (int) $iterations;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function getMessage()
    {
        return $this->message;
    }

    /**
     * @return AgentStep[]
     */
    public function getSteps()
    {
        return $this->steps;
    }

    /**
     * @return Message[]
     */
    public function getHistory()
    {
        return $this->history;
    }

    public function getPending()
    {
        return $this->pending;
    }

    public function getConfirmToken()
    {
        return $this->confirmToken;
    }

    public function getInputTokens()
    {
        return $this->inputTokens;
    }

    public function getOutputTokens()
    {
        return $this->outputTokens;
    }

    public function getIterations()
    {
        return $this->iterations;
    }

    /**
     * @return array Wire shape for the REST response.
     */
    public function toArray()
    {
        return [
            'status'  => $this->status,
            'message' => $this->message,
            'steps'   => array_map(
                static function (AgentStep $step) {
                    return $step->toArray();
                },
                $this->steps
            ),
            'history' => array_map(
                static function (Message $message) {
                    return $message->toArray();
                },
                $this->history
            ),
            'pending'      => $this->pending,
            'confirmToken' => $this->confirmToken,
            'usage'        => [
                'inputTokens'  => $this->inputTokens,
                'outputTokens' => $this->outputTokens,
                'iterations'   => $this->iterations,
            ],
        ];
    }
}
