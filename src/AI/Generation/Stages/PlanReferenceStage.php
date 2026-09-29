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
        $library    = $context->getLibrary();
        $pageScoped = 0;

        foreach ($page['sections'] as $index => $section) {
            $sectionPath = $path . '.sections.' . $index;

            if (isset($section['block'])) {
                $this->checkBlock($section['block'], $context, $allowedBlocks, $sectionPath);
                continue;
            }

            $unit = $library->get($section['pattern']);

            if ($unit === null) {
                throw new ValidationException(
                    $this->getSlug(),
                    sprintf('"%s" is not a generation unit in the catalogue.', (string) $section['pattern']),
                    $sectionPath . '.pattern'
                );
            }

            if ($unit->getRequiresMedia() > 0) {
                throw new ValidationException(
                    $this->getSlug(),
                    sprintf('Unit "%s" needs images, and image handling is not available.', $unit->getId()),
                    $sectionPath . '.pattern'
                );
            }

            if (! $unit->isKsesStable()) {
                throw new ValidationException(
                    $this->getSlug(),
                    sprintf('Unit "%s" does not survive content filtering on save.', $unit->getId()),
                    $sectionPath . '.pattern'
                );
            }

            if ($unit->getScope() === 'page') {
                $pageScoped++;
            }

            $this->checkPageLinks($unit, $section, $pageKeys, $sectionPath);
        }

        if ($pageScoped > 0 && count($page['sections']) !== 1) {
            throw new ValidationException(
                $this->getSlug(),
                'A whole-page unit must be the only section on its page.',
                $path . '.sections'
            );
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

    /**
     * A slot value of the form {{page:about}} becomes a permalink at execution
     * time, once the page it names actually exists and has an ID.
     */
    private function checkPageLinks($unit, array $section, array $pageKeys, $path)
    {
        foreach ($section['content'] as $name => $value) {
            if (! is_string($value) || strpos($value, '{{page:') !== 0) {
                continue;
            }

            if (! preg_match('/^\{\{page:([a-z0-9][a-z0-9-]{0,39})\}\}$/', $value, $matches)) {
                throw new ValidationException(
                    $this->getSlug(),
                    sprintf('"%s" is not a usable internal page reference.', $value),
                    $path . '.content.' . $name
                );
            }

            if (! isset($pageKeys[$matches[1]])) {
                throw new ValidationException(
                    $this->getSlug(),
                    sprintf('Internal link points at unknown page key "%s".', $matches[1]),
                    $path . '.content.' . $name
                );
            }

            $slot = $unit->getSlot($name);

            if ($slot !== null && $slot->getType() !== 'url') {
                throw new ValidationException(
                    $this->getSlug(),
                    sprintf('Slot "%s" is not a link, so it cannot reference a page.', $name),
                    $path . '.content.' . $name
                );
            }
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
