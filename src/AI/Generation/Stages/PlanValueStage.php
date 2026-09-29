<?php
/**
 * Stage 4: every value in the plan satisfies its constraints.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Generation\Stages;

use SwishfolioCore\AI\Generation\PlanContext;
use SwishfolioCore\AI\Generation\SlotSpec;
use SwishfolioCore\Actions\ActionSchema;
use SwishfolioCore\Actions\ActionValidator;
use SwishfolioCore\Actions\Stages\ValueStage;
use SwishfolioCore\Actions\ValidationContext;
use SwishfolioCore\Actions\ValidationException;

/**
 * Block attributes are not re-validated here: they are handed to the existing
 * ActionValidator as synthetic update_block_attributes actions, so the manifest
 * allowlist, the enum and range checks, design-token expansion and sanitisation
 * are the same code that guards in-editor edits. Only the failure path is
 * rewritten, from an action index back to a page and section.
 *
 * Slot values get their own rules, because a slot is a literal token in markup
 * rather than an attribute. The strict rule is for slots whose token also lands
 * inside a block comment: KSES rewrites comment bodies on save, so a bare "&" or
 * an angle bracket there silently corrupts the block.
 */
class PlanValueStage implements PlanStageInterface
{
    /**
     * Characters a slot may not carry when its token is substituted raw into a
     * block comment: angle brackets and ampersands are rewritten by KSES, and a
     * quote, a backslash or a double hyphen breaks the comment's JSON or its
     * delimiter outright.
     */
    const COMMENT_UNSAFE = ['<', '>', '&', '"', '\\', '--'];

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
        $plan = $this->checkBlockAttributes($plan, $context);

        if (isset($plan['site']['styleVariation']) && (string) $plan['site']['styleVariation'] !== '') {
            $this->checkStyleVariation((string) $plan['site']['styleVariation'], $context);
        }

        return $plan;
    }

    private function checkSlots(array $plan, PlanContext $context)
    {
        foreach ($plan['pages'] as $pageIndex => $page) {
            foreach ($page['sections'] as $sectionIndex => $section) {
                if (! isset($section['pattern'])) {
                    continue;
                }

                $unit = $context->getLibrary()->get($section['pattern']);
                $path = 'pages.' . $pageIndex . '.sections.' . $sectionIndex . '.content';

                foreach ($section['content'] as $name => $value) {
                    $plan['pages'][$pageIndex]['sections'][$sectionIndex]['content'][$name] = $this->checkSlot(
                        $unit->getSlot($name),
                        $value,
                        $path . '.' . $name
                    );
                }
            }
        }

        return $plan;
    }

    /**
     * @param SlotSpec $slot  Slot being filled.
     * @param mixed    $value Proposed value.
     * @param string   $path  Error path.
     *
     * @return string
     */
    private function checkSlot(SlotSpec $slot, $value, $path)
    {
        if (is_array($value) || is_object($value)) {
            throw new ValidationException($this->getSlug(), 'Slot values must be strings.', $path);
        }

        $string = trim((string) $value);

        if ($slot->getType() === 'url') {
            return $this->checkUrlSlot($string, $path);
        }

        $max = $slot->getMaxLength();

        $length = function_exists('mb_strlen') ? mb_strlen($string) : strlen($string);

        if ($max !== null && $length > $max) {
            throw new ValidationException(
                $this->getSlug(),
                sprintf('Slot "%s" exceeds %d characters.', $slot->getName(), $max),
                $path
            );
        }

        if ($slot->isInComment()) {
            foreach (self::COMMENT_UNSAFE as $needle) {
                if (strpos($string, $needle) !== false) {
                    throw new ValidationException(
                        $this->getSlug(),
                        sprintf(
                            'Slot "%s" is stored inside a block comment, so it cannot contain "%s".',
                            $slot->getName(),
                            $needle
                        ),
                        $path
                    );
                }
            }
        }

        return $string;
    }

    private function checkUrlSlot($url, $path)
    {
        if (preg_match('/^\{\{page:[a-z0-9][a-z0-9-]{0,39}\}\}$/', $url)) {
            // Resolved to a permalink once the page it names has an ID.
            return $url;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if ($url !== '' && $scheme !== '' && ! in_array($scheme, ValueStage::ALLOWED_URL_SCHEMES, true)) {
            throw new ValidationException(
                $this->getSlug(),
                sprintf('URL scheme "%s" is not permitted.', $scheme),
                $path
            );
        }

        return $url;
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

    private function checkStyleVariation($title, PlanContext $context)
    {
        foreach ($context->getStyleVariations() as $known) {
            if ($known === $title || sanitize_title($known) === sanitize_title($title)) {
                return;
            }
        }

        throw new ValidationException(
            $this->getSlug(),
            sprintf(
                '"%s" is not a style variation this theme ships. Available: %s.',
                $title,
                implode(', ', $context->getStyleVariations())
            ),
            'site.styleVariation'
        );
    }
}
