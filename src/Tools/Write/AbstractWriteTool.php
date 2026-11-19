<?php
/**
 * Shared behaviour for tools that write.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Tools\Write;

use SwishfolioCore\Tools\Journal\MutationJournal;
use SwishfolioCore\Tools\ToolError;
use SwishfolioCore\Tools\ToolException;
use SwishfolioCore\Tools\ToolInterface;

/**
 * Holds the journal every write goes through. A write tool that reached for
 * wp_insert_post directly would compile and pass its own tests, and would only
 * reveal itself the first time a user asked to undo — so the journal is a
 * constructor dependency rather than something a tool opts into.
 */
abstract class AbstractWriteTool implements ToolInterface
{
    /** @var MutationJournal */
    protected $journal;

    /**
     * @param MutationJournal $journal Write gateway.
     */
    public function __construct(MutationJournal $journal)
    {
        $this->journal = $journal;
    }

    /**
     * @param string $message Reason the user may not do this.
     *
     * @throws ToolException Always.
     *
     * @return void
     */
    protected function forbid($message)
    {
        throw ToolException::make($this->getName(), ToolError::FORBIDDEN, $message);
    }
}
