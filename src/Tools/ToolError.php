<?php
/**
 * One machine-readable tool failure.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Tools;

/**
 * The model is the primary audience for the code, not the message. A model that
 * reads "schema_invalid" with a field path can correct its own call; one that
 * reads only prose guesses. So codes are a closed, stable set and messages are
 * free to be reworded or translated without breaking anything.
 */
class ToolError
{
    const TOOL_NOT_FOUND   = 'tool_not_found';
    const SCHEMA_INVALID   = 'schema_invalid';
    const FORBIDDEN        = 'forbidden';
    const CONFIRM_REQUIRED = 'confirm_required';
    const TARGET_MISSING   = 'target_missing';
    const WRITE_FAILED     = 'write_failed';
    const RATE_LIMITED     = 'rate_limited';

    const CODES = [
        self::TOOL_NOT_FOUND,
        self::SCHEMA_INVALID,
        self::FORBIDDEN,
        self::CONFIRM_REQUIRED,
        self::TARGET_MISSING,
        self::WRITE_FAILED,
        self::RATE_LIMITED,
    ];

    /** @var string */
    private $code;

    /** @var string */
    private $message;

    /** @var array */
    private $details;

    /**
     * @param string $code    One of the code constants.
     * @param string $message Human-readable reason.
     * @param array  $details Extra context, e.g. ['field' => 'postId'].
     */
    public function __construct($code, $message, array $details = [])
    {
        $this->code    = (string) $code;
        $this->message = (string) $message;
        $this->details = $details;
    }

    public function getCode()
    {
        return $this->code;
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function getDetails()
    {
        return $this->details;
    }

    /**
     * @return array Wire shape, for REST responses and model-visible output.
     */
    public function toArray()
    {
        $out = [
            'code'    => $this->code,
            'message' => $this->message,
        ];

        if ($this->details !== []) {
            $out['details'] = $this->details;
        }

        return $out;
    }
}
