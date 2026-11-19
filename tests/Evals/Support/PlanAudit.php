<?php
/**
 * The eval assertions for the site and page generation track.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Tests\Evals\Support;

use SwishfolioCore\AI\Context\TokenResolver;
use SwishfolioCore\AI\Generation\Kit;
use SwishfolioCore\AI\Generation\Stages\PlanReferenceStage;
use SwishfolioCore\AI\Generation\PlanSchema;
use SwishfolioCore\Blocks\BlockRegistry;

/**
 * Generation has two surfaces where a model can go wrong and only one of them
 * looks like block editing.
 *
 * A "block" section is a dynamic block named directly, and is judged exactly as
 * an in-editor attribute write is. A "pattern" section is copy dropped into the
 * slots of a kit unit (a template's own section), and is judged against that
 * unit's slots: every slot holding demo copy must be rewritten or removed, and
 * copy far longer than the demo it replaces is flagged, because a headline
 * twice its slot's length does not fail, it wraps into four lines and ruins
 * the section.
 *
 * The third surface is the composed page itself, which is the only place the two
 * meet, and is checked by parsing it.
 */
final class PlanAudit
{
    /** @var Kit */
    private $kit;

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

    public function __construct(
        Kit $kit,
        BlockRegistry $registry,
        TokenResolver $tokens,
        BlockCatalogue $catalogue,
        ManifestIndex $manifests
    ) {
        $this->kit       = $kit;
        $this->registry  = $registry;
        $this->tokens    = $tokens;
        $this->catalogue = $catalogue;
        $this->manifests = $manifests;
    }

    /**
     * Facts that belong to the site rather than to the plugin: which templates
     * this theme declares. Without them a template name cannot be judged.
     *
     * @param string[] $customTemplates Template names.
     *
     * @return self
     */
    public function withSiteFacts(array $customTemplates)
    {
        $this->customTemplates = array_values(array_map('strval', $customTemplates));

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

        $pageKeys   = [];

        if (isset($plan['kit']) && (string) $plan['kit'] !== $this->kit->getSlug()) {
            $violations[] = sprintf('kit: the plan names "%s" but was asked for "%s".', (string) $plan['kit'], $this->kit->getSlug());
        }

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

            foreach ($sections as $sectionIndex => $section) {
                $where = $path . '.sections.' . $sectionIndex;

                if (isset($section['block'])) {
                    foreach ($this->auditBlockSection($section, $where) as $violation) {
                        $violations[] = $violation;
                    }
                    continue;
                }

                foreach ($this->auditPatternSection($section, $where) as $violation) {
                    $violations[] = $violation;
                }
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

            if (preg_match('/\{\{(kit-[a-z-]+|link|nav|slot|art)[:}]/', (string) $markup) === 1) {
                $violations[] = $where . ': a kit placeholder survived composition.';
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
     * @param array  $section Section payload.
     * @param string $where   Error path.
     *
     * @return string[]
     */
    private function auditPatternSection(array $section, $where)
    {
        $unitId = isset($section['pattern']) ? (string) $section['pattern'] : '';

        if ($this->kit->section($unitId) === null) {
            return [sprintf('%s.pattern: "%s" is not a section in the %s kit.', $where, $unitId, $this->kit->getSlug())];
        }

        $slots      = $this->kit->slots($unitId)->slots();
        $units      = $this->kit->slots($unitId)->units();
        $violations = [];
        $written    = [];

        foreach (['texts' => ['html', ['text', 'run']], 'attrs' => ['value', ['attr']]] as $key => $spec) {
            foreach ((array) (isset($section[$key]) ? $section[$key] : []) as $index => $pair) {
                $path  = $where . '.' . $key . '.' . $index;
                $id    = is_array($pair) && isset($pair['id']) ? (string) $pair['id'] : '';
                $value = is_array($pair) && isset($pair[$spec[0]]) ? $pair[$spec[0]] : null;

                if (! isset($slots[$id]) || ! in_array($slots[$id]['type'], $spec[1], true)) {
                    $violations[] = sprintf('%s: unit "%s" has no %s slot "%s".', $path, $unitId, $key === 'attrs' ? 'attribute' : 'text', $id);
                    continue;
                }

                if (! is_string($value)) {
                    $violations[] = sprintf('%s: slot values must be strings, got %s.', $path, gettype($value));
                    continue;
                }

                $written[$id] = true;

                foreach ($this->auditSlotValue($value, $slots[$id], $path) as $violation) {
                    $violations[] = $violation;
                }
            }
        }

        $removed = [];

        foreach ((array) (isset($section['remove']) ? $section['remove'] : []) as $index => $id) {
            if (! isset($units[(string) $id])) {
                $violations[] = sprintf('%s.remove.%d: unit "%s" has no "%s" to remove.', $where, $index, $unitId, (string) $id);
                continue;
            }

            $removed[] = $units[(string) $id];
        }

        foreach ($this->kit->requiredSlots($unitId) as $id) {
            if (isset($written[$id])) {
                continue;
            }

            foreach ($removed as $unit) {
                if ($slots[$id]['start'] >= $unit['start'] && $slots[$id]['end'] <= $unit['end']) {
                    continue 2;
                }
            }

            $violations[] = sprintf('%s: slot "%s" of "%s" still holds the demo copy "%s".', $where, $id, $unitId, self::plain($slots[$id]['value']));
        }

        return $violations;
    }

    /**
     * @param string $value Copy for one slot.
     * @param array  $slot  The CopySlots slot it replaces.
     * @param string $path  Error path.
     *
     * @return string[]
     */
    private function auditSlotValue($value, array $slot, $path)
    {
        $violations = [];
        $plain      = self::plain($value);
        $demo       = self::plain($slot['value']);

        if ($plain === '') {
            $violations[] = $path . ': empty copy; the unit should be removed instead.';
        }

        if (CapabilityAudit::length($plain) > 40 && CapabilityAudit::length($plain) > 2.5 * CapabilityAudit::length($demo)) {
            $violations[] = sprintf(
                '%s: %d characters replaces %d of demo copy, so it will not fit the design.',
                $path,
                CapabilityAudit::length($plain),
                CapabilityAudit::length($demo)
            );
        }

        preg_match_all('/\{\{[^}]*\}\}/', $value, $placeholders);

        foreach ($placeholders[0] as $placeholder) {
            if (strpos((string) $slot['value'], $placeholder) === false) {
                $violations[] = sprintf('%s: "%s" is not a placeholder the demo copy had.', $path, $placeholder);
            }
        }

        if ($slot['type'] === 'attr' && isset($slot['attr']) && $slot['attr'] === 'href'
            && preg_match('/^(tel:\+?[0-9]{3,20}|mailto:[^@\s]+@[^@\s]+)$/', $value) !== 1) {
            $violations[] = sprintf('%s: "%s" is not a tel: or mailto: address.', $path, $value);
        }

        $literal = CapabilityAudit::literalReason($plain);

        if ($literal !== null) {
            $violations[] = sprintf('%s: "%s" is %s, not copy.', $path, $plain, $literal);
        }

        if (preg_match_all('/href="([^"]*)"/', $value, $hrefs)) {
            foreach ($hrefs[1] as $href) {
                if (strpos($href, '{{link:') === 0 || strpos($href, '#') === 0 || preg_match('/^(tel|mailto):/', $href)) {
                    continue;
                }

                foreach (CapabilityAudit::auditUrl($href, $path) as $violation) {
                    $violations[] = $violation;
                }
            }
        }

        return $violations;
    }

    /**
     * @param string $html Slot HTML.
     *
     * @return string Its text.
     */
    private static function plain($html)
    {
        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags((string) $html), ENT_QUOTES, 'UTF-8')));
    }
}
