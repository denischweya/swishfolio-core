<?php
/**
 * Stage 4: every value satisfies its field's constraints.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Actions\Stages;

use SwishfolioCore\Actions\ValidationContext;
use SwishfolioCore\Actions\ValidationException;
use SwishfolioCore\Blocks\BlockCapability;
use SwishfolioCore\Blocks\FieldSpec;

/**
 * Also performs the one transformation in the pipeline: a validated design
 * token slug is expanded to the CSS custom property blocks actually store, so
 * everything downstream sees final attribute values.
 */
class ValueStage implements StageInterface
{
    const ALLOWED_URL_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    public function getSlug()
    {
        return 'value';
    }

    public function process(array $response, ValidationContext $context)
    {
        foreach ($response['actions'] as $index => $action) {
            $path = 'actions.' . $index;

            if ($action['type'] === 'update_block_attributes') {
                $capability = $this->capabilityFor($context, $action['clientId']);

                $response['actions'][$index]['changes'] = $this->checkAll(
                    $capability,
                    $action['changes'],
                    $context,
                    $path . '.changes'
                );
                continue;
            }

            if ($action['type'] === 'insert_block') {
                $capability = $context->getRegistry()->get($action['blockName']);
                $attributes = isset($action['attributes']) ? (array) $action['attributes'] : [];

                $response['actions'][$index]['attributes'] = $this->checkAll(
                    $capability,
                    $attributes,
                    $context,
                    $path . '.attributes'
                );
                continue;
            }

            if ($action['type'] === 'update_block_content') {
                $capability = $this->capabilityFor($context, $action['clientId']);
                $field      = $this->contentField($capability);
                $max        = $field === null ? null : $field->getMaxLength();

                if ($max !== null && strlen((string) $action['content']) > $max) {
                    throw new ValidationException(
                        $this->getSlug(),
                        sprintf('Content exceeds %d characters.', $max),
                        $path . '.content'
                    );
                }
            }
        }

        return $response;
    }

    private function checkAll(BlockCapability $capability, array $values, ValidationContext $context, $path)
    {
        $checked = [];

        foreach ($values as $attribute => $value) {
            $field = $capability->getField($attribute);

            // The allowlist stage has already proved the field exists.
            $checked[$attribute] = $this->check($field, $value, $context, $path . '.' . $attribute);
        }

        return $checked;
    }

    private function check(FieldSpec $field, $value, ValidationContext $context, $path)
    {
        switch ($field->getType()) {
            case 'enum':
                if (! in_array($value, $field->getEnum(), true)) {
                    throw new ValidationException(
                        $this->getSlug(),
                        sprintf('"%s" is not one of: %s.', (string) $value, implode(', ', $field->getEnum())),
                        $path
                    );
                }

                return $value;

            case 'token':
                if (! $context->getTokens()->isValid($field->getTokenSet(), (string) $value)) {
                    throw new ValidationException(
                        $this->getSlug(),
                        sprintf(
                            '"%s" is not a %s token in this theme. Use a slug such as: %s.',
                            (string) $value,
                            $field->getTokenSet(),
                            implode(', ', array_slice($context->getTokens()->getSlugs($field->getTokenSet()), 0, 5))
                        ),
                        $path
                    );
                }

                return $context->getTokens()->toStorageValue($field->getTokenSet(), (string) $value);

            case 'number':
                if (! is_numeric($value)) {
                    throw new ValidationException($this->getSlug(), 'Value must be a number.', $path);
                }

                $number = (float) $value;

                if ($field->getMin() !== null && $number < $field->getMin()) {
                    throw new ValidationException(
                        $this->getSlug(),
                        sprintf('%s is below the minimum of %s.', $number, $field->getMin()),
                        $path
                    );
                }

                if ($field->getMax() !== null && $number > $field->getMax()) {
                    throw new ValidationException(
                        $this->getSlug(),
                        sprintf('%s is above the maximum of %s.', $number, $field->getMax()),
                        $path
                    );
                }

                return $number;

            case 'boolean':
                if (is_bool($value)) {
                    return $value;
                }

                if (in_array($value, ['true', '1', 1], true)) {
                    return true;
                }

                if (in_array($value, ['false', '0', 0], true)) {
                    return false;
                }

                throw new ValidationException($this->getSlug(), 'Value must be true or false.', $path);

            case 'url':
                $url    = trim((string) $value);
                $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

                if ($url !== '' && $scheme !== '' && ! in_array($scheme, self::ALLOWED_URL_SCHEMES, true)) {
                    throw new ValidationException(
                        $this->getSlug(),
                        sprintf('URL scheme "%s" is not permitted.', $scheme),
                        $path
                    );
                }

                return $url;

            default:
                $string = (string) $value;
                $max    = $field->getMaxLength();

                if ($max !== null && strlen($string) > $max) {
                    throw new ValidationException(
                        $this->getSlug(),
                        sprintf('Value exceeds %d characters.', $max),
                        $path
                    );
                }

                return $string;
        }
    }

    private function capabilityFor(ValidationContext $context, $clientId)
    {
        $block = $context->getBlock($clientId);

        return $context->getRegistry()->get($block['name']);
    }

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
