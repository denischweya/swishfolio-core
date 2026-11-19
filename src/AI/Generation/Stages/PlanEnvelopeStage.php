<?php
/**
 * Stage 1: the plan envelope is well-formed and within its caps.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Generation\Stages;

use SwishfolioCore\AI\Generation\PlanContext;
use SwishfolioCore\AI\Generation\PlanSchema;
use SwishfolioCore\Actions\ValidationException;

/**
 * This stage is also the gate that reduces a plan to known keys. A plan reaching
 * /ai/generate has been through a review UI and is untrusted in exactly the way
 * an AI response is; anything not named here is discarded rather than carried
 * forward, so no later stage can be influenced by a field it never expected.
 */
class PlanEnvelopeStage implements PlanStageInterface
{
    const PAGE_KEYS    = ['key', 'title', 'slug', 'status', 'template', 'onConflict', 'sections'];
    const SECTION_KEYS = ['pattern', 'texts', 'attrs', 'remove', 'clones', 'block', 'attributes'];
    const SITE_KEYS    = ['title', 'tagline', 'frontPage', 'blogPage', 'navigation'];

    /** Caps on what one section may carry, so a plan cannot balloon. */
    const MAX_EDITS  = 200;
    const MAX_CLONES = 24;

    public function getSlug()
    {
        return 'plan-envelope';
    }

    public function process(array $plan, PlanContext $context)
    {
        $version = isset($plan['schemaVersion']) ? $plan['schemaVersion'] : null;

        if ($version !== PlanSchema::SCHEMA_VERSION) {
            throw new ValidationException(
                $this->getSlug(),
                sprintf('Unsupported schema version "%s".', (string) $version),
                'schemaVersion'
            );
        }

        if (! isset($plan['pages']) || ! is_array($plan['pages'])) {
            throw new ValidationException($this->getSlug(), 'Plan has no pages array.', 'pages');
        }

        $pages = $plan['pages'];

        if ($pages === [] || array_keys($pages) !== range(0, count($pages) - 1)) {
            throw new ValidationException($this->getSlug(), 'Pages must be a non-empty list.', 'pages');
        }

        if (count($pages) > $context->getMaxPages()) {
            throw new ValidationException(
                $this->getSlug(),
                sprintf('Too many pages: %d (max %d).', count($pages), $context->getMaxPages()),
                'pages'
            );
        }

        if ($context->getScope() === 'page' && count($pages) !== 1) {
            throw new ValidationException(
                $this->getSlug(),
                'A single-page plan must describe exactly one page.',
                'pages'
            );
        }

        // One plan, one kit: the plan may only name the kit it was planned with.
        $kit = $context->getKit()->getSlug();

        if (isset($plan['kit']) && (string) $plan['kit'] !== '' && (string) $plan['kit'] !== $kit) {
            throw new ValidationException(
                $this->getSlug(),
                sprintf('This plan is for kit "%s", but this generation uses kit "%s".', (string) $plan['kit'], $kit),
                'kit'
            );
        }

        $clean         = [
            'schemaVersion' => PlanSchema::SCHEMA_VERSION,
            'summary'       => isset($plan['summary']) ? (string) $plan['summary'] : '',
            'kit'           => $kit,
        ];
        $seenKeys      = [];
        $sectionsTotal = 0;

        foreach ($pages as $index => $page) {
            $path = 'pages.' . $index;

            if (! is_array($page)) {
                throw new ValidationException($this->getSlug(), 'Page must be an object.', $path);
            }

            $page = $this->only($page, self::PAGE_KEYS);

            foreach (['key', 'title', 'slug'] as $required) {
                if (! isset($page[$required]) || trim((string) $page[$required]) === '') {
                    throw new ValidationException(
                        $this->getSlug(),
                        sprintf('Page is missing "%s".', $required),
                        $path . '.' . $required
                    );
                }
            }

            $key = (string) $page['key'];

            if (! preg_match('/^[a-z0-9][a-z0-9-]{0,39}$/', $key)) {
                throw new ValidationException(
                    $this->getSlug(),
                    sprintf('Page key "%s" must be lowercase letters, digits and hyphens.', $key),
                    $path . '.key'
                );
            }

            if (isset($seenKeys[$key])) {
                throw new ValidationException(
                    $this->getSlug(),
                    sprintf('Duplicate page key "%s".', $key),
                    $path . '.key'
                );
            }

            $seenKeys[$key] = true;

            if (isset($page['status']) && (string) $page['status'] !== PlanSchema::STATUS) {
                throw new ValidationException(
                    $this->getSlug(),
                    'Generated pages are always drafts; publishing is not available.',
                    $path . '.status'
                );
            }

            $sections = $this->checkSections(
                isset($page['sections']) ? $page['sections'] : null,
                $context,
                $path . '.sections'
            );

            $sectionsTotal += count($sections);

            if ($sectionsTotal > $context->getMaxSectionsTotal()) {
                throw new ValidationException(
                    $this->getSlug(),
                    sprintf('Too many sections in total: %d (max %d).', $sectionsTotal, $context->getMaxSectionsTotal()),
                    $path . '.sections'
                );
            }

            // Rebuilt in a canonical order, so validating an already-validated
            // plan returns the identical array rather than a reshuffled one.
            $clean['pages'][] = [
                'key'        => $key,
                'title'      => (string) $page['title'],
                'slug'       => (string) $page['slug'],
                'status'     => PlanSchema::STATUS,
                'template'   => isset($page['template']) && (string) $page['template'] !== ''
                    ? (string) $page['template']
                    : null,
                'onConflict' => isset($page['onConflict']) && (string) $page['onConflict'] !== ''
                    ? (string) $page['onConflict']
                    : null,
                'sections'   => $sections,
            ];
        }

        if ($context->getScope() === 'site') {
            $clean['site'] = $this->checkSite(isset($plan['site']) ? $plan['site'] : null);
        }

        return $clean;
    }

    /**
     * @param mixed       $sections Raw section list.
     * @param PlanContext $context  Validation context.
     * @param string      $path     Error path.
     *
     * @return array
     */
    private function checkSections($sections, PlanContext $context, $path)
    {
        if (! is_array($sections) || $sections === [] || array_keys($sections) !== range(0, count($sections) - 1)) {
            throw new ValidationException($this->getSlug(), 'Page must have a non-empty list of sections.', $path);
        }

        if (count($sections) > $context->getMaxSectionsPerPage()) {
            throw new ValidationException(
                $this->getSlug(),
                sprintf('Too many sections on one page: %d (max %d).', count($sections), $context->getMaxSectionsPerPage()),
                $path
            );
        }

        $clean = [];

        foreach ($sections as $index => $section) {
            $sectionPath = $path . '.' . $index;

            if (! is_array($section)) {
                throw new ValidationException($this->getSlug(), 'Section must be an object.', $sectionPath);
            }

            $section   = $this->only($section, self::SECTION_KEYS);
            $isPattern = isset($section['pattern']) && trim((string) $section['pattern']) !== '';
            $isBlock   = isset($section['block']) && trim((string) $section['block']) !== '';

            if ($isPattern === $isBlock) {
                throw new ValidationException(
                    $this->getSlug(),
                    'Section must name exactly one of "pattern" or "block".',
                    $sectionPath
                );
            }

            if ($isPattern) {
                $clean[] = $this->checkPatternSection($section, $sectionPath);
                continue;
            }

            $clean[] = [
                'block'      => (string) $section['block'],
                'attributes' => isset($section['attributes']) ? (array) $section['attributes'] : [],
            ];
        }

        return $clean;
    }

    /**
     * A kit section: the unit id plus CopySlots edits, normalised to maps so a
     * validated plan validates to itself. Texts and attrs arrive from a model as
     * lists of {id, html} / {id, value}, and from the review UI as id => value.
     *
     * @param array  $section Section, reduced to known keys.
     * @param string $path    Error path.
     *
     * @return array
     */
    private function checkPatternSection(array $section, $path)
    {
        $clean = [
            'pattern' => (string) $section['pattern'],
            'texts'   => $this->pairs(isset($section['texts']) ? $section['texts'] : [], 'html', $path . '.texts'),
            'attrs'   => $this->pairs(isset($section['attrs']) ? $section['attrs'] : [], 'value', $path . '.attrs'),
            'remove'  => [],
            'clones'  => [],
        ];

        $remove = isset($section['remove']) ? $section['remove'] : [];

        if (! is_array($remove)) {
            throw new ValidationException($this->getSlug(), 'Remove must be a list of unit ids.', $path . '.remove');
        }

        foreach ($remove as $unitId) {
            if (! is_string($unitId) || $unitId === '') {
                throw new ValidationException($this->getSlug(), 'Remove must be a list of unit ids.', $path . '.remove');
            }
            $clean['remove'][] = $unitId;
        }

        $clean['remove'] = array_values(array_unique($clean['remove']));
        $clones          = isset($section['clones']) ? $section['clones'] : [];

        if (! is_array($clones) || count($clones) > self::MAX_CLONES) {
            throw new ValidationException(
                $this->getSlug(),
                sprintf('Clones must be a list of at most %d items.', self::MAX_CLONES),
                $path . '.clones'
            );
        }

        foreach (array_values($clones) as $index => $clone) {
            $clonePath = $path . '.clones.' . $index;

            if (! is_array($clone) || ! isset($clone['unit']) || ! is_string($clone['unit']) || $clone['unit'] === '') {
                throw new ValidationException($this->getSlug(), 'A clone must name the unit it copies.', $clonePath);
            }

            $clean['clones'][] = [
                'unit'  => $clone['unit'],
                'texts' => $this->pairs(isset($clone['texts']) ? $clone['texts'] : [], 'html', $clonePath . '.texts'),
                'attrs' => $this->pairs(isset($clone['attrs']) ? $clone['attrs'] : [], 'value', $clonePath . '.attrs'),
            ];
        }

        return $clean;
    }

    /**
     * @param mixed  $list     [{id, <valueKey>}] or {id: value}.
     * @param string $valueKey "html" or "value".
     * @param string $path     Error path.
     *
     * @return array id => value.
     */
    private function pairs($list, $valueKey, $path)
    {
        if (! is_array($list)) {
            throw new ValidationException($this->getSlug(), 'Expected a list of slot edits.', $path);
        }

        if (count($list) > self::MAX_EDITS) {
            throw new ValidationException(
                $this->getSlug(),
                sprintf('Too many slot edits in one section (max %d).', self::MAX_EDITS),
                $path
            );
        }

        $out = [];

        foreach ($list as $key => $item) {
            if (is_array($item)) {
                if (! isset($item['id']) || ! is_string($item['id']) || ! array_key_exists($valueKey, $item)) {
                    throw new ValidationException(
                        $this->getSlug(),
                        sprintf('Each slot edit needs an "id" and a "%s".', $valueKey),
                        $path . '.' . $key
                    );
                }
                $id    = $item['id'];
                $value = $item[$valueKey];
            } else {
                $id    = (string) $key;
                $value = $item;
            }

            if (is_array($value) || is_object($value)) {
                throw new ValidationException($this->getSlug(), 'Slot values must be strings.', $path . '.' . $id);
            }

            $out[(string) $id] = (string) $value;
        }

        return $out;
    }

    /**
     * @param mixed $site Raw site block.
     *
     * @return array
     */
    private function checkSite($site)
    {
        if (! is_array($site)) {
            throw new ValidationException($this->getSlug(), 'A site plan must include a site block.', 'site');
        }

        $site = $this->only($site, self::SITE_KEYS);

        if (! isset($site['frontPage']) || trim((string) $site['frontPage']) === '') {
            throw new ValidationException($this->getSlug(), 'Site plan must name a front page.', 'site.frontPage');
        }

        $navigation = isset($site['navigation']) ? $site['navigation'] : [];

        if (! is_array($navigation) || ($navigation !== [] && array_keys($navigation) !== range(0, count($navigation) - 1))) {
            throw new ValidationException($this->getSlug(), 'Navigation must be a list of page keys.', 'site.navigation');
        }

        $site['navigation'] = array_values(array_map('strval', $navigation));

        return $site;
    }

    /**
     * @param array    $data Source array.
     * @param string[] $keys Keys to keep.
     *
     * @return array
     */
    private function only(array $data, array $keys)
    {
        $kept = [];

        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                $kept[$key] = $data[$key];
            }
        }

        return $kept;
    }
}
