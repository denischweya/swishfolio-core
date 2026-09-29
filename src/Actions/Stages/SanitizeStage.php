<?php
/**
 * Stage 5: values are sanitised for their field type.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Actions\Stages;

use SwishfolioCore\Actions\ValidationContext;
use SwishfolioCore\Blocks\BlockCapability;
use SwishfolioCore\Blocks\FieldSpec;

class SanitizeStage implements StageInterface
{
    public function getSlug()
    {
        return 'sanitize';
    }

    public function process(array $response, ValidationContext $context)
    {
        foreach ($response['actions'] as $index => $action) {
            if ($action['type'] === 'update_block_attributes') {
                $capability = $this->capabilityFor($context, $action['clientId']);

                $response['actions'][$index]['changes'] = $this->sanitizeAll($capability, $action['changes']);
                continue;
            }

            if ($action['type'] === 'insert_block') {
                $capability = $context->getRegistry()->get($action['blockName']);
                $attributes = isset($action['attributes']) ? (array) $action['attributes'] : [];

                $response['actions'][$index]['attributes'] = $this->sanitizeAll($capability, $attributes);
                continue;
            }

            if ($action['type'] === 'update_block_content') {
                $response['actions'][$index]['content'] = wp_kses_post((string) $action['content']);
            }
        }

        return $response;
    }

    private function sanitizeAll(BlockCapability $capability, array $values)
    {
        $clean = [];

        foreach ($values as $attribute => $value) {
            $clean[$attribute] = $this->sanitize($capability->getField($attribute), $value);
        }

        return $clean;
    }

    private function sanitize(FieldSpec $field, $value)
    {
        switch ($field->getType()) {
            case 'richtext':
                return wp_kses_post((string) $value);

            case 'url':
                return esc_url_raw((string) $value);

            case 'text':
                return sanitize_text_field((string) $value);

            default:
                // Enums, tokens, numbers and booleans were normalised by the
                // value stage and are not free-form strings.
                return $value;
        }
    }

    private function capabilityFor(ValidationContext $context, $clientId)
    {
        $block = $context->getBlock($clientId);

        return $context->getRegistry()->get($block['name']);
    }
}
