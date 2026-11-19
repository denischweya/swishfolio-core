<?php

namespace SwishfolioCore\Tests\Unit\Tools;

use SwishfolioCore\Tools\Journal\MutationJournal;
use SwishfolioCore\Tools\ToolContext;
use SwishfolioCore\Tools\ToolError;
use SwishfolioCore\Tools\ToolException;
use SwishfolioCore\Tools\ToolInterface;

abstract class ToolRunnerTestFixtures extends ToolsTestCase
{
}

class SpyTool implements ToolInterface
{
    /** @var string[] */
    public $calls = [];

    public function getName()
    {
        return 'test/spy';
    }

    public function getTitle()
    {
        return 'Spy';
    }

    public function getDescription()
    {
        return 'Records the order it was called in.';
    }

    public function getInputSchema()
    {
        return ['type' => 'object', 'additionalProperties' => false, 'properties' => []];
    }

    public function getSideEffect()
    {
        return self::EFFECT_READ;
    }

    public function authorize(array $input, ToolContext $context)
    {
        $this->calls[] = 'authorize';
    }

    public function execute(array $input, ToolContext $context)
    {
        $this->calls[] = 'execute';

        return ['ok' => true];
    }
}

class RefusingTool implements ToolInterface
{
    public function getName()
    {
        return 'test/refuse';
    }

    public function getTitle()
    {
        return 'Refuse';
    }

    public function getDescription()
    {
        return 'Always refuses.';
    }

    public function getInputSchema()
    {
        return ['type' => 'object', 'additionalProperties' => false, 'properties' => []];
    }

    public function getSideEffect()
    {
        return self::EFFECT_READ;
    }

    public function authorize(array $input, ToolContext $context)
    {
        throw ToolException::make($this->getName(), ToolError::FORBIDDEN, 'Nope.');
    }

    public function execute(array $input, ToolContext $context)
    {
        throw new \LogicException('execute must not be reached after a refusal.');
    }
}

class PublishedWriteTool implements ToolInterface
{
    /** @var MutationJournal */
    private $journal;

    public function __construct(MutationJournal $journal)
    {
        $this->journal = $journal;
    }

    public function getName()
    {
        return 'test/published';
    }

    public function getTitle()
    {
        return 'Published write';
    }

    public function getDescription()
    {
        return 'Writes an option, so it needs confirmation.';
    }

    public function getInputSchema()
    {
        return [
            'type'                 => 'object',
            'additionalProperties' => false,
            'properties'           => ['note' => ['type' => 'string']],
        ];
    }

    public function getSideEffect()
    {
        return self::EFFECT_WRITE_PUBLISHED;
    }

    public function authorize(array $input, ToolContext $context)
    {
    }

    public function execute(array $input, ToolContext $context)
    {
        $this->journal->updateOption($context, $this->getName(), 'sfcore_test_flag', 'yes', 'Set the flag');

        return ['applied' => true];
    }
}

class FailingWriteTool implements ToolInterface
{
    /** @var MutationJournal */
    private $journal;

    public function __construct(MutationJournal $journal)
    {
        $this->journal = $journal;
    }

    public function getName()
    {
        return 'test/failing';
    }

    public function getTitle()
    {
        return 'Failing write';
    }

    public function getDescription()
    {
        return 'Fails after being allowed to run.';
    }

    public function getInputSchema()
    {
        return ['type' => 'object', 'additionalProperties' => false, 'properties' => []];
    }

    public function getSideEffect()
    {
        return self::EFFECT_WRITE_PUBLISHED;
    }

    public function authorize(array $input, ToolContext $context)
    {
    }

    public function execute(array $input, ToolContext $context)
    {
        throw ToolException::make($this->getName(), ToolError::WRITE_FAILED, 'Disk is full.');
    }
}
