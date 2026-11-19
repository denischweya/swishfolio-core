<?php
/**
 * Raised when a tool call cannot proceed.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Tools;

use SwishfolioCore\Actions\Handlers\ExecutionException;

/**
 * Carries a ToolError rather than a bare message, so every failure that reaches
 * the REST layer or the journal already has a stable code attached.
 *
 * The two legacy exception types map in rather than being replaced: the
 * generation path still raises ExecutionException from code this slice does not
 * touch, and both error formats have to arrive at the caller as one shape.
 */
class ToolException extends \RuntimeException
{
    /** @var string */
    private $toolName;

    /** @var ToolError */
    private $error;

    /**
     * @param string    $toolName Tool that failed.
     * @param ToolError $error    The failure.
     */
    public function __construct($toolName, ToolError $error)
    {
        parent::__construct($error->getMessage());

        $this->toolName = (string) $toolName;
        $this->error    = $error;
    }

    /**
     * @param string $toolName Tool that failed.
     * @param string $code     ToolError code constant.
     * @param string $message  Human-readable reason.
     * @param array  $details  Extra context.
     *
     * @return self
     */
    public static function make($toolName, $code, $message, array $details = [])
    {
        return new self($toolName, new ToolError($code, $message, $details));
    }

    /**
     * Maps a legacy handler failure onto the tool error contract.
     *
     * ExecutionException's reason slugs were never a closed set, so anything
     * unrecognised becomes write_failed with the original slug preserved in
     * details — losing the detail would be worse than an imprecise code.
     *
     * @param string             $toolName Tool that failed.
     * @param ExecutionException $e        The legacy failure.
     *
     * @return self
     */
    public static function fromExecution($toolName, ExecutionException $e)
    {
        $reason = $e->getReason();
        $known  = [
            'forbidden'      => ToolError::FORBIDDEN,
            'not_a_page'     => ToolError::TARGET_MISSING,
            'missing_page'   => ToolError::TARGET_MISSING,
            'unknown_action' => ToolError::TOOL_NOT_FOUND,
        ];

        $code    = isset($known[$reason]) ? $known[$reason] : ToolError::WRITE_FAILED;
        $details = isset($known[$reason]) ? [] : ['reason' => $reason];

        return self::make($toolName, $code, $e->getMessage(), $details);
    }

    public function getToolName()
    {
        return $this->toolName;
    }

    public function getError()
    {
        return $this->error;
    }
}
