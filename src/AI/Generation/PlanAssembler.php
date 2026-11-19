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
 * What it must not do is put pattern markup in the prompt. A kit's sections are
 * a few hundred KB of markup; their CopySlots outlines (the copy, tagged with
 * slot ids) are a fraction of that, and are all a planner needs to choose a
 * section and rewrite it. Only one kit is ever described: the one the run uses.
 *
 * Ordering follows ContextAssembler's: the catalogue and the design tokens are
 * byte-stable for a given site and go in the system prompt where a provider's
 * cache can hold them; the brief is volatile and goes in the user message.
 */
class PlanAssembler
{
    /** @var Kit */
    private $kit;

    /** @var BlockRegistry */
    private $registry;

    /** @var TokenResolver */
    private $tokens;

    /** @var PromptManager */
    private $prompts;

    /** @var array Brand context from Swish AI settings. */
    private $brand = [];

    public function __construct(
        Kit $kit,
        BlockRegistry $registry,
        TokenResolver $tokens,
        PromptManager $prompts,
        array $brand = []
    ) {
        $this->kit      = $kit;
        $this->registry = $registry;
        $this->tokens   = $tokens;
        $this->prompts  = $prompts;
        $this->brand    = $brand;
    }

    /**
     * @param array  $brief Brief: instruction, site facts.
     * @param string $model Model identifier.
     *
     * @return AIRequest
     */
    public function forSitePlan(array $brief, $model)
    {
        // Every slot of every section is rewritten in this one reply, so a
        // five-page site runs to tens of thousands of output tokens.
        return new AIRequest(
            $this->systemPrompt('site-generation', 'site', $brief),
            $this->prompts->task('plan-site', [
                'instruction' => (string) (isset($brief['instruction']) ? $brief['instruction'] : ''),
                'site'        => $this->encode($this->describeSite($brief)),
                'kit'         => $this->kit->getSlug(),
            ]),
            PlanSchema::build('site', PlanSchema::MAX_PAGES, $this->kit->getSlug()),
            $model,
            32000
        );
    }

    /**
     * @param array  $brief Brief: instruction, site facts, page.
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
                'kit'         => $this->kit->getSlug(),
            ]),
            PlanSchema::build('page', 1, $this->kit->getSlug()),
            $model,
            16000
        );
    }

    /**
     * One follow-up call for the slots a plan left holding demo copy.
     *
     * @param array   $brief Brief.
     * @param array   $plan  Plan so far (envelope-normalised).
     * @param array[] $gaps  PlanCoverage::missing().
     * @param string  $model Model identifier.
     *
     * @return AIRequest
     */
    public function forCopyFill(array $brief, array $plan, array $gaps, $model)
    {
        $lines = [];

        foreach ($gaps as $gap) {
            $lines[] = sprintf('%d | %d | %s | %s | %s', $gap['page'], $gap['section'], $gap['unit'], $gap['slot'], $gap['demo']);
        }

        $item = [
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => ['page', 'section', 'slot', 'html'],
            'properties'           => [
                'page'    => ['type' => 'integer'],
                'section' => ['type' => 'integer'],
                'slot'    => ['type' => 'string'],
                'html'    => ['type' => 'string'],
            ],
        ];

        return new AIRequest(
            $this->systemPrompt(isset($plan['site']) ? 'site-generation' : 'page-generation', isset($plan['site']) ? 'site' : 'page', $brief),
            $this->prompts->task('fill-copy', [
                'instruction' => (string) (isset($brief['instruction']) ? $brief['instruction'] : ''),
                'plan'        => $this->encode(['pages' => $plan['pages']]),
                'gaps'        => implode("\n", $lines),
            ]),
            [
                'type'                 => 'object',
                'additionalProperties' => false,
                'required'             => ['fills'],
                'properties'           => ['fills' => ['type' => 'array', 'items' => $item]],
            ],
            $model,
            8192
        );
    }

    /**
     * The site plan's system prompt (instructions, kit catalogue, tokens,
     * limits, brand, custom design): the prefix every call of a staged site
     * plan shares, so a provider can cache it once per build.
     *
     * @param array $brief Brief.
     *
     * @return string
     */
    public function siteSystemPrompt(array $brief)
    {
        return $this->systemPrompt('site-generation', 'site', $brief);
    }

    /**
     * @param array $brief Brief.
     *
     * @return string JSON site facts, as the plan prompts show them.
     */
    public function siteFactsJson(array $brief)
    {
        return $this->encode($this->describeSite($brief));
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

        $limits = [
            'maxPages'            => $scope === 'page' ? 1 : PlanSchema::MAX_PAGES,
            'maxSectionsPerPage'  => PlanSchema::MAX_SECTIONS_PER_PAGE,
            'maxSectionsTotal'    => PlanSchema::MAX_SECTIONS_TOTAL,
            'conflictStrategies'  => PlanSchema::CONFLICT_STRATEGIES,
        ];

        $segments = [
            $this->prompts->system($systemPrompt),
            "The kit you compose pages out of:\n" . $this->kit->catalogue(),
            "Dynamic blocks you may also place as a section:\n" . $this->encode($this->describeDynamicBlocks()),
            "Design tokens for dynamic block attributes. Use these slugs, never literal values:\n" . $this->encode($this->describeTokens()),
            "Limits you must respect:\n" . $this->encode($limits),
        ];

        // Generation is where brand context earns its keep: without it every
        // generated site reads like the same anonymous filler.
        $brand = $this->describeBrand();

        if ($brand !== '') {
            $segments[] = $brand;
        }

        $design = isset($brief['design']) ? trim((string) $brief['design']) : '';

        if ($design !== '') {
            $segments[] = "Custom visual design. This site gets its own new look instead of the kit's (colours, fonts and shapes are replaced after you plan), described by the owner as:\n" . $design
                . "\nPlan for that look: prefer sections that suit it, leave out units whose decoration would clash with it (scrolling marquees or tickers for a calm or minimal look, ornamental script or arch motifs for a hard-edged one), and write copy whose tone and rhythm match it. Do not describe the look in the copy.";
        }

        return implode("\n\n", $segments);
    }

    /**
     * The site's brand context, as prose. Empty fields are omitted.
     *
     * @return string
     */
    private function describeBrand()
    {
        $labels = [
            'siteName'        => 'Business name',
            'siteDescription' => 'What the business does',
            'designStyle'     => 'Preferred design style',
            'blockShapes'     => 'Preferred corner treatment',
            'copywritingTone' => 'Copywriting tone',
        ];

        $lines = [];

        foreach ($labels as $key => $label) {
            $value = isset($this->brand[$key]) ? trim((string) $this->brand[$key]) : '';

            if ($value !== '') {
                $lines[] = $label . ': ' . $value;
            }
        }

        if ($lines === []) {
            return '';
        }

        return "About this business. Write copy for it specifically, never generic placeholder text, and respect these preferences:\n"
            . implode("\n", $lines);
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
            'customTemplates' => isset($brief['customTemplates']) ? array_values((array) $brief['customTemplates']) : [],
            'existingSlugs'   => isset($brief['existingSlugs']) ? array_values((array) $brief['existingSlugs']) : [],
        ];
    }

    private function encode(array $data)
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
