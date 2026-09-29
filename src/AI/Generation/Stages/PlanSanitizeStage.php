<?php
/**
 * Stage 5: plan values are sanitised for their type.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Generation\Stages;

use SwishfolioCore\AI\Generation\PlanContext;

/**
 * The same mapping Actions\Stages\SanitizeStage applies to attributes, applied to
 * the things a plan carries that an action does not: slot copy, page titles and
 * page slugs.
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

                $unit = $context->getLibrary()->get($section['pattern']);

                foreach ($section['content'] as $name => $value) {
                    $plan['pages'][$pageIndex]['sections'][$sectionIndex]['content'][$name] = $this->sanitize(
                        $unit->getSlot($name),
                        $value
                    );
                }
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
     * @param \SwishfolioCore\AI\Generation\SlotSpec $slot  Slot being filled.
     * @param string                                 $value Validated value.
     *
     * @return string
     */
    private function sanitize($slot, $value)
    {
        switch ($slot->getType()) {
            case 'richtext':
                return wp_kses_post((string) $value);

            case 'url':
                // An internal page reference is resolved to a permalink at
                // execution time; escaping it now would destroy the braces.
                if (strpos((string) $value, '{{page:') === 0) {
                    return (string) $value;
                }

                return esc_url_raw((string) $value);

            default:
                $text = sanitize_text_field((string) $value);

                // A slot whose token sits inside a block comment must stay raw:
                // escaping would put an ampersand into the comment body, which
                // is the one thing that corrupts it. PlanValueStage has already
                // refused the characters that would need escaping there.
                if ($slot->isInComment()) {
                    return $text;
                }

                // Everywhere else, escape now rather than let KSES rewrite the
                // content on save: "Design & build" stored raw comes back as
                // "Design &amp; build", so the markup written is not the markup
                // kept. Escaping here makes composed content byte-stable.
                return esc_html($text);
        }
    }
}
