<?php
/**
 * Builds the AI request for planning a site or a page.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Generation;

use SwishfolioCore\AI\AIRequest;
use SwishfolioCore\AI\Context\TokenResolver;
use SwishfolioCore\AI\Prompt\PromptManager;
use SwishfolioCore\AI\Generation\Stages\PlanReferenceStage;
use SwishfolioCore\Blocks\BlockRegistry;

/**
 * The sibling of ContextAssembler, on the same economy. ContextAssembler answers
 * "what may I change about this one selected block"; this answers "what may I
 * build a site out of", and the two have no overlapping work to share: different
 * prompt, different schema, different catalogue.
 *
 * What it must not do is put pattern markup in the prompt. The eleven registered
 * patterns are about 166 KB of markup between them, roughly fifty thousand
 * tokens. A role, a summary, a capacity and a slot list per unit is a few hundred
 * bytes each, and is all a planner needs to choose.
 *
 * Ordering follows ContextAssembler's: the catalogue and the design tokens are
 * byte-stable for a given site and go in the system prompt where a provider's
 * cache can hold them; the brief is volatile and goes in the user message.
 */
class PlanAssembler
{
    /** @var PatternLibrary */
    private $library;

    /** @var BlockRegistry */
    private $registry;

    /** @var TokenResolver */
    private $tokens;

    /** @var PromptManager */
    private $prompts;

    public function __construct(
        PatternLibrary $library,
        BlockRegistry $registry,
        TokenResolver $tokens,
        PromptManager $prompts
    ) {
        $this->library  = $library;
        $this->registry = $registry;
        $this->tokens   = $tokens;
        $this->prompts  = $prompts;
    }

    /**
     * @param array  $brief Brief: instruction, styleVariation, site facts.
     * @param string $model Model identifier.
     *
     * @return AIRequest
     */
    public function forSitePlan(array $brief, $model)
    {
        return new AIRequest(
            $this->systemPrompt('site-generation', 'site', $brief),
            $this->prompts->task('plan-site', [
                'instruction' => (string) (isset($brief['instruction']) ? $brief['instruction'] : ''),
                'site'        => $this->encode($this->describeSite($brief)),
            ]),
            PlanSchema::build('site'),
            $model,
            8192
        );
    }

    /**
     * @param array  $brief Brief: instruction, styleVariation, site facts, page.
     * @param string $model Model identifier.
     *
     * @return AIRequest
     */
    public function forPagePlan(array $brief, $model)
    {
        return new AIRequest(
            $this->systemPrompt('page-generation', 'page', $brief),
            $this->prompts->task('generate-page', [
                'instruction' => (string) (isset($brief['instruction']) ? $brief['instruction'] : ''),
                'site'        => $this->encode($this->describeSite($brief)),
                'page'        => $this->encode(isset($brief['page']) ? (array) $brief['page'] : []),
            ]),
            PlanSchema::build('page'),
            $model,
            6144
        );
    }

    /**
     * @param string $systemPrompt Prompt file name.
     * @param string $scope        "site" or "page".
     * @param array  $brief        Brief.
     *
     * @return string
     */
    private function systemPrompt($systemPrompt, $scope, array $brief)
    {
        $variation = isset($brief['styleVariation']) && $brief['styleVariation'] !== ''
            ? (string) $brief['styleVariation']
            : null;

        $catalogue = [
            'wholePageUnits' => $this->library->describe('page', $variation),
            'sectionUnits'   => $this->library->describe('section', $variation),
            'dynamicBlocks'  => $this->describeDynamicBlocks(),
        ];

        $limits = [
            'maxPages'            => $scope === 'page' ? 1 : PlanSchema::MAX_PAGES,
            'maxSectionsPerPage'  => PlanSchema::MAX_SECTIONS_PER_PAGE,
            'maxSectionsTotal'    => PlanSchema::MAX_SECTIONS_TOTAL,
            'conflictStrategies'  => PlanSchema::CONFLICT_STRATEGIES,
        ];

        return implode("\n\n", [
            $this->prompts->system($systemPrompt),
            "What you may compose pages out of:\n" . $this->encode($catalogue),
            "Design tokens available in this theme. Use these slugs, never literal values:\n" . $this->encode($this->describeTokens()),
            "Limits you must respect:\n" . $this->encode($limits),
        ]);
    }

    /**
     * Only the blocks a plan is allowed to name, with only the fields it may set.
     *
     * @return array
     */
    private function describeDynamicBlocks()
    {
        $described = [];

        foreach (PlanReferenceStage::DYNAMIC_BLOCKS as $blockName) {
            $capability = $this->registry->get($blockName);

            if ($capability === null) {
                continue;
            }

            $fields = [];

            foreach ($capability->getFields() as $attribute => $field) {
                $spec = ['type' => $field->getType()];

                if ($field->getEnum() !== []) {
                    $spec['enum'] = $field->getEnum();
                }
                if ($field->getTokenSet() !== null) {
                    $spec['tokenSet'] = $field->getTokenSet();
                }
                if ($field->getMin() !== null) {
                    $spec['min'] = $field->getMin();
                }
                if ($field->getMax() !== null) {
                    $spec['max'] = $field->getMax();
                }
                if ($field->getMaxLength() !== null) {
                    $spec['maxLength'] = $field->getMaxLength();
                }

                $fields[$attribute] = $spec;
            }

            $described[$blockName] = [
                'purpose'    => $capability->getPurpose(),
                'summary'    => $capability->getSummary(),
                'attributes' => $fields,
            ];
        }

        ksort($described);

        return $described;
    }

    /**
     * @return array
     */
    private function describeTokens()
    {
        $tokens = [];

        foreach (['color', 'spacing', 'fontSize'] as $set) {
            $tokens[$set] = $this->tokens->getSlugs($set);
        }

        return $tokens;
    }

    /**
     * @param array $brief Brief.
     *
     * @return array
     */
    private function describeSite(array $brief)
    {
        return [
            'siteTitle'       => isset($brief['siteTitle']) ? (string) $brief['siteTitle'] : '',
            'styleVariation'  => isset($brief['styleVariation']) ? (string) $brief['styleVariation'] : '',
            'styleVariations' => isset($brief['styleVariations']) ? array_values((array) $brief['styleVariations']) : [],
            'customTemplates' => isset($brief['customTemplates']) ? array_values((array) $brief['customTemplates']) : [],
            'existingSlugs'   => isset($brief['existingSlugs']) ? array_values((array) $brief['existingSlugs']) : [],
        ];
    }

    private function encode(array $data)
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
