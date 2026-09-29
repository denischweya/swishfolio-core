<?php
/**
 * The eval assertions for the site and page generation track.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Tests\Evals\Support;

use SwishfolioCore\AI\Context\TokenResolver;
use SwishfolioCore\AI\Generation\PatternLibrary;
use SwishfolioCore\AI\Generation\Stages\PlanReferenceStage;
use SwishfolioCore\AI\Generation\PlanSchema;
use SwishfolioCore\Blocks\BlockRegistry;

/**
 * Generation has two surfaces where a model can go wrong and only one of them
 * looks like block editing.
 *
 * A "block" section is a dynamic block named directly, and is judged exactly as
 * an in-editor attribute write is. A "pattern" section is copy dropped into slots
 * in markup the plugin ships, and is judged against the unit's own slot list —
 * whose real teeth are the maximum lengths, because a headline twice its slot's
 * length does not fail, it wraps into four lines and ruins the section.
 *
 * The third surface is the composed page itself, which is the only place the two
 * meet, and is checked by parsing it.
 */
final class PlanAudit
{
    /** @var PatternLibrary */
    private $library;

    /** @var BlockRegistry */
    private $registry;

    /** @var TokenResolver */
    private $tokens;

    /** @var BlockCatalogue */
    private $catalogue;

    /** @var ManifestIndex */
    private $manifests;

    /** @var string[] Custom template names this theme declares. */
    private $customTemplates = [];

    /** @var string[] Style variation titles this theme ships. */
    private $styleVariations = [];

    public function __construct(
        PatternLibrary $library,
        BlockRegistry $registry,
        TokenResolver $tokens,
        BlockCatalogue $catalogue,
        ManifestIndex $manifests
    ) {
        $this->library   = $library;
        $this->registry  = $registry;
        $this->tokens    = $tokens;
        $this->catalogue = $catalogue;
        $this->manifests = $manifests;
    }

    /**
     * Facts that belong to the site rather than to the plugin: which templates
     * this theme declares and which style variations it ships. Without them a
     * template name and a variation title cannot be judged at all.
     *
     * @param string[] $customTemplates Template names.
     * @param string[] $styleVariations Variation titles.
     *
     * @return self
     */
    public function withSiteFacts(array $customTemplates, array $styleVariations)
    {
        $this->customTemplates = array_values(array_map('strval', $customTemplates));
        $this->styleVariations = array_values(array_map('strval', $styleVariations));

        return $this;
    }

    /**
     * @param array $plan Raw plan as the model produced it.
     *
     * @return string[] Violations; empty means the plan is acceptable.
     */
    public function auditProposal(array $plan)
    {
        $violations = [];
        $pages      = isset($plan['pages']) ? (array) $plan['pages'] : [];

        if ($pages === []) {
            return ['pages: the model planned nothing, so this case proves nothing.'];
        }

        $pageKeys = [];

        foreach ($pages as $page) {
            if (isset($page['key'])) {
                $pageKeys[(string) $page['key']] = true;
            }
        }

        foreach ($pages as $pageIndex => $page) {
            $path     = 'pages.' . $pageIndex;
            $sections = isset($page['sections']) ? (array) $page['sections'] : [];

            if (isset($page['status']) && (string) $page['status'] !== PlanSchema::STATUS) {
                $violations[] = sprintf(
                    '%s.status: "%s" — generated pages are always drafts.',
                    $path,
                    (string) $page['status']
                );
            }

            foreach ($this->auditTemplate($page, $path) as $violation) {
                $violations[] = $violation;
            }

            $wholePageUnits = 0;

            foreach ($sections as $sectionIndex => $section) {
                $where = $path . '.sections.' . $sectionIndex;

                if (isset($section['block'])) {
                    foreach ($this->auditBlockSection($section, $where) as $violation) {
                        $violations[] = $violation;
                    }
                    continue;
                }

                $unit = $this->library->get(isset($section['pattern']) ? (string) $section['pattern'] : '');

                if ($unit !== null && $unit->getScope() === 'page') {
                    $wholePageUnits++;
                }

                foreach ($this->auditPatternSection($section, $pageKeys, $where) as $violation) {
                    $violations[] = $violation;
                }
            }

            if ($wholePageUnits > 0 && count($sections) !== 1) {
                $violations[] = sprintf(
                    '%s.sections: a whole-page unit is an entire page body, so it cannot share a page.',
                    $path
                );
            }
        }

        foreach ($this->auditSite(isset($plan['site']) ? (array) $plan['site'] : [], $pageKeys) as $violation) {
            $violations[] = $violation;
        }

        return $violations;
    }

    /**
     * @param array  $page Page payload.
     * @param string $path Error path.
     *
     * @return string[]
     */
    private function auditTemplate(array $page, $path)
    {
        if (! isset($page['template']) || (string) $page['template'] === '') {
            return [];
        }

        $template = (string) $page['template'];

        if ($this->customTemplates === []) {
            return [sprintf('%s.template: "%s" cannot be verified — no template list was supplied.', $path, $template)];
        }

        if (! in_array($template, $this->customTemplates, true)) {
            return [sprintf(
                '%s.template: "%s" is not a template this theme declares. Declared: %s.',
                $path,
                $template,
                implode(', ', $this->customTemplates)
            )];
        }

        return [];
    }

    /**
     * @param array $site     Site block of the plan.
     * @param array $pageKeys Page keys declared by the plan.
     *
     * @return string[]
     */
    private function auditSite(array $site, array $pageKeys)
    {
        $violations = [];

        foreach (['frontPage', 'blogPage'] as $field) {
            if (! isset($site[$field]) || (string) $site[$field] === '') {
                continue;
            }

            if (! isset($pageKeys[(string) $site[$field]])) {
                $violations[] = sprintf('site.%s: names unknown page key "%s".', $field, (string) $site[$field]);
            }
        }

        foreach ((array) (isset($site['navigation']) ? $site['navigation'] : []) as $index => $key) {
            if (! isset($pageKeys[(string) $key])) {
                $violations[] = sprintf('site.navigation.%d: names unknown page key "%s".', $index, (string) $key);
            }
        }

        if (! isset($site['styleVariation']) || (string) $site['styleVariation'] === '') {
            return $violations;
        }

        $title = (string) $site['styleVariation'];

        if ($this->styleVariations === []) {
            $violations[] = sprintf('site.styleVariation: "%s" cannot be verified — no variation list was supplied.', $title);

            return $violations;
        }

        if (! in_array($title, $this->styleVariations, true)) {
            $violations[] = sprintf(
                'site.styleVariation: "%s" is not a variation this theme ships. Shipped: %s.',
                $title,
                implode(', ', $this->styleVariations)
            );
        }

        return $violations;
    }

    /**
     * Design tokens are only half-checked before validation: the model emits a
     * slug, and the value stage expands it to the CSS custom property a block
     * really stores. This proves the expansion happened, so a token that quietly
     * stayed a bare slug — and would render as nothing — is caught.
     *
     * @param array $raw       Raw plan.
     * @param array $validated Validated plan.
     *
     * @return string[]
     */
    public function auditTokenExpansion(array $raw, array $validated)
    {
        $violations = [];

        foreach ((array) $raw['pages'] as $pageIndex => $page) {
            foreach ((array) $page['sections'] as $sectionIndex => $section) {
                if (! isset($section['block'])) {
                    continue;
                }

                $capability = $this->registry->get((string) $section['block']);

                if ($capability === null) {
                    continue;
                }

                foreach ((array) $section['attributes'] as $attribute => $slug) {
                    $field = $capability->getField($attribute);

                    if ($field === null || $field->getType() !== 'token') {
                        continue;
                    }

                    $expected = $this->tokens->toStorageValue($field->getTokenSet(), (string) $slug);
                    $actual   = isset($validated['pages'][$pageIndex]['sections'][$sectionIndex]['attributes'][$attribute])
                        ? $validated['pages'][$pageIndex]['sections'][$sectionIndex]['attributes'][$attribute]
                        : null;

                    if ($actual !== $expected) {
                        $violations[] = sprintf(
                            'pages.%d.sections.%d.attributes.%s: token "%s" should be stored as "%s", got "%s".',
                            $pageIndex,
                            $sectionIndex,
                            $attribute,
                            (string) $slug,
                            $expected,
                            is_scalar($actual) ? (string) $actual : gettype($actual)
                        );
                    }
                }
            }
        }

        return $violations;
    }

    /**
     * @param array $validated Validated plan.
     * @param array $composed  Composed post_content, keyed by page index.
     *
     * @return string[]
     */
    public function auditComposedContent(array $validated, array $composed)
    {
        $violations = [];
        $pageKeys   = [];

        foreach ((array) $validated['pages'] as $page) {
            $pageKeys[(string) $page['key']] = true;
        }

        foreach ($composed as $pageIndex => $markup) {
            $where = sprintf('pages.%d.content', $pageIndex);

            if (trim((string) $markup) === '') {
                $violations[] = $where . ': composed to nothing.';
                continue;
            }

            if (strpos((string) $markup, '{{slot:') !== false) {
                $violations[] = $where . ': an unfilled slot token survived composition.';
            }

            preg_match_all('/\{\{page:([a-z0-9-]+)\}\}/', (string) $markup, $matches);

            foreach (array_unique($matches[1]) as $key) {
                if (! isset($pageKeys[$key])) {
                    $violations[] = sprintf('%s: internal link points at unknown page key "%s".', $where, $key);
                }
            }

            try {
                $trip = BlockMarkup::roundTrip((string) $markup);
            } catch (\RuntimeException $e) {
                $violations[] = sprintf('%s: does not parse as block markup — %s', $where, $e->getMessage());
                continue;
            }

            if (! $trip['identical']) {
                $violations[] = sprintf(
                    '%s: does not survive a parse and re-serialise; first byte differs at offset %d.',
                    $where,
                    (int) $trip['diffAt']
                );
            }

            foreach (array_unique(BlockMarkup::blockNames($trip['blocks'])) as $blockName) {
                if (! $this->catalogue->isVerifiable($blockName)) {
                    $violations[] = sprintf(
                        '%s: "%s" cannot be verified as registered — WordPress core is not reachable.',
                        $where,
                        $blockName
                    );
                    continue;
                }

                if (! $this->catalogue->isRegistered($blockName)) {
                    $violations[] = sprintf('%s: composes "%s", which is not a registered block.', $where, $blockName);
                }
            }
        }

        return $violations;
    }

    /**
     * @param array  $section Section payload.
     * @param string $where   Error path.
     *
     * @return string[]
     */
    private function auditBlockSection(array $section, $where)
    {
        $violations = [];
        $blockName  = (string) $section['block'];

        if (! in_array($blockName, PlanReferenceStage::DYNAMIC_BLOCKS, true)) {
            $violations[] = sprintf(
                '%s.block: "%s" is not a dynamic block, so PHP cannot produce its markup.',
                $where,
                $blockName
            );
        }

        if (! $this->catalogue->isVerifiable($blockName)) {
            $violations[] = sprintf('%s.block: "%s" cannot be verified as registered.', $where, $blockName);
        } elseif (! $this->catalogue->isRegistered($blockName)) {
            $violations[] = sprintf('%s.block: "%s" is not a registered block.', $where, $blockName);
        }

        $capability = $this->registry->get($blockName);

        if ($capability === null) {
            $violations[] = sprintf('%s.block: "%s" has no AI capability manifest.', $where, $blockName);

            return $violations;
        }

        if ($capability->getTier() !== 'full') {
            $violations[] = sprintf('%s.block: "%s" is content-tier and exposes no attributes.', $where, $blockName);
        }

        foreach (
            CapabilityAudit::auditAttributes(
                $capability,
                isset($section['attributes']) ? (array) $section['attributes'] : [],
                $this->tokens,
                $this->manifests,
                $where . '.attributes'
            ) as $violation
        ) {
            $violations[] = $violation;
        }

        return $violations;
    }

    /**
     * @param array  $section  Section payload.
     * @param array  $pageKeys Page keys declared by the plan.
     * @param string $where    Error path.
     *
     * @return string[]
     */
    private function auditPatternSection(array $section, array $pageKeys, $where)
    {
        $unitId = isset($section['pattern']) ? (string) $section['pattern'] : '';
        $unit   = $this->library->get($unitId);

        if ($unit === null) {
            return [sprintf('%s.pattern: "%s" is not a generation unit in the catalogue.', $where, $unitId)];
        }

        $violations = [];

        if ($unit->getRequiresMedia() > 0) {
            $violations[] = sprintf('%s.pattern: "%s" needs images, which generation cannot supply.', $where, $unitId);
        }

        if (! $unit->isKsesStable()) {
            $violations[] = sprintf('%s.pattern: "%s" does not survive content filtering on save.', $where, $unitId);
        }

        foreach ((array) (isset($section['content']) ? $section['content'] : []) as $name => $value) {
            $path = $where . '.content.' . $name;
            $slot = $unit->getSlot((string) $name);

            if ($slot === null) {
                $violations[] = sprintf('%s: unit "%s" declares no slot "%s".', $path, $unitId, (string) $name);
                continue;
            }

            if (! is_string($value)) {
                $violations[] = sprintf('%s: slot values must be strings, got %s.', $path, gettype($value));
                continue;
            }

            if ($slot->getType() === 'url') {
                foreach ($this->auditSlotUrl($value, $pageKeys, $path) as $violation) {
                    $violations[] = $violation;
                }
                continue;
            }

            $max = $slot->getMaxLength();

            if ($max !== null && CapabilityAudit::length($value) > $max) {
                $violations[] = sprintf(
                    '%s: %d characters exceeds the slot maximum of %d.',
                    $path,
                    CapabilityAudit::length($value),
                    $max
                );
            }

            $literal = CapabilityAudit::literalReason($value);

            if ($literal !== null) {
                $violations[] = sprintf('%s: "%s" is %s, not copy.', $path, $value, $literal);
            }

            if ($slot->isInComment()) {
                foreach (['<', '>', '&', '"', '\\', '--'] as $needle) {
                    if (strpos($value, $needle) !== false) {
                        $violations[] = sprintf(
                            '%s: this slot is stored inside a block comment and cannot contain "%s".',
                            $path,
                            $needle
                        );
                    }
                }
            }
        }

        return $violations;
    }

    /**
     * @param string $value    Slot value.
     * @param array  $pageKeys Page keys declared by the plan.
     * @param string $path     Error path.
     *
     * @return string[]
     */
    private function auditSlotUrl($value, array $pageKeys, $path)
    {
        if (preg_match('/^\{\{page:([a-z0-9][a-z0-9-]{0,39})\}\}$/', $value, $matches) === 1) {
            if (! isset($pageKeys[$matches[1]])) {
                return [sprintf('%s: internal link names unknown page key "%s".', $path, $matches[1])];
            }

            return [];
        }

        if (strpos($value, '{{page:') === 0) {
            return [sprintf('%s: "%s" is not a usable internal page reference.', $path, $value)];
        }

        return CapabilityAudit::auditUrl($value, $path);
    }
}
