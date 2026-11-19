<?php
/**
 * Serves the block capability manifests on demand.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Tools\Read;

use SwishfolioCore\Blocks\BlockRegistry;
use SwishfolioCore\Tools\ToolContext;

/**
 * ContextAssembler injects manifests into the prompt for the editing path, which
 * is right there: the selection is known before the request is made.
 *
 * Nothing is known in advance when the model is driving, and injecting all 28
 * manifests to cover that costs tokens on every call. Called with no arguments
 * this returns the index — every block's name, purpose and tier, a few words
 * each — and called with names it returns those manifests in full. The model
 * pays for the two or three blocks it actually uses.
 */
class GetBlockCapabilitiesTool extends AbstractReadTool
{
    const MAX_BLOCKS = 10;

    /** @var BlockRegistry */
    private $blocks;

    /**
     * @param BlockRegistry $blocks Capability manifests.
     */
    public function __construct(BlockRegistry $blocks)
    {
        $this->blocks = $blocks;
    }

    public function getName()
    {
        return 'swishfolio/get-block-capabilities';
    }

    public function getTitle()
    {
        return __('Get block capabilities', 'swishfolio-core');
    }

    public function getDescription()
    {
        return 'List which blocks this site offers and what each is for. Call it with no arguments for the '
            . 'index, then with up to 10 block names for their full editable attributes, allowed values and '
            . 'child-block rules. Only attributes listed here can be set; anything else is rejected.';
    }

    public function getInputSchema()
    {
        return [
            'type'                 => 'object',
            'additionalProperties' => false,
            'properties'           => [
                'blocks' => [
                    'type'        => 'array',
                    'maxItems'    => self::MAX_BLOCKS,
                    'items'       => ['type' => 'string', 'maxLength' => 100],
                    'description' => 'Block names to describe in full, e.g. "swishfolio-core/hero".',
                ],
            ],
        ];
    }

    public function execute(array $input, ToolContext $context)
    {
        $requested = isset($input['blocks']) && is_array($input['blocks']) ? $input['blocks'] : [];

        if ($requested === []) {
            return ['index' => $this->index()];
        }

        $found   = [];
        $unknown = [];

        foreach ($requested as $name) {
            $name = (string) $name;

            if (! $this->blocks->has($name)) {
                $unknown[] = $name;

                continue;
            }

            $found[$name] = $this->detail($this->blocks->get($name));
        }

        $out = ['blocks' => $found];

        if ($unknown !== []) {
            $out['unknown'] = $unknown;
        }

        return $out;
    }

    /**
     * @return array[]
     */
    private function index()
    {
        $out = [];

        foreach ($this->blocks->all() as $name => $capability) {
            $out[] = [
                'name'    => $name,
                'tier'    => $capability->getTier(),
                'purpose' => $capability->getPurpose(),
            ];
        }

        return $out;
    }

    /**
     * @param \SwishfolioCore\Blocks\BlockCapability $capability Manifest.
     *
     * @return array
     */
    private function detail($capability)
    {
        $fields = [];

        foreach ($capability->getFields() as $attribute => $field) {
            $spec = [
                'type'  => $field->getType(),
                'group' => $field->getGroup(),
            ];

            foreach (
                [
                    'enum'      => $field->getEnum(),
                    'min'       => $field->getMin(),
                    'max'       => $field->getMax(),
                    'maxLength' => $field->getMaxLength(),
                    'tokenSet'  => $field->getTokenSet(),
                    'unit'      => $field->getUnit(),
                    'role'      => $field->getRole(),
                ] as $key => $value
            ) {
                if (null !== $value && [] !== $value && '' !== $value) {
                    $spec[$key] = $value;
                }
            }

            $fields[$attribute] = $spec;
        }

        return [
            'tier'       => $capability->getTier(),
            'purpose'    => $capability->getPurpose(),
            'summary'    => $capability->getSummary(),
            'attributes' => $fields,
            'children'   => [
                'allowed' => $capability->getAllowedChildren(),
                'min'     => $capability->getChildMin(),
                'max'     => $capability->getChildMax(),
            ],
        ];
    }
}
