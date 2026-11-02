<?php
/**
 * The tools the AI may call.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Tools;

use SwishfolioCore\Blocks\BlockRegistry;
use SwishfolioCore\Tools\Journal\MutationJournal;
use SwishfolioCore\Tools\Journal\Rollback;
use SwishfolioCore\Tools\Read\GetBlockCapabilitiesTool;
use SwishfolioCore\Tools\Read\GetGlobalStylesTool;
use SwishfolioCore\Tools\Read\ListContentTool;
use SwishfolioCore\Tools\Read\ListMediaTool;
use SwishfolioCore\Tools\Read\ReadPageTool;
use SwishfolioCore\Tools\Read\SiteInfoTool;
use SwishfolioCore\Tools\Write\CreatePageTool;
use SwishfolioCore\Tools\Write\RollbackRunTool;
use SwishfolioCore\Tools\Write\SetFrontPageTool;
use SwishfolioCore\Tools\Write\SetNavigationTool;
use SwishfolioCore\Tools\Write\SetStyleVariationTool;
use SwishfolioCore\Tools\Write\UpdatePageTool;

/**
 * Mirrors ActionExecutor::withDefaultHandlers(), which it replaces: the same
 * explicit list in one place, so what the AI can reach is readable without
 * tracing hooks.
 *
 * The filter is for the hosted product and third parties to add tools later. It
 * runs through the same gate as everything else — a filtered-in tool still has
 * to declare a schema and a permission callback, and still cannot write without
 * going through the journal.
 */
class ToolRegistry
{
    /** @var ToolInterface[] Keyed by name. */
    private $tools = [];

    /**
     * @param ToolInterface[] $tools Tools to register.
     */
    public function __construct(array $tools = [])
    {
        foreach ($tools as $tool) {
            $this->add($tool);
        }
    }

    /**
     * @param MutationJournal $journal       Write gateway.
     * @param BlockRegistry   $blockRegistry Capability manifests.
     *
     * @return self
     */
    public static function withDefaultTools(MutationJournal $journal, BlockRegistry $blockRegistry)
    {
        $tools = [
            new SiteInfoTool(),
            new ListContentTool(),
            new ReadPageTool($blockRegistry),
            new GetGlobalStylesTool(),
            new ListMediaTool(),
            new GetBlockCapabilitiesTool($blockRegistry),
            new CreatePageTool($journal),
            new UpdatePageTool($journal),
            new SetFrontPageTool($journal),
            new SetNavigationTool($journal),
            new SetStyleVariationTool($journal),
            new RollbackRunTool(new Rollback($journal->repository()), $journal->repository()),
        ];

        /**
         * Filters the registered AI tools.
         *
         * @param ToolInterface[] $tools   Tools.
         * @param MutationJournal $journal Write gateway for tools that mutate.
         */
        $tools = apply_filters('swishfolio_ai_tools', $tools, $journal);

        $registry = new self();

        foreach ($tools as $tool) {
            if ($tool instanceof ToolInterface) {
                $registry->add($tool);
            }
        }

        return $registry;
    }

    /**
     * @param ToolInterface $tool Tool to register.
     *
     * @return void
     */
    public function add(ToolInterface $tool)
    {
        $this->tools[$tool->getName()] = $tool;
    }

    /**
     * @param string $name Tool name.
     *
     * @return bool
     */
    public function has($name)
    {
        return isset($this->tools[(string) $name]);
    }

    /**
     * @param string $name Tool name.
     *
     * @return ToolInterface|null
     */
    public function get($name)
    {
        return isset($this->tools[(string) $name]) ? $this->tools[(string) $name] : null;
    }

    /**
     * @return ToolInterface[] Keyed by name.
     */
    public function all()
    {
        return $this->tools;
    }

    /**
     * @return string[]
     */
    public function names()
    {
        return array_keys($this->tools);
    }

    /**
     * The tool list as the model sees it.
     *
     * @return array[]
     */
    public function describe()
    {
        $out = [];

        foreach ($this->tools as $name => $tool) {
            $out[] = [
                'name'        => $name,
                'title'       => $tool->getTitle(),
                'description' => $tool->getDescription(),
                'sideEffect'  => $tool->getSideEffect(),
                'inputSchema' => $tool->getInputSchema(),
            ];
        }

        return $out;
    }
}
