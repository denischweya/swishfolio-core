<?php
/**
 * Turns a validated page plan into block markup.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Generation;

/**
 * A composing serializer, never a synthesising one.
 *
 * PHP cannot produce the save markup of a static block: serialize_block() builds
 * content from innerContent and innerBlocks only, and there is no PHP equivalent
 * of a block's JavaScript save(). A hand-built swishfolio-core/hero therefore
 * serialises to an empty self-closing comment and renders to nothing.
 *
 * So this class only ever emits markup it did not invent:
 *
 *  - a pattern section is a kit unit's own markup with the plan's copy spliced
 *    into its CopySlots slots (everything else kept byte for byte), then its
 *    kit placeholders resolved: {{link:key}} to the page playing that part,
 *    {{kit-image:name}} to the kit image's URL;
 *  - a block section is a dynamic block, whose every attribute lives in the
 *    comment and whose markup is produced at render time.
 */
class BlockComposer
{
    /** @var Kit */
    private $kit;

    /** @var array|null KitForms options; null leaves demo forms as they are. */
    private $forms = null;

    /** @var int */
    private $formCount = 0;

    public function __construct(Kit $kit)
    {
        $this->kit = $kit;
    }

    /**
     * Swap the demo form of needs:form sections for the working form block.
     *
     * @param array $opts KitForms::swap() options (recipient, formId prefix, subject, success).
     *
     * @return self
     */
    public function withForms(array $opts)
    {
        $this->forms = $opts;

        return $this;
    }

    /**
     * @return Kit
     */
    public function getKit()
    {
        return $this->kit;
    }

    /**
     * @param array $sections Validated section list from one plan page.
     * @param array $links    Kit page key => link target ("{{page:key}}" or a URL).
     *
     * @throws \RuntimeException When a unit cannot be resolved or a placeholder is left behind.
     *
     * @return string post_content.
     */
    public function compose(array $sections, array $links = [])
    {
        $parts = [];

        foreach ($sections as $section) {
            if (isset($section['pattern'])) {
                $parts[] = $this->composePattern((string) $section['pattern'], $section, $links);
                continue;
            }

            if (isset($section['block'])) {
                $parts[] = $this->composeBlock(
                    (string) $section['block'],
                    isset($section['attributes']) ? (array) $section['attributes'] : []
                );
            }
        }

        return implode("\n\n", array_filter($parts, 'strlen'));
    }

    /**
     * @param string $unitId  Kit section id.
     * @param array  $section Validated section: texts, attrs, remove, clones.
     * @param array  $links   Kit page key => link target.
     *
     * @throws \RuntimeException When a placeholder is left behind.
     *
     * @return string
     */
    public function composePattern($unitId, array $section, array $links = [])
    {
        // Kit links are protected as "#sf-link-<key>" while CopySlots runs, so
        // its kses pass cannot read "{{link" as a URL scheme and strip it.
        // Only attribute values change, so the slot ids stay the same.
        $copy = new \SwishfolioCore\AI\Onboarding\CopySlots(self::protectLinks($this->kit->markup($unitId)));

        $clones = [];
        foreach (isset($section['clones']) ? (array) $section['clones'] : [] as $clone) {
            $clones[] = [
                'unit'  => (string) $clone['unit'],
                'texts' => array_map([self::class, 'protectLinks'], isset($clone['texts']) ? (array) $clone['texts'] : []),
                'attrs' => isset($clone['attrs']) ? (array) $clone['attrs'] : [],
            ];
        }

        // A title longer than the demo's is drawn smaller, and a decorative
        // copy of the heading always says (and wraps) what the heading says.
        $texts = HeadlineFit::texts(
            isset($section['texts']) ? (array) $section['texts'] : [],
            $copy->slots(),
            $this->kit->mirrorSlots($unitId)
        );

        $filled = $copy->apply([
            'texts'  => array_map([self::class, 'protectLinks'], $texts),
            'attrs'  => isset($section['attrs']) ? (array) $section['attrs'] : [],
            'remove' => isset($section['remove']) ? (array) $section['remove'] : [],
            'clones' => $clones,
        ]);

        $filled = $this->resolve(self::restoreLinks($filled), $links);

        $unit = $this->kit->get($unitId);

        if ($this->forms !== null && $unit !== null && in_array('form', $unit->getNeeds(), true)) {
            $opts           = $this->forms;
            $opts['formId'] = (isset($opts['formId']) ? $opts['formId'] : 'swish-form') . '-' . (++$this->formCount);
            $filled         = \SwishfolioCore\AI\Onboarding\KitForms::swap($filled, $opts);
        }

        if (preg_match('/\{\{(kit-[a-z-]+|link|nav):?[^}]*\}\}/', $filled)) {
            throw new \RuntimeException(
                sprintf('Kit unit "%s" left an unresolved placeholder in its markup.', $unitId)
            );
        }

        return trim($filled);
    }

    /**
     * @param string $html Markup that may hold {{link:key}}.
     *
     * @return string The same with each as "#sf-link-key", which kses keeps.
     */
    public static function protectLinks($html)
    {
        return preg_replace('/\{\{link:([a-z0-9-]+)\}\}/', '#sf-link-$1', (string) $html);
    }

    /**
     * @param string $html Markup from protectLinks().
     *
     * @return string
     */
    public static function restoreLinks($html)
    {
        return preg_replace('/#sf-link-([a-z0-9-]+)(?=["\'])/', '{{link:$1}}', (string) $html);
    }

    /**
     * @param string $markup Unit markup with kit placeholders.
     * @param array  $links  Kit page key => link target.
     *
     * @return string
     */
    public function resolve($markup, array $links)
    {
        $kit = $this->kit;

        $markup = preg_replace_callback('/\{\{link:([a-z0-9-]+)\}\}/', static function ($m) use ($links) {
            return isset($links[$m[1]]) ? (string) $links[$m[1]] : '#';
        }, $markup);

        return preg_replace_callback('/\{\{kit-image:([a-z0-9_-]+)\}\}/', static function ($m) use ($kit) {
            $url = $kit->assetUrl($m[1]);

            return $url === null ? '' : $url;
        }, $markup);
    }

    /**
     * @param string $blockName  Dynamic block name.
     * @param array  $attributes Validated attributes.
     *
     * @return string
     */
    public function composeBlock($blockName, array $attributes)
    {
        return serialize_block([
            'blockName'    => $blockName,
            'attrs'        => $attributes,
            'innerBlocks'  => [],
            'innerHTML'    => '',
            'innerContent' => [],
        ]);
    }
}
