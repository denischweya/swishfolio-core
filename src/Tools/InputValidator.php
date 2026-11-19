<?php
/**
 * Validates tool input against a JSON Schema subset.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Tools;

/**
 * The plugin ships zero runtime Composer dependencies and that holds here, so
 * this is a deliberately small validator rather than a general one.
 *
 * It supports exactly the keywords the tool schemas use, and raises a
 * LogicException on any keyword it does not recognise. Silently ignoring an
 * unknown keyword is the dangerous failure: a schema author would believe a
 * constraint was being enforced when it was not.
 *
 * Model output is coerced where the intent is unambiguous — "12" for an integer,
 * 1 for a boolean — because providers vary in how they encode scalars and
 * rejecting those would fail calls that were correct in substance. A string
 * where an object belongs is not coerced; that is a real mistake.
 */
class InputValidator
{
    const KNOWN_KEYWORDS = [
        'type', 'required', 'properties', 'additionalProperties', 'enum',
        'minimum', 'maximum', 'maxLength', 'minItems', 'maxItems', 'items',
        'default', 'description', 'title',
    ];

    /**
     * @param array $schema JSON Schema for the input object.
     * @param mixed $input  Raw input.
     *
     * @throws SchemaViolation When the input does not conform.
     *
     * @return array Normalised input with defaults applied.
     */
    public function validate(array $schema, $input)
    {
        if (! is_array($input)) {
            throw new SchemaViolation('', __('Input must be an object.', 'swishfolio-core'));
        }

        $validated = $this->check($schema, $input, '');

        return is_array($validated) ? $validated : [];
    }

    /**
     * @param array  $schema Schema node.
     * @param mixed  $value  Value to check.
     * @param string $path   Dotted path for error reporting.
     *
     * @throws SchemaViolation When the value does not conform.
     *
     * @return mixed Normalised value.
     */
    private function check(array $schema, $value, $path)
    {
        $this->assertKnownKeywords($schema, $path);

        $type = isset($schema['type']) ? (string) $schema['type'] : '';

        if ('object' === $type) {
            return $this->checkObject($schema, $value, $path);
        }

        if ('array' === $type) {
            return $this->checkArray($schema, $value, $path);
        }

        $value = $this->coerceScalar($type, $value, $path);

        if (isset($schema['enum']) && ! in_array($value, $schema['enum'], true)) {
            throw new SchemaViolation(
                $path,
                sprintf(
                    /* translators: %s: comma-separated list of allowed values. */
                    __('Must be one of: %s.', 'swishfolio-core'),
                    implode(', ', array_map('strval', $schema['enum']))
                )
            );
        }

        if (isset($schema['maxLength']) && is_string($value) && mb_strlen($value) > (int) $schema['maxLength']) {
            throw new SchemaViolation(
                $path,
                sprintf(
                    /* translators: %d: maximum number of characters. */
                    __('Must be %d characters or fewer.', 'swishfolio-core'),
                    (int) $schema['maxLength']
                )
            );
        }

        if (isset($schema['minimum']) && is_numeric($value) && $value < $schema['minimum']) {
            throw new SchemaViolation(
                $path,
                sprintf(
                    /* translators: %s: minimum allowed value. */
                    __('Must be at least %s.', 'swishfolio-core'),
                    (string) $schema['minimum']
                )
            );
        }

        if (isset($schema['maximum']) && is_numeric($value) && $value > $schema['maximum']) {
            throw new SchemaViolation(
                $path,
                sprintf(
                    /* translators: %s: maximum allowed value. */
                    __('Must be at most %s.', 'swishfolio-core'),
                    (string) $schema['maximum']
                )
            );
        }

        return $value;
    }

    /**
     * @param array  $schema Object schema node.
     * @param mixed  $value  Value to check.
     * @param string $path   Dotted path.
     *
     * @throws SchemaViolation When the value does not conform.
     *
     * @return array
     */
    private function checkObject(array $schema, $value, $path)
    {
        if (! is_array($value)) {
            throw new SchemaViolation($path, __('Must be an object.', 'swishfolio-core'));
        }

        $properties = isset($schema['properties']) ? $schema['properties'] : [];
        $required   = isset($schema['required']) ? $schema['required'] : [];
        $closed     = ! isset($schema['additionalProperties']) || false === $schema['additionalProperties'];

        foreach ($required as $name) {
            if (! array_key_exists($name, $value)) {
                throw new SchemaViolation(
                    $this->join($path, $name),
                    __('This field is required.', 'swishfolio-core')
                );
            }
        }

        if ($closed) {
            foreach (array_keys($value) as $name) {
                if (! isset($properties[$name])) {
                    throw new SchemaViolation(
                        $this->join($path, $name),
                        __('Unknown field.', 'swishfolio-core')
                    );
                }
            }
        }

        $out = [];

        foreach ($properties as $name => $propertySchema) {
            $childPath = $this->join($path, $name);

            if (array_key_exists($name, $value)) {
                $out[$name] = $this->check($propertySchema, $value[$name], $childPath);

                continue;
            }

            if (is_array($propertySchema) && array_key_exists('default', $propertySchema)) {
                $out[$name] = $propertySchema['default'];
            }
        }

        return $out;
    }

    /**
     * @param array  $schema Array schema node.
     * @param mixed  $value  Value to check.
     * @param string $path   Dotted path.
     *
     * @throws SchemaViolation When the value does not conform.
     *
     * @return array
     */
    private function checkArray(array $schema, $value, $path)
    {
        if (! is_array($value) || ($value !== [] && array_keys($value) !== range(0, count($value) - 1))) {
            throw new SchemaViolation($path, __('Must be a list.', 'swishfolio-core'));
        }

        if (isset($schema['minItems']) && count($value) < (int) $schema['minItems']) {
            throw new SchemaViolation(
                $path,
                sprintf(
                    /* translators: %d: minimum number of items. */
                    __('Must have at least %d items.', 'swishfolio-core'),
                    (int) $schema['minItems']
                )
            );
        }

        if (isset($schema['maxItems']) && count($value) > (int) $schema['maxItems']) {
            throw new SchemaViolation(
                $path,
                sprintf(
                    /* translators: %d: maximum number of items. */
                    __('Must have at most %d items.', 'swishfolio-core'),
                    (int) $schema['maxItems']
                )
            );
        }

        if (! isset($schema['items'])) {
            return $value;
        }

        $out = [];

        foreach ($value as $index => $item) {
            $out[] = $this->check($schema['items'], $item, $path . '[' . $index . ']');
        }

        return $out;
    }

    /**
     * @param string $type  Schema type.
     * @param mixed  $value Value to coerce.
     * @param string $path  Dotted path.
     *
     * @throws SchemaViolation When the value is not that type.
     *
     * @return mixed
     */
    private function coerceScalar($type, $value, $path)
    {
        if ('' === $type) {
            return $value;
        }

        if (is_array($value)) {
            throw new SchemaViolation($path, $this->typeMessage($type));
        }

        if ('string' === $type) {
            if (! is_string($value)) {
                throw new SchemaViolation($path, $this->typeMessage($type));
            }

            return $value;
        }

        if ('integer' === $type) {
            if (is_int($value)) {
                return $value;
            }

            if ((is_string($value) || is_float($value)) && is_numeric($value) && (float) $value === floor((float) $value)) {
                return (int) $value;
            }

            throw new SchemaViolation($path, $this->typeMessage($type));
        }

        if ('number' === $type) {
            if (is_int($value) || is_float($value)) {
                return $value;
            }

            if (is_string($value) && is_numeric($value)) {
                return (float) $value;
            }

            throw new SchemaViolation($path, $this->typeMessage($type));
        }

        if ('boolean' === $type) {
            if (is_bool($value)) {
                return $value;
            }

            if (1 === $value || 0 === $value || 'true' === $value || 'false' === $value) {
                return 'true' === $value || 1 === $value;
            }

            throw new SchemaViolation($path, $this->typeMessage($type));
        }

        throw new \LogicException(sprintf('Unsupported schema type "%s" at "%s".', $type, $path));
    }

    /**
     * @param string $type Schema type.
     *
     * @return string
     */
    private function typeMessage($type)
    {
        $messages = [
            'string'  => __('Must be text.', 'swishfolio-core'),
            'integer' => __('Must be a whole number.', 'swishfolio-core'),
            'number'  => __('Must be a number.', 'swishfolio-core'),
            'boolean' => __('Must be true or false.', 'swishfolio-core'),
        ];

        return isset($messages[$type]) ? $messages[$type] : __('Wrong type.', 'swishfolio-core');
    }

    /**
     * @param array  $schema Schema node.
     * @param string $path   Dotted path.
     *
     * @return void
     */
    private function assertKnownKeywords(array $schema, $path)
    {
        foreach (array_keys($schema) as $keyword) {
            if (! in_array($keyword, self::KNOWN_KEYWORDS, true)) {
                throw new \LogicException(
                    sprintf('Unsupported schema keyword "%s" at "%s".', $keyword, '' === $path ? '(root)' : $path)
                );
            }
        }
    }

    /**
     * @param string $path Parent path.
     * @param string $name Property name.
     *
     * @return string
     */
    private function join($path, $name)
    {
        return '' === $path ? (string) $name : $path . '.' . $name;
    }
}
