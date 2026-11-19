<?php
/**
 * Stage 4: every value in the plan satisfies its constraints.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Generation\Stages;

use SwishfolioCore\AI\Generation\PlanContext;
use SwishfolioCore\Actions\ActionSchema;
use SwishfolioCore\Actions\ActionValidator;
use SwishfolioCore\Actions\ValidationContext;
use SwishfolioCore\Actions\ValidationException;

/**
 * Block attributes are not re-validated here: they are handed to the existing
 * ActionValidator as synthetic update_block_attributes actions, so the manifest
 * allowlist, the enum and range checks, design-token expansion and sanitisation
 * are the same code that guards in-editor edits. Only the failure path is
 * rewritten, from an action index back to a page and section.
 *
 * Slot edits get their own rules: a bounded length, no kit placeholder syntax
 * beyond the {{link:key}} hrefs the demo copy already had (any other "{{" in
 * copy would be resolved like the kit's own links and images), and
 * a link target only in the tel:/mailto: form CopySlots accepts. CopySlots
 * would silently keep the demo number for anything else, so it is refused.
 */
class PlanValueStage implements PlanStageInterface
{
    /** Longest copy one slot may take. */
    const MAX_SLOT_CHARS = 1200;

    /** Link targets an attribute slot on an href may take (CopySlots' own rule). */
    const HREF_PATTERN = '#^(tel:\+?[0-9 ().-]{3,30}|mailto:[^\s"\'<>]{3,200})$#i';

    /**
     * Characters a dynamic block's attribute may not carry. Narrower than the
     * slot rule because serialize_block_attributes() already escapes the rest.
     * Measured on a live multisite: of "&", "<", ">", "--", a quote, a
     * backslash, an apostrophe, an em dash and a design token, only "&" and ">"
     * come back changed — "&" reparses as "&amp;" and ">" as "&gt;".
     */
    const ATTRIBUTE_UNSAFE = ['&', '>'];

    public function getSlug()
    {
        return 'plan-value';
    }

    public function process(array $plan, PlanContext $context)
    {
        $plan = $this->checkSlots($plan, $context);

        return $this->checkBlockAttributes($plan, $context);
    }

    private function checkSlots(array $plan, PlanContext $context)
    {
        $kit = $context->getKit();

        foreach ($plan['pages'] as $pageIndex => $page) {
            foreach ($page['sections'] as $sectionIndex => $section) {
                if (! isset($section['pattern'])) {
                    continue;
                }

                $slots = $kit->slots($section['pattern'])->slots();
                $path  = 'pages.' . $pageIndex . '.sections.' . $sectionIndex;
                $ref   = &$plan['pages'][$pageIndex]['sections'][$sectionIndex];

                $keys = $kit->getPageKeys();

                $ref['texts'] = $this->checkValues($section['texts'], $slots, $path . '.texts', $keys);
                $ref['attrs'] = $this->checkValues($section['attrs'], $slots, $path . '.attrs', $keys);

                foreach ($section['clones'] as $index => $clone) {
                    $ref['clones'][$index]['texts'] = $this->checkValues($clone['texts'], $slots, $path . '.clones.' . $index . '.texts', $keys);
                    $ref['clones'][$index]['attrs'] = $this->checkValues($clone['attrs'], $slots, $path . '.clones.' . $index . '.attrs', $keys);
                }

                unset($ref);
            }
        }

        return $plan;
    }

    /**
     * @param array  $values id => value.
     * @param array  $slots  CopySlots slots.
     * @param string   $path   Error path.
     * @param string[] $keys   The kit's page keys.
     *
     * @return array Trimmed values.
     */
    private function checkValues(array $values, array $slots, $path, array $keys = [])
    {
        foreach ($values as $id => $value) {
            $string = trim((string) $value);
            $length = function_exists('mb_strlen') ? mb_strlen($string) : strlen($string);

            // A form field's default (a date input's value="2026-11-20") is
            // rightly cleared: a stale demo date is worse than an empty field.
            if ($string === '' && isset($slots[$id]['type'], $slots[$id]['attr'])
                && $slots[$id]['type'] === 'attr' && $slots[$id]['attr'] === 'value') {
                $values[$id] = '';
                continue;
            }

            if ($string === '') {
                throw new ValidationException(
                    $this->getSlug(),
                    sprintf('Slot "%s" is empty. Remove its unit instead of emptying it.', $id),
                    $path . '.' . $id
                );
            }

            if ($length > self::MAX_SLOT_CHARS) {
                throw new ValidationException(
                    $this->getSlug(),
                    sprintf('Slot "%s" exceeds %d characters.', $id, self::MAX_SLOT_CHARS),
                    $path . '.' . $id
                );
            }

            // The only placeholders copy may carry are the ones its demo copy
            // had (a button's {{link:key}} href), kept where they were.
            $demo = isset($slots[$id]['value']) ? (string) $slots[$id]['value'] : '';
            $bare = preg_replace('/\{\{link:[a-z0-9-]+\}\}/', '', $string);

            preg_match_all('/\{\{link:[a-z0-9-]+\}\}/', $string, $used);

            // A button that linked to a template page may point at another
            // template page instead (the plan dropped "private dining", so the
            // button now says "Visit us"): same number of links, kit keys only.
            preg_match_all('/\{\{link:([a-z0-9-]+)\}\}/', $demo, $demoLinks);
            $retarget = count($used[0]) <= count($demoLinks[0]);

            foreach ($used[0] as $placeholder) {
                $key = substr($placeholder, 7, -2);
                if (strpos($demo, $placeholder) === false && ! ($retarget && in_array($key, $keys, true))) {
                    $bare .= '{{';
                }
            }

            if (strpos($bare, '{{') !== false || strpos($bare, '}}') !== false) {
                throw new ValidationException(
                    $this->getSlug(),
                    sprintf('Slot "%s" may only keep the {{link:...}} its demo copy had; it cannot add "{{" or "}}".', $id),
                    $path . '.' . $id
                );
            }

            // A link inside copy keeps to the schemes an edit may use.
            if (preg_match_all('/\bhref\s*=\s*["\']?\s*([a-z][a-z0-9+.-]*):/i', $string, $schemes)) {
                foreach ($schemes[1] as $scheme) {
                    if (! in_array(strtolower($scheme), ['http', 'https', 'mailto', 'tel'], true)) {
                        throw new ValidationException(
                            $this->getSlug(),
                            sprintf('Slot "%s" links with "%s:"; only http, https, mailto and tel are allowed.', $id, $scheme),
                            $path . '.' . $id
                        );
                    }
                }
            }

            if (isset($slots[$id]['attr']) && $slots[$id]['attr'] === 'href' && ! preg_match(self::HREF_PATTERN, $string)) {
                throw new ValidationException(
                    $this->getSlug(),
                    sprintf('Slot "%s" is a phone or email link, so it must be a tel: or mailto: address.', $id),
                    $path . '.' . $id
                );
            }

            $values[$id] = $string;
        }

        return $values;
    }

    private function checkBlockAttributes(array $plan, PlanContext $context)
    {
        $tree    = [];
        $actions = [];
        $map     = [];

        foreach ($plan['pages'] as $pageIndex => $page) {
            foreach ($page['sections'] as $sectionIndex => $section) {
                if (! isset($section['block']) || $section['attributes'] === []) {
                    continue;
                }

                $clientId        = 'gen-' . $pageIndex . '-' . $sectionIndex;
                $tree[$clientId] = [
                    'name'           => $section['block'],
                    'attributes'     => [],
                    'parentClientId' => null,
                ];

                $map[count($actions)] = ['page' => $pageIndex, 'section' => $sectionIndex];

                $actions[] = [
                    'type'     => 'update_block_attributes',
                    'clientId' => $clientId,
                    'changes'  => $section['attributes'],
                ];
            }
        }

        if ($actions === []) {
            return $plan;
        }

        $clientIds = array_keys($tree);

        $validationContext = new ValidationContext(
            $tree,
            $clientIds[0],
            $context->getRegistry(),
            $context->getTokens(),
            count($actions)
        );

        try {
            $validated = ActionValidator::withDefaultStages()->validate(
                [
                    'schemaVersion' => ActionSchema::SCHEMA_VERSION,
                    'summary'       => 'Generation plan block attributes.',
                    'actions'       => $actions,
                ],
                $validationContext
            );
        } catch (ValidationException $e) {
            throw new ValidationException(
                $this->getSlug(),
                $e->getMessage(),
                $this->remapPath($e->getPath(), $map)
            );
        }

        foreach ($validated['actions'] as $index => $action) {
            $target = $map[$index];
            $path   = sprintf('pages.%d.sections.%d.attributes', $target['page'], $target['section']);

            $this->assertCommentSafe($action['changes'], $path);

            $plan['pages'][$target['page']]['sections'][$target['section']]['attributes'] = $action['changes'];
        }

        return $plan;
    }

    /**
     * A dynamic block's every attribute is stored in the block comment, and a
     * comment body is rewritten by KSES on save. Verified on a live multisite:
     * serialize_block_attributes() escapes "&" to \u0026, and KSES then turns
     * that into \u0026amp;, so the attribute reparses as "&amp;" rather than
     * "&". The value is not lost, it is quietly wrong — so it is refused.
     *
     * Design tokens are unaffected: var(--wp--preset--color--accent-2) survives
     * byte for byte, which is why the check is narrow rather than a blanket one.
     *
     * @param array  $attributes Validated attributes.
     * @param string $path       Error path.
     *
     * @return void
     */
    private function assertCommentSafe(array $attributes, $path)
    {
        foreach ($attributes as $attribute => $value) {
            if (! is_string($value)) {
                continue;
            }

            foreach (self::ATTRIBUTE_UNSAFE as $needle) {
                if (strpos($value, $needle) !== false) {
                    throw new ValidationException(
                        $this->getSlug(),
                        sprintf(
                            'Attribute "%s" is stored inside a block comment, so it cannot contain "%s".',
                            $attribute,
                            $needle
                        ),
                        $path . '.' . $attribute
                    );
                }
            }
        }
    }

    /**
     * Rewrite "actions.2.changes.columns" as "pages.1.sections.3.attributes.columns".
     *
     * @param string|null $path Path reported by the action pipeline.
     * @param array       $map  Action index => page and section index.
     *
     * @return string|null
     */
    private function remapPath($path, array $map)
    {
        if ($path === null || ! preg_match('/^actions\.(\d+)(.*)$/', $path, $matches)) {
            return $path;
        }

        $index = (int) $matches[1];

        if (! isset($map[$index])) {
            return $path;
        }

        $suffix = $matches[2];

        if (strpos($suffix, '.changes') === 0) {
            $suffix = substr($suffix, strlen('.changes'));
        }

        return sprintf(
            'pages.%d.sections.%d.attributes%s',
            $map[$index]['page'],
            $map[$index]['section'],
            $suffix
        );
    }
}
