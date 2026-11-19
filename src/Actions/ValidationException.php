<?php
/**
 * Raised when an AI response fails validation.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Actions;

/**
 * Carries the stage that rejected the response and, where known, the offending
 * path — so the editor can tell the user precisely what was refused.
 */
class ValidationException extends \RuntimeException
{
    /** @var string */
    private $stage;

    /** @var string|null */
    private $path;

    /**
     * @param string      $stage   Stage slug, e.g. "allowlist".
     * @param string      $message Human-readable reason.
     * @param string|null $path    Offending path, e.g. "actions.0.changes.decoShape1Color".
     */
    public function __construct($stage, $message, $path = null)
    {
        parent::__construct($message);

        $this->stage = $stage;
        $this->path  = $path;
    }

    public function getStage()
    {
        return $this->stage;
    }

    public function getPath()
    {
        return $this->path;
    }
}
