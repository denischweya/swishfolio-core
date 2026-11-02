<?php
/**
 * One tool call the agent made, as the user sees it.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Agent;

/**
 * Deliberately not the raw tool result: the user wants to know what happened,
 * and a page's full block tree coming back from read-page is noise to them even
 * though it is exactly what the model needed.
 */
class AgentStep
{
    /** @var string */
    private $tool;

    /** @var string */
    private $summary;

    /** @var bool */
    private $ok;

    /** @var string */
    private $errorCode;

    /**
     * @param string $tool      Tool name.
     * @param string $summary   Human-readable description of the call.
     * @param bool   $ok        Whether it succeeded.
     * @param string $errorCode ToolError code when it did not.
     */
    public function __construct($tool, $summary, $ok = true, $errorCode = '')
    {
        $this->tool      = (string) $tool;
        $this->summary   = (string) $summary;
        $this->ok        = (bool) $ok;
        $this->errorCode = (string) $errorCode;
    }

    public function getTool()
    {
        return $this->tool;
    }

    public function getSummary()
    {
        return $this->summary;
    }

    public function isOk()
    {
        return $this->ok;
    }

    public function getErrorCode()
    {
        return $this->errorCode;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $out = [
            'tool'    => $this->tool,
            'summary' => $this->summary,
            'ok'      => $this->ok,
        ];

        if ('' !== $this->errorCode) {
            $out['errorCode'] = $this->errorCode;
        }

        return $out;
    }
}
