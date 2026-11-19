<?php
/**
 * Stage 5: plan values are sanitised for their type.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Generation\Stages;

use SwishfolioCore\AI\Onboarding\CopySlots;
use SwishfolioCore\AI\Generation\BlockComposer;
use SwishfolioCore\AI\Generation\PlanContext;

/**
 * The same mapping Actions\Stages\SanitizeStage applies to attributes, applied to
 * the things a plan carries that an action does not: slot copy, page titles and
 * page slugs.
 *
 * Text slots keep the inline markup a template uses (<em>, <span class>, an
 * <a> on a button), filtered to CopySlots' inline allowlist, the same filter
 * CopySlots applies again when it splices the copy in. Attribute values are
 * plain text; CopySlots escapes them. Because both are safe by construction,
 * the composed page can be written past kses (see TrustedMarkup::vouch()).
 *
 * Block attributes are already sanitised: PlanValueStage puts them through the
 * whole action pipeline, sanitize stage included.
 */
class PlanSanitizeStage implements PlanStageInterface
{
    public function getSlug()
    {
        return 'plan-sanitize';
    }

    public function process(array $plan, PlanContext $context)
    {
        $plan['summary'] = sanitize_text_field($plan['summary']);

        foreach ($plan['pages'] as $pageIndex => $page) {
            $plan['pages'][$pageIndex]['title'] = sanitize_text_field($page['title']);
            $plan['pages'][$pageIndex]['slug']  = sanitize_title($page['slug']);

            foreach ($page['sections'] as $sectionIndex => $section) {
                if (! isset($section['pattern'])) {
                    continue;
                }

                $ref          = &$plan['pages'][$pageIndex]['sections'][$sectionIndex];
                $ref['texts'] = $this->texts($section['texts']);
                $ref['attrs'] = $this->attrs($section['attrs']);

                foreach ($section['clones'] as $index => $clone) {
                    $ref['clones'][$index]['texts'] = $this->texts($clone['texts']);
                    $ref['clones'][$index]['attrs'] = $this->attrs($clone['attrs']);
                }

                unset($ref);
            }
        }

        if (isset($plan['site'])) {
            foreach (['title', 'tagline'] as $field) {
                if (isset($plan['site'][$field])) {
                    $plan['site'][$field] = sanitize_text_field($plan['site'][$field]);
                }
            }
        }

        return $plan;
    }

    /**
     * @param array $texts id => inner HTML.
     *
     * @return array
     */
    private function texts(array $texts)
    {
        foreach ($texts as $id => $html) {
            $clean      = wp_kses(BlockComposer::protectLinks(str_replace(["\r", "\n"], ' ', (string) $html)), CopySlots::INLINE_KSES);
            $texts[$id] = trim(BlockComposer::restoreLinks($clean));
        }

        return $texts;
    }

    /**
     * @param array $attrs id => value.
     *
     * @return array
     */
    private function attrs(array $attrs)
    {
        foreach ($attrs as $id => $value) {
            $attrs[$id] = sanitize_text_field((string) $value);
        }

        return $attrs;
    }
}
