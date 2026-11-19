<?php
/**
 * Stage 2: every reference in the plan resolves to something real.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Generation\Stages;

use SwishfolioCore\AI\Generation\PlanContext;
use SwishfolioCore\Actions\ValidationException;

/**
 * Only dynamic blocks may be named directly. A static block serialised from
 * attributes alone renders to nothing, because PHP cannot run its JavaScript
 * save() — so the plan is not allowed to ask for one, and a static block is not
 * absent by rule here, it is absent from the allowlist and therefore unreachable.
 *
 * swishfolio-core/swish-form is dynamic but excluded: its fields are an array
 * attribute, arrays are in no capability manifest, and a form with no fields is
 * worse than no form.
 */
class PlanReferenceStage implements PlanStageInterface
{
    const DYNAMIC_BLOCKS = [
        'swishfolio-core/portfolio',
        'swishfolio-core/latest-posts',
    ];

    public function getSlug()
    {
        return 'plan-reference';
    }

    public function process(array $plan, PlanContext $context)
    {
        $allowedBlocks = $this->allowedBlocks();
        $pageKeys      = [];

        foreach ($plan['pages'] as $page) {
            $pageKeys[$page['key']] = true;
        }

        foreach ($plan['pages'] as $index => $page) {
            $path = 'pages.' . $index;

            $this->checkTemplate($page, $context, $path);
            $this->checkSections($page, $context, $allowedBlocks, $pageKeys, $path);
        }

        if (isset($plan['site'])) {
            $this->checkSite($plan['site'], $pageKeys);
        }

        return $plan;
    }

    /**
     * @return string[]
     */
    private function allowedBlocks()
    {
        /**
         * Filters the dynamic blocks a generated page may contain.
         *
         * @param string[] $blocks Block names.
         */
        return (array) apply_filters('sfcore_generation_dynamic_blocks', self::DYNAMIC_BLOCKS);
    }

    private function checkTemplate(array $page, PlanContext $context, $path)
    {
        if (! isset($page['template']) || (string) $page['template'] === '') {
            return;
        }

        if (! in_array((string) $page['template'], $context->getCustomTemplates(), true)) {
            throw new ValidationException(
                $this->getSlug(),
                sprintf('"%s" is not a custom template this theme declares.', (string) $page['template']),
                $path . '.template'
            );
        }
    }

    private function checkSections(array $page, PlanContext $context, array $allowedBlocks, array $pageKeys, $path)
    {
        $kit = $context->getKit();

        foreach ($page['sections'] as $index => $section) {
            $sectionPath = $path . '.sections.' . $index;

            if (isset($section['block'])) {
                $this->checkBlock($section['block'], $context, $allowedBlocks, $sectionPath);
                continue;
            }

            // Only this kit's sections: another kit's markup would render
            // without the CSS it was designed for.
            if ($kit->section($section['pattern']) === null) {
                throw new ValidationException(
                    $this->getSlug(),
                    sprintf('"%s" is not a section in the "%s" kit.', (string) $section['pattern'], $kit->getSlug()),
                    $sectionPath . '.pattern'
                );
            }
        }
    }

    private function checkBlock($blockName, PlanContext $context, array $allowedBlocks, $path)
    {
        if (! in_array((string) $blockName, $allowedBlocks, true)) {
            throw new ValidationException(
                $this->getSlug(),
                sprintf('Block "%s" cannot be generated server-side.', (string) $blockName),
                $path . '.block'
            );
        }

        $capability = $context->getRegistry()->get($blockName);

        if ($capability === null || $capability->getTier() !== 'full') {
            throw new ValidationException(
                $this->getSlug(),
                sprintf('Block "%s" has no AI capability manifest.', (string) $blockName),
                $path . '.block'
            );
        }
    }

    private function checkSite(array $site, array $pageKeys)
    {
        foreach (['frontPage', 'blogPage'] as $field) {
            if (! isset($site[$field]) || (string) $site[$field] === '') {
                continue;
            }

            if (! isset($pageKeys[(string) $site[$field]])) {
                throw new ValidationException(
                    $this->getSlug(),
                    sprintf('Site %s names unknown page key "%s".', $field, (string) $site[$field]),
                    'site.' . $field
                );
            }
        }

        foreach ($site['navigation'] as $index => $key) {
            if (! isset($pageKeys[$key])) {
                throw new ValidationException(
                    $this->getSlug(),
                    sprintf('Navigation item names unknown page key "%s".', $key),
                    'site.navigation.' . $index
                );
            }
        }
    }
}
