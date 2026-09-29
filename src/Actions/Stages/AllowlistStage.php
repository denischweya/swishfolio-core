<?php
/**
 * Stage 3: every attribute touched is one the manifest exposes.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Actions\Stages;

use SwishfolioCore\Actions\ValidationContext;
use SwishfolioCore\Actions\ValidationException;
use SwishfolioCore\Blocks\BlockCapability;

/**
 * The capability manifest is the allowlist. An attribute the manifest does not
 * expose is not blocked by a rule here — it simply cannot be resolved, so it is
 * unreachable by construction.
 */
class AllowlistStage implements StageInterface
{
    public function getSlug()
    {
        return 'allowlist';
    }

    public function process(array $response, ValidationContext $context)
    {
        foreach ($response['actions'] as $index => $action) {
            $path = 'actions.' . $index;

            switch ($action['type']) {
                case 'update_block_attributes':
                    $capability = $this->capabilityFor($context, $action['clientId'], $path);

                    if ($capability->getTier() !== 'full') {
                        throw new ValidationException(
                            $this->getSlug(),
                            sprintf('Block "%s" only supports content edits.', $capability->getBlockName()),
                            $path
                        );
                    }

                    $this->assertAttributesAllowed($capability, $action['changes'], $path . '.changes');
                    break;

                case 'update_block_content':
                    $capability = $this->capabilityFor($context, $action['clientId'], $path);

                    if ($this->contentField($capability) === null) {
                        throw new ValidationException(
                            $this->getSlug(),
                            sprintf('Block "%s" has no editable text content.', $capability->getBlockName()),
                            $path
                        );
                    }
                    break;

                case 'insert_block':
                    $parent = $this->capabilityFor($context, $action['parentClientId'], $path);
                    $child  = $action['blockName'];

                    if (! in_array($child, $parent->getAllowedChildren(), true)) {
                        throw new ValidationException(
                            $this->getSlug(),
                            sprintf('Block "%s" does not accept "%s" as a child.', $parent->getBlockName(), $child),
                            $path . '.blockName'
                        );
                    }

                    $this->assertChildCountWithinMax($context, $parent, $action['parentClientId'], $path);

                    $childCapability = $context->getRegistry()->get($child);

                    if ($childCapability === null) {
                        throw new ValidationException(
                            $this->getSlug(),
                            sprintf('Block "%s" has no AI capability manifest.', $child),
                            $path . '.blockName'
                        );
                    }

                    $attributes = isset($action['attributes']) ? (array) $action['attributes'] : [];
                    $this->assertAttributesAllowed($childCapability, $attributes, $path . '.attributes');
                    break;

                case 'remove_block':
                    $this->assertChildCountWithinMin($context, $action['clientId'], $path);
                    break;
            }
        }

        return $response;
    }

    /**
     * @return BlockCapability
     */
    private function capabilityFor(ValidationContext $context, $clientId, $path)
    {
        $block      = $context->getBlock($clientId);
        $blockName  = $block === null ? '' : $block['name'];
        $capability = $context->getRegistry()->get($blockName);

        if ($capability === null) {
            throw new ValidationException(
                $this->getSlug(),
                sprintf('Block "%s" has no AI capability manifest.', $blockName),
                $path
            );
        }

        return $capability;
    }

    private function assertAttributesAllowed(BlockCapability $capability, array $attributes, $path)
    {
        foreach (array_keys($attributes) as $attribute) {
            if ($capability->getField($attribute) === null) {
                throw new ValidationException(
                    $this->getSlug(),
                    sprintf('Attribute "%s" is not AI-editable on %s.', $attribute, $capability->getBlockName()),
                    $path . '.' . $attribute
                );
            }
        }
    }

    private function assertChildCountWithinMax(ValidationContext $context, BlockCapability $parent, $parentClientId, $path)
    {
        $max = $parent->getChildMax();

        if ($max === null) {
            return;
        }

        if ($this->countChildren($context, $parentClientId) >= $max) {
            throw new ValidationException(
                $this->getSlug(),
                sprintf('Block "%s" already holds its maximum of %d children.', $parent->getBlockName(), $max),
                $path
            );
        }
    }

    private function assertChildCountWithinMin(ValidationContext $context, $clientId, $path)
    {
        $block = $context->getBlock($clientId);

        if ($block === null || empty($block['parentClientId'])) {
            return;
        }

        $parentBlock = $context->getBlock($block['parentClientId']);

        if ($parentBlock === null) {
            return;
        }

        $parent = $context->getRegistry()->get($parentBlock['name']);
        $min    = $parent === null ? null : $parent->getChildMin();

        if ($min !== null && $this->countChildren($context, $block['parentClientId']) <= $min) {
            throw new ValidationException(
                $this->getSlug(),
                sprintf('Block "%s" requires at least %d children.', $parentBlock['name'], $min),
                $path
            );
        }
    }

    private function countChildren(ValidationContext $context, $parentClientId)
    {
        $count = 0;

        foreach ($context->getBlockTree() as $block) {
            if (isset($block['parentClientId']) && $block['parentClientId'] === $parentClientId) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return \SwishfolioCore\Blocks\FieldSpec|null
     */
    private function contentField(BlockCapability $capability)
    {
        foreach ($capability->getFields() as $field) {
            if ($field->getType() === 'richtext') {
                return $field;
            }
        }

        return null;
    }
}
