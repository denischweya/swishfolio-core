<?php
/**
 * Reads a page as a block tree.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Tools\Read;

use SwishfolioCore\Blocks\BlockRegistry;
use SwishfolioCore\Tools\ToolContext;
use SwishfolioCore\Tools\ToolError;
use SwishfolioCore\Tools\ToolException;

/**
 * The tool that turns a write-only AI into one that can edit what is already
 * there. "Add a testimonials section to my homepage" is unanswerable without it.
 *
 * Two deliberate reductions. Markup is never returned: 20 of 23 blocks are
 * static save() blocks, so the tree is the authoritative state and the rendered
 * HTML would cost tokens to say the same thing less precisely. And attributes
 * are filtered to what the block's capability manifest declares — a block with
 * no manifest comes back opaque, children intact, attributes withheld, because
 * the validator could not check an edit to them and offering them would invite
 * one.
 *
 * Addresses are index paths, not clientIds: clientIds exist only in the editor.
 */
class ReadPageTool extends AbstractReadTool
{
    const DEFAULT_MAX_NODES = 200;
    const MAX_MAX_NODES     = 400;
    const DEFAULT_MAX_DEPTH = 6;

    /** @var BlockRegistry */
    private $blocks;

    /** @var int */
    private $nodeCount = 0;

    /** @var bool */
    private $truncated = false;

    /**
     * @param BlockRegistry $blocks Capability manifests.
     */
    public function __construct(BlockRegistry $blocks)
    {
        $this->blocks = $blocks;
    }

    public function getName()
    {
        return 'swishfolio/read-page';
    }

    public function getTitle()
    {
        return __('Read page', 'swishfolio-core');
    }

    public function getDescription()
    {
        return 'Read one page, post or project as a tree of blocks, with each block\'s name, its editable '
            . 'attributes and its children. Each block carries a path like [0,2] that addresses it in the tree. '
            . 'Blocks without a capability manifest are returned as opaque: their children are listed but their '
            . 'attributes are not editable. Call this before changing an existing page.';
    }

    public function getInputSchema()
    {
        return [
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => ['postId'],
            'properties'           => [
                'postId' => [
                    'type'        => 'integer',
                    'minimum'     => 1,
                    'description' => 'ID of the page, post or project to read.',
                ],
                'maxNodes' => [
                    'type'        => 'integer',
                    'minimum'     => 1,
                    'maximum'     => self::MAX_MAX_NODES,
                    'default'     => self::DEFAULT_MAX_NODES,
                    'description' => 'Stop after this many blocks and report the tree as truncated.',
                ],
                'maxDepth' => [
                    'type'        => 'integer',
                    'minimum'     => 1,
                    'maximum'     => 10,
                    'default'     => self::DEFAULT_MAX_DEPTH,
                    'description' => 'How deep into nested blocks to descend.',
                ],
            ],
        ];
    }

    public function authorize(array $input, ToolContext $context)
    {
        parent::authorize($input, $context);

        $postId = isset($input['postId']) ? (int) $input['postId'] : 0;

        if (! current_user_can('edit_post', $postId)) {
            throw ToolException::make(
                $this->getName(),
                ToolError::FORBIDDEN,
                __('You cannot read this page.', 'swishfolio-core')
            );
        }
    }

    public function execute(array $input, ToolContext $context)
    {
        $postId   = (int) $input['postId'];
        $maxNodes = isset($input['maxNodes']) ? (int) $input['maxNodes'] : self::DEFAULT_MAX_NODES;
        $maxDepth = isset($input['maxDepth']) ? (int) $input['maxDepth'] : self::DEFAULT_MAX_DEPTH;

        $status = get_post_status($postId);

        if (false === $status) {
            throw ToolException::make(
                $this->getName(),
                ToolError::TARGET_MISSING,
                sprintf(
                    /* translators: %d: post ID. */
                    __('There is no page with ID %d.', 'swishfolio-core'),
                    $postId
                )
            );
        }

        $this->nodeCount = 0;
        $this->truncated = false;

        $parsed = parse_blocks((string) get_post_field('post_content', $postId));
        $tree   = $this->reduceList($parsed, [], $maxNodes, $maxDepth);

        return [
            'postId'    => $postId,
            'title'     => (string) get_post_field('post_title', $postId),
            'status'    => (string) $status,
            'postType'  => (string) get_post_type($postId),
            'blocks'    => $tree,
            'nodeCount' => $this->nodeCount,
            'truncated' => $this->truncated,
        ];
    }

    /**
     * @param array $blocks   Parsed blocks.
     * @param int[] $path     Index path to this list.
     * @param int   $maxNodes Node ceiling.
     * @param int   $maxDepth Depth ceiling.
     *
     * @return array[]
     */
    private function reduceList(array $blocks, array $path, $maxNodes, $maxDepth)
    {
        $out   = [];
        $index = 0;

        foreach ($blocks as $block) {
            if (! is_array($block) || empty($block['blockName'])) {
                // parse_blocks() emits a nameless node for the whitespace
                // between blocks. Counting those would make every path wrong.
                continue;
            }

            if ($this->nodeCount >= $maxNodes) {
                $this->truncated = true;

                break;
            }

            ++$this->nodeCount;

            $childPath = array_merge($path, [$index]);
            $out[]     = $this->reduceBlock($block, $childPath, $maxNodes, $maxDepth);
            ++$index;
        }

        return $out;
    }

    /**
     * @param array $block    One parsed block.
     * @param int[] $path     Index path to it.
     * @param int   $maxNodes Node ceiling.
     * @param int   $maxDepth Depth ceiling.
     *
     * @return array
     */
    private function reduceBlock(array $block, array $path, $maxNodes, $maxDepth)
    {
        $name       = (string) $block['blockName'];
        $capability = $this->blocks->has($name) ? $this->blocks->get($name) : null;
        $attributes = isset($block['attrs']) && is_array($block['attrs']) ? $block['attrs'] : [];

        $node = [
            'name' => $name,
            'path' => $path,
        ];

        if (null === $capability) {
            $node['opaque'] = true;
        } else {
            $node['attributes'] = $this->filterAttributes($capability, $attributes);
        }

        $inner = isset($block['innerBlocks']) && is_array($block['innerBlocks']) ? $block['innerBlocks'] : [];

        if ($inner === []) {
            return $node;
        }

        if (count($path) >= $maxDepth) {
            $this->truncated      = true;
            $node['childrenOmitted'] = count($inner);

            return $node;
        }

        $node['children'] = $this->reduceList($inner, $path, $maxNodes, $maxDepth);

        return $node;
    }

    /**
     * @param \SwishfolioCore\Blocks\BlockCapability $capability Manifest.
     * @param array                                  $attributes Saved attributes.
     *
     * @return array
     */
    private function filterAttributes($capability, array $attributes)
    {
        $out = [];

        foreach (array_keys($capability->getFields()) as $attribute) {
            if (array_key_exists($attribute, $attributes)) {
                $out[$attribute] = $attributes[$attribute];
            }
        }

        return $out;
    }
}
