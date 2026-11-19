<?php
/**
 * Stage 1 of a staged site plan: the outline that decides the site.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Generation\Staged;

use SwishfolioCore\Actions\ValidationException;
use SwishfolioCore\AI\Generation\Kit;
use SwishfolioCore\AI\Generation\PlanContext;
use SwishfolioCore\AI\Generation\PlanSchema;
use SwishfolioCore\AI\Generation\Stages\PlanReferenceStage;

/**
 * One small call decides everything that has to agree across pages: which
 * pages exist (key, title, slug, purpose), which kit sections each one uses
 * and in what order, the front page and the menu, and the shared facts (name,
 * voice, key messages, contact details) every page writes from. No copy is
 * written here; each page's copy is a separate call (see StagedPlanner).
 *
 * normalise() repairs what can be repaired without guessing (unknown
 * sections dropped, caps applied, a missing front page defaulted) and only
 * refuses an outline that leaves nothing to build.
 */
class SiteOutline
{
    const STAGE = 'plan-outline';

    /**
     * @param string $kit Kit slug.
     *
     * @return array JSON Schema.
     */
    public static function schema($kit)
    {
        $text = ['type' => 'string'];

        return [
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => ['schemaVersion', 'kit', 'summary', 'site', 'facts', 'pages'],
            'properties'           => [
                'schemaVersion' => ['type' => 'string', 'enum' => [PlanSchema::SCHEMA_VERSION]],
                'kit'           => ['type' => 'string', 'enum' => [(string) $kit]],
                'summary'       => [
                    'type'        => 'string',
                    'description' => 'One or two sentences describing the site, shown to the owner.',
                ],
                'site' => [
                    'type'                 => 'object',
                    'additionalProperties' => false,
                    'required'             => ['title', 'tagline', 'frontPage', 'navigation'],
                    'properties'           => [
                        'title'      => $text,
                        'tagline'    => $text,
                        'frontPage'  => ['type' => 'string', 'description' => 'Key of the front page.'],
                        'navigation' => ['type' => 'array', 'items' => $text, 'description' => 'Page keys in menu order.'],
                    ],
                ],
                'facts' => [
                    'type'                 => 'object',
                    'additionalProperties' => false,
                    'required'             => ['businessName', 'voice', 'audience', 'keyMessages', 'contact'],
                    'description'          => 'Shared facts every page writes from, so the pages agree.',
                    'properties'           => [
                        'businessName' => $text,
                        'voice'        => ['type' => 'string', 'description' => 'Tone of voice in a few words.'],
                        'audience'     => $text,
                        'keyMessages'  => ['type' => 'array', 'items' => $text, 'description' => 'Three to six things the site must get across, from the owner\'s own facts.'],
                        'contact'      => ['type' => 'string', 'description' => 'Contact details, address and hours exactly as the owner gave them, or an empty string.'],
                    ],
                ],
                'pages' => [
                    'type'     => 'array',
                    'minItems' => 1,
                    'items'    => [
                        'type'                 => 'object',
                        'additionalProperties' => false,
                        'required'             => ['key', 'title', 'slug', 'purpose', 'sections'],
                        'properties'           => [
                            'key'        => $text,
                            'title'      => $text,
                            'slug'       => $text,
                            'onConflict' => ['type' => 'string', 'enum' => PlanSchema::CONFLICT_STRATEGIES],
                            'purpose'    => ['type' => 'string', 'description' => 'What this page is for and what it must say, in one or two sentences.'],
                            'sections'   => [
                                'type'     => 'array',
                                'minItems' => 1,
                                'items'    => [
                                    'type'                 => 'object',
                                    'additionalProperties' => false,
                                    'required'             => ['brief'],
                                    'properties'           => [
                                        'pattern' => ['type' => 'string', 'description' => 'Id of a section unit from the kit. Use this or "block".'],
                                        'block'   => ['type' => 'string', 'description' => 'Name of a dynamic block. Use this or "pattern".'],
                                        'brief'   => ['type' => 'string', 'description' => 'What this section says on this page, in one sentence.'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array       $outline Decoded outline.
     * @param Kit         $kit     The run's kit.
     * @param PlanContext $context Site-scope plan context (limits, existing slugs).
     *
     * @throws ValidationException When nothing buildable is left.
     *
     * @return array Clean outline.
     */
    public static function normalise(array $outline, Kit $kit, PlanContext $context)
    {
        $blocks   = (array) (function_exists('apply_filters')
            ? apply_filters('sfcore_generation_dynamic_blocks', PlanReferenceStage::DYNAMIC_BLOCKS)
            : PlanReferenceStage::DYNAMIC_BLOCKS);
        $pages    = isset($outline['pages']) && is_array($outline['pages']) ? array_values($outline['pages']) : [];
        $clean    = [];
        $keys     = [];
        $slugs    = [];
        $total    = 0;
        $maxPages = max(1, (int) $context->getMaxPages());
        $perPage  = max(1, (int) $context->getMaxSectionsPerPage());
        $maxTotal = max(1, (int) $context->getMaxSectionsTotal());

        foreach ($pages as $page) {
            if (! is_array($page) || count($clean) >= $maxPages) {
                continue;
            }

            $key   = strtolower(trim((string) (isset($page['key']) ? $page['key'] : '')));
            $title = trim((string) (isset($page['title']) ? $page['title'] : ''));
            $slug  = strtolower(trim((string) (isset($page['slug']) ? $page['slug'] : $key), " /\t\n"));
            $slug  = trim((string) preg_replace('/[^a-z0-9-]+/', '-', $slug), '-');

            if (! preg_match('/^[a-z0-9][a-z0-9-]{0,39}$/', $key) || isset($keys[$key]) || $title === '') {
                continue;
            }

            if ($slug === '') {
                $slug = $key;
            }

            if (isset($slugs[$slug])) {
                $slug = $slug . '-' . $key;
            }

            $sections = [];

            foreach (isset($page['sections']) && is_array($page['sections']) ? $page['sections'] : [] as $section) {
                if (! is_array($section) || count($sections) >= $perPage || $total + count($sections) >= $maxTotal) {
                    continue;
                }

                $brief   = trim((string) (isset($section['brief']) ? $section['brief'] : ''));
                $pattern = trim((string) (isset($section['pattern']) ? $section['pattern'] : ''));
                $block   = trim((string) (isset($section['block']) ? $section['block'] : ''));

                if ($pattern !== '' && $kit->section($pattern) !== null) {
                    $sections[] = ['pattern' => $pattern, 'brief' => $brief];
                } elseif ($pattern === '' && $block !== '' && in_array($block, $blocks, true)) {
                    $sections[] = ['block' => $block, 'brief' => $brief];
                }
            }

            if ($sections === []) {
                continue;
            }

            $onConflict = isset($page['onConflict']) && in_array($page['onConflict'], PlanSchema::CONFLICT_STRATEGIES, true)
                ? (string) $page['onConflict']
                : null;

            // A slug already taken on this site needs a strategy, or the plan is refused.
            if ($onConflict === null && $context->getExistingPostId($slug) !== null) {
                $onConflict = 'suffix';
            }

            $keys[$key]   = true;
            $slugs[$slug] = true;
            $total       += count($sections);
            $clean[]      = [
                'key'        => $key,
                'title'      => mb_substr($title, 0, 120),
                'slug'       => mb_substr($slug, 0, 120),
                'onConflict' => $onConflict,
                'purpose'    => trim((string) (isset($page['purpose']) ? $page['purpose'] : '')),
                'sections'   => $sections,
            ];
        }

        if ($clean === []) {
            throw new ValidationException(self::STAGE, 'The outline has no page made of this kit\'s sections.', 'pages');
        }

        $site  = isset($outline['site']) && is_array($outline['site']) ? $outline['site'] : [];
        $front = isset($site['frontPage']) ? strtolower(trim((string) $site['frontPage'])) : '';

        if (! isset($keys[$front])) {
            $front = $clean[0]['key'];
        }

        $navigation = [];

        foreach (isset($site['navigation']) && is_array($site['navigation']) ? $site['navigation'] : [] as $key) {
            $key = strtolower(trim((string) $key));

            if (isset($keys[$key]) && ! in_array($key, $navigation, true)) {
                $navigation[] = $key;
            }
        }

        if ($navigation === []) {
            $navigation = array_column($clean, 'key');
        }

        $facts = isset($outline['facts']) && is_array($outline['facts']) ? $outline['facts'] : [];

        return [
            'summary' => mb_substr(trim((string) (isset($outline['summary']) ? $outline['summary'] : '')), 0, 400),
            'site'    => [
                'title'      => mb_substr(trim((string) (isset($site['title']) ? $site['title'] : '')), 0, 120),
                'tagline'    => mb_substr(trim((string) (isset($site['tagline']) ? $site['tagline'] : '')), 0, 200),
                'frontPage'  => $front,
                'navigation' => $navigation,
            ],
            'facts'   => [
                'businessName' => trim((string) (isset($facts['businessName']) ? $facts['businessName'] : '')),
                'voice'        => trim((string) (isset($facts['voice']) ? $facts['voice'] : '')),
                'audience'     => trim((string) (isset($facts['audience']) ? $facts['audience'] : '')),
                'keyMessages'  => array_values(array_filter(array_map('trim', array_map('strval', isset($facts['keyMessages']) && is_array($facts['keyMessages']) ? $facts['keyMessages'] : [])))),
                'contact'      => trim((string) (isset($facts['contact']) ? $facts['contact'] : '')),
            ],
            'pages'   => $clean,
        ];
    }

    /**
     * @param array $outline Clean outline.
     *
     * @return int
     */
    public static function sectionCount(array $outline)
    {
        $count = 0;

        foreach ($outline['pages'] as $page) {
            $count += count($page['sections']);
        }

        return $count;
    }
}
