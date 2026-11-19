<?php
/**
 * The eval assertions for the in-editor block-editing track.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Tests\Evals\Support;

use SwishfolioCore\AI\Context\TokenResolver;
use SwishfolioCore\Blocks\BlockRegistry;

/**
 * Judges an AI's proposed actions against the same three sources of truth the
 * runtime uses — the registered block.json files, the capability manifests and
 * the live theme.json token sets — without going through the validator, so that
 * a stage which quietly stops enforcing something is caught by disagreement
 * rather than hidden by a shared assumption.
 *
 * The storage check is separate and runs on the *validated* actions, because only
 * then have design-token slugs been expanded to the values a block really stores.
 * It exists because an attribute that survives every validation stage can still be
 * corrupted on its way into a block comment.
 */
final class ActionAudit
{
    /** @var BlockRegistry */
    private $registry;

    /** @var TokenResolver */
    private $tokens;

    /** @var BlockCatalogue */
    private $catalogue;

    /** @var ManifestIndex */
    private $manifests;

    public function __construct(
        BlockRegistry $registry,
        TokenResolver $tokens,
        BlockCatalogue $catalogue,
        ManifestIndex $manifests
    ) {
        $this->registry  = $registry;
        $this->tokens    = $tokens;
        $this->catalogue = $catalogue;
        $this->manifests = $manifests;
    }

    /**
     * @param array $response  Raw response as the model produced it.
     * @param array $blockTree clientId => ['name' => string, 'attributes' => array, 'parentClientId' => string|null].
     *
     * @return string[] Violations; empty means the answer is acceptable.
     */
    public function audit(array $response, array $blockTree)
    {
        $violations = [];
        $actions    = isset($response['actions']) ? (array) $response['actions'] : [];

        if ($actions === []) {
            return ['actions: the model proposed nothing, so this case proves nothing.'];
        }

        foreach ($actions as $index => $action) {
            $path = 'actions.' . $index;
            $type = isset($action['type']) ? (string) $action['type'] : '';

            switch ($type) {
                case 'update_block_attributes':
                    foreach ($this->auditAttributeWrite($action, $blockTree, $path) as $violation) {
                        $violations[] = $violation;
                    }
                    break;

                case 'update_block_content':
                    foreach ($this->auditContentWrite($action, $blockTree, $path) as $violation) {
                        $violations[] = $violation;
                    }
                    break;

                case 'insert_block':
                    foreach ($this->auditInsert($action, $blockTree, $path) as $violation) {
                        $violations[] = $violation;
                    }
                    break;

                case 'remove_block':
                    foreach ($this->auditRemove($action, $blockTree, $path) as $violation) {
                        $violations[] = $violation;
                    }
                    break;

                default:
                    $violations[] = sprintf('%s.type: "%s" is not an action type this schema defines.', $path, $type);
                    break;
            }
        }

        return $violations;
    }

    /**
     * Characters measured on a live multisite to come back changed when a block
     * comment is filtered on save: "&" reparses as "&amp;" and ">" as "&gt;". The
     * value is not lost, it is quietly wrong, which is the worst failure of the
     * three. PlanValueStage refuses these for generated blocks; the in-editor
     * value stage does not, so an edit carrying one is a real finding here.
     */
    const COMMENT_UNSAFE = ['&', '>'];

    /**
     * Every attribute the validated actions would write survives serialisation
     * into a block comment and back.
     *
     * Two different failures are checked. The first is fidelity: the attribute
     * JSON is escaped going in — "--", "<", ">", "&" and a backslash all become
     * unicode escapes — and a value that breaks that encoding, or that terminates
     * the comment early, comes back different or does not parse at all. The
     * second is the KSES hazard above, which this cannot reproduce without
     * WordPress and therefore checks by refusing the characters known to trigger
     * it rather than by observing the rewrite.
     *
     * Numbers are compared by value. The value stage normalises every numeric
     * attribute to a float, and JSON stores a whole float as an integer, so a
     * type change between 2.0 and 2 is how storage works rather than a defect.
     *
     * @param array $validated Validated response.
     * @param array $blockTree Submitted block tree.
     *
     * @return string[]
     */
    public function auditStorageStability(array $validated, array $blockTree)
    {
        $violations = [];

        foreach ((array) $validated['actions'] as $index => $action) {
            $attributes = [];

            if ($action['type'] === 'update_block_attributes') {
                $attributes = (array) $action['changes'];
                $blockName  = $this->blockNameFor($action, $blockTree);
            } elseif ($action['type'] === 'insert_block') {
                $attributes = isset($action['attributes']) ? (array) $action['attributes'] : [];
                $blockName  = (string) $action['blockName'];
            } else {
                continue;
            }

            if ($attributes === [] || $blockName === null) {
                continue;
            }

            foreach ($attributes as $attribute => $value) {
                if (! is_string($value)) {
                    continue;
                }

                foreach (self::COMMENT_UNSAFE as $needle) {
                    if (strpos($value, $needle) !== false) {
                        $violations[] = sprintf(
                            'actions.%d.%s: "%s" is stored inside a block comment, where it is rewritten on save.',
                            $index,
                            $attribute,
                            $needle
                        );
                    }
                }
            }

            $markup = sprintf(
                '<!-- wp:%s %s /-->',
                strpos($blockName, 'core/') === 0 ? substr($blockName, 5) : $blockName,
                BlockMarkup::serializeAttributes($attributes)
            );

            try {
                $parsed = BlockMarkup::parse($markup);
            } catch (\RuntimeException $e) {
                $violations[] = sprintf('actions.%d: stored attributes do not reparse: %s', $index, $e->getMessage());
                continue;
            }

            $expected = self::asStored($attributes);

            if ($parsed === [] || $parsed[0]['attrs'] !== $expected) {
                $violations[] = sprintf(
                    'actions.%d: attributes on %s change when stored in a block comment. Wrote %s, read back %s.',
                    $index,
                    $blockName,
                    json_encode($expected),
                    $parsed === [] ? 'nothing' : json_encode($parsed[0]['attrs'])
                );
            }
        }

        return $violations;
    }

    /**
     * The same attribute map as JSON would hold it, so a float that is a whole
     * number is compared as the integer storage gives back.
     *
     * @param array $attributes Attribute map.
     *
     * @return array
     */
    private static function asStored(array $attributes)
    {
        return (array) json_decode(
            (string) json_encode($attributes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            true
        );
    }

    /**
     * @param array  $action    Action payload.
     * @param array  $blockTree Submitted block tree.
     * @param string $path      Error path.
     *
     * @return string[]
     */
    private function auditAttributeWrite(array $action, array $blockTree, $path)
    {
        $blockName = $this->blockNameFor($action, $blockTree);

        if ($blockName === null) {
            return [sprintf('%s.clientId: targets a block the editor never sent.', $path)];
        }

        $violations = $this->auditBlockName($blockName, $path);
        $capability = $this->registry->get($blockName);

        if ($capability === null) {
            return $violations;
        }

        if ($capability->getTier() !== 'full') {
            $violations[] = sprintf(
                '%s: %s is a content-tier block, so its attributes are not writable.',
                $path,
                $blockName
            );
        }

        foreach (
            CapabilityAudit::auditAttributes(
                $capability,
                isset($action['changes']) ? (array) $action['changes'] : [],
                $this->tokens,
                $this->manifests,
                $path . '.changes'
            ) as $violation
        ) {
            $violations[] = $violation;
        }

        return $violations;
    }

    /**
     * @param array  $action    Action payload.
     * @param array  $blockTree Submitted block tree.
     * @param string $path      Error path.
     *
     * @return string[]
     */
    private function auditContentWrite(array $action, array $blockTree, $path)
    {
        $blockName = $this->blockNameFor($action, $blockTree);

        if ($blockName === null) {
            return [sprintf('%s.clientId: targets a block the editor never sent.', $path)];
        }

        $violations = $this->auditBlockName($blockName, $path);
        $capability = $this->registry->get($blockName);

        if ($capability === null) {
            return $violations;
        }

        $field = null;

        foreach ($capability->getFields() as $candidate) {
            if ($candidate->getType() === 'richtext') {
                $field = $candidate;
                break;
            }
        }

        if ($field === null) {
            $violations[] = sprintf('%s: %s has no editable text content.', $path, $blockName);

            return $violations;
        }

        $max     = $field->getMaxLength();
        $content = isset($action['content']) ? (string) $action['content'] : '';

        if ($max !== null && CapabilityAudit::length($content) > $max) {
            $violations[] = sprintf(
                '%s.content: %d characters exceeds the manifest maximum of %d.',
                $path,
                CapabilityAudit::length($content),
                $max
            );
        }

        return $violations;
    }

    /**
     * @param array  $action    Action payload.
     * @param array  $blockTree Submitted block tree.
     * @param string $path      Error path.
     *
     * @return string[]
     */
    private function auditInsert(array $action, array $blockTree, $path)
    {
        $violations = [];
        $parentId   = isset($action['parentClientId']) ? (string) $action['parentClientId'] : '';
        $childName  = isset($action['blockName']) ? (string) $action['blockName'] : '';

        if (! isset($blockTree[$parentId])) {
            return [sprintf('%s.parentClientId: names a block the editor never sent.', $path)];
        }

        $parentName = (string) $blockTree[$parentId]['name'];
        $parent     = $this->registry->get($parentName);

        foreach ($this->auditBlockName($parentName, $path . '.parentClientId') as $violation) {
            $violations[] = $violation;
        }

        foreach ($this->auditBlockName($childName, $path . '.blockName') as $violation) {
            $violations[] = $violation;
        }

        if ($parent !== null && ! in_array($childName, $parent->getAllowedChildren(), true)) {
            $violations[] = sprintf(
                '%s.blockName: %s does not accept %s as a child.',
                $path,
                $parentName,
                $childName
            );
        }

        $child = $this->registry->get($childName);

        if ($child === null) {
            return $violations;
        }

        foreach (
            CapabilityAudit::auditAttributes(
                $child,
                isset($action['attributes']) ? (array) $action['attributes'] : [],
                $this->tokens,
                $this->manifests,
                $path . '.attributes'
            ) as $violation
        ) {
            $violations[] = $violation;
        }

        return $violations;
    }

    /**
     * @param array  $action    Action payload.
     * @param array  $blockTree Submitted block tree.
     * @param string $path      Error path.
     *
     * @return string[]
     */
    private function auditRemove(array $action, array $blockTree, $path)
    {
        $clientId = isset($action['clientId']) ? (string) $action['clientId'] : '';

        if (! isset($blockTree[$clientId])) {
            return [sprintf('%s.clientId: names a block the editor never sent.', $path)];
        }

        $violations = $this->auditBlockName((string) $blockTree[$clientId]['name'], $path);
        $parentId   = isset($blockTree[$clientId]['parentClientId']) ? $blockTree[$clientId]['parentClientId'] : null;

        if ($parentId === null || ! isset($blockTree[$parentId])) {
            return $violations;
        }

        $parent = $this->registry->get((string) $blockTree[$parentId]['name']);
        $min    = $parent === null ? null : $parent->getChildMin();

        if ($min === null) {
            return $violations;
        }

        $siblings = 0;

        foreach ($blockTree as $block) {
            if (isset($block['parentClientId']) && $block['parentClientId'] === $parentId) {
                $siblings++;
            }
        }

        if ($siblings - 1 < $min) {
            $violations[] = sprintf(
                '%s: removing this child would leave %s below its minimum of %d.',
                $path,
                (string) $blockTree[$parentId]['name'],
                $min
            );
        }

        return $violations;
    }

    /**
     * @param string $blockName Full block name.
     * @param string $path      Error path.
     *
     * @return string[]
     */
    private function auditBlockName($blockName, $path)
    {
        $violations = [];

        if (! $this->catalogue->isVerifiable($blockName)) {
            $violations[] = sprintf(
                '%s: "%s" cannot be verified as registered — WordPress core is not reachable from this checkout.',
                $path,
                $blockName
            );
        } elseif (! $this->catalogue->isRegistered($blockName)) {
            $violations[] = sprintf('%s: "%s" is not a registered block on this install.', $path, $blockName);
        }

        if ($this->registry->get($blockName) === null) {
            $violations[] = sprintf('%s: "%s" has no AI capability manifest.', $path, $blockName);
        }

        return $violations;
    }

    /**
     * @param array $action    Action payload.
     * @param array $blockTree Submitted block tree.
     *
     * @return string|null
     */
    private function blockNameFor(array $action, array $blockTree)
    {
        $clientId = isset($action['clientId']) ? (string) $action['clientId'] : '';

        return isset($blockTree[$clientId]) ? (string) $blockTree[$clientId]['name'] : null;
    }
}
