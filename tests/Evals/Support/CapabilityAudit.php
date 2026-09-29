<?php
/**
 * The attribute-level half of the eval assertions, shared by both tracks.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Tests\Evals\Support;

use SwishfolioCore\AI\Context\TokenResolver;
use SwishfolioCore\Blocks\BlockCapability;
use SwishfolioCore\Blocks\FieldSpec;

/**
 * Written against the manifests and the live token sets rather than by calling
 * the validator, on purpose. Re-running ActionValidator and asserting it agrees
 * with itself proves nothing; these checks are an independent reading of the same
 * source of truth, so a stage that stops enforcing a rule shows up as a
 * disagreement rather than as two matching silences.
 *
 * Every method returns a list of human-readable violations instead of asserting,
 * so one eval case reports everything wrong with a model's answer at once.
 */
final class CapabilityAudit
{
    /**
     * Shapes a design-token slug can never take. A model that writes one of these
     * has bypassed the token vocabulary, and the value would survive a style
     * variation switch as a colour that no longer belongs to the palette.
     */
    const LITERAL_PATTERNS = [
        'a hex colour'          => '/^#[0-9a-fA-F]{3,8}$/',
        'a CSS colour function' => '/^(rgba?|hsla?)\s*\(/i',
        'a raw custom property' => '/^var\s*\(\s*--/',
    ];

    /**
     * Only meaningful where a design token belongs. A percentage or a length is
     * ordinary copy in a headline or a statistic — "92%" is a number a
     * photographer writes, not a CSS value a model leaked.
     */
    const DIMENSION_PATTERN = '/^-?\d+(\.\d+)?(px|rem|em|vh|vw|ch)$/';

    const ALLOWED_URL_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    /**
     * @param BlockCapability $capability Manifest for the block being written.
     * @param array           $attributes Attributes the model asked to set, raw.
     * @param TokenResolver   $tokens     Live design tokens.
     * @param ManifestIndex   $manifests  Raw manifests, for the excluded list.
     * @param string          $path       Human-readable location for messages.
     *
     * @return string[] Violations; empty means the write is acceptable.
     */
    public static function auditAttributes(
        BlockCapability $capability,
        array $attributes,
        TokenResolver $tokens,
        ManifestIndex $manifests,
        $path
    ) {
        $violations = [];
        $excluded   = $manifests->excludedFor($capability->getBlockName());

        foreach ($attributes as $attribute => $value) {
            $where = $path . '.' . $attribute;
            $field = $capability->getField($attribute);

            if (in_array((string) $attribute, $excluded, true)) {
                $violations[] = sprintf(
                    '%s: "%s" is an excluded attribute on %s and must never be attempted.',
                    $where,
                    $attribute,
                    $capability->getBlockName()
                );
            }

            if ($field === null) {
                $violations[] = sprintf(
                    '%s: "%s" is not in the %s capability manifest.',
                    $where,
                    $attribute,
                    $capability->getBlockName()
                );
                continue;
            }

            foreach (self::auditValue($field, $value, $tokens, $where) as $violation) {
                $violations[] = $violation;
            }
        }

        return $violations;
    }

    /**
     * @param FieldSpec     $field  Field the value is written to.
     * @param mixed         $value  Raw value from the model.
     * @param TokenResolver $tokens Live design tokens.
     * @param string        $where  Human-readable location.
     *
     * @return string[]
     */
    public static function auditValue(FieldSpec $field, $value, TokenResolver $tokens, $where)
    {
        $violations = [];

        if (is_array($value) || is_object($value)) {
            return [sprintf('%s: value is a %s; only scalars are storable.', $where, gettype($value))];
        }

        switch ($field->getType()) {
            case 'token':
                $literal = self::literalReason($value, true);

                if ($literal !== null) {
                    $violations[] = sprintf(
                        '%s: "%s" is %s, not a %s token slug. Design tokens are the only safe currency here.',
                        $where,
                        (string) $value,
                        $literal,
                        $field->getTokenSet()
                    );
                    break;
                }

                if (! $tokens->isValid($field->getTokenSet(), (string) $value)) {
                    $violations[] = sprintf(
                        '%s: "%s" is not a %s slug in this theme.json. Known slugs: %s.',
                        $where,
                        (string) $value,
                        $field->getTokenSet(),
                        implode(', ', $tokens->getSlugs($field->getTokenSet()))
                    );
                }
                break;

            case 'enum':
                if (! in_array($value, $field->getEnum(), true)) {
                    $violations[] = sprintf(
                        '%s: "%s" is not one of %s.',
                        $where,
                        is_scalar($value) ? (string) $value : gettype($value),
                        implode(', ', array_map('strval', $field->getEnum()))
                    );
                }
                break;

            case 'number':
                if (! is_numeric($value)) {
                    $violations[] = sprintf('%s: "%s" is not a number.', $where, (string) $value);
                    break;
                }

                $number = (float) $value;

                if ($field->getMin() !== null && $number < $field->getMin()) {
                    $violations[] = sprintf('%s: %s is below the minimum of %s.', $where, $number, $field->getMin());
                }

                if ($field->getMax() !== null && $number > $field->getMax()) {
                    $violations[] = sprintf('%s: %s is above the maximum of %s.', $where, $number, $field->getMax());
                }
                break;

            case 'boolean':
                if (! is_bool($value)) {
                    $violations[] = sprintf('%s: "%s" is not a boolean.', $where, (string) $value);
                }
                break;

            case 'url':
                foreach (self::auditUrl((string) $value, $where) as $violation) {
                    $violations[] = $violation;
                }
                break;

            default:
                foreach (self::auditText($field, (string) $value, $where) as $violation) {
                    $violations[] = $violation;
                }
                break;
        }

        return $violations;
    }

    /**
     * @param FieldSpec $field Text or richtext field.
     * @param string    $value Raw value.
     * @param string    $where Human-readable location.
     *
     * @return string[]
     */
    private static function auditText(FieldSpec $field, $value, $where)
    {
        $violations = [];
        $max        = $field->getMaxLength();

        if ($max !== null && self::length($value) > $max) {
            $violations[] = sprintf(
                '%s: %d characters exceeds the manifest maximum of %d.',
                $where,
                self::length($value),
                $max
            );
        }

        $literal = self::literalReason($value);

        if ($literal !== null) {
            $violations[] = sprintf(
                '%s: "%s" is %s written into a %s field, not copy.',
                $where,
                $value,
                $literal,
                $field->getType()
            );
        }

        return $violations;
    }

    /**
     * @param string $url   URL to check.
     * @param string $where Human-readable location.
     *
     * @return string[]
     */
    public static function auditUrl($url, $where)
    {
        $url = trim((string) $url);

        if ($url === '') {
            return [];
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if ($scheme !== '' && ! in_array($scheme, self::ALLOWED_URL_SCHEMES, true)) {
            return [sprintf('%s: URL scheme "%s" is not permitted.', $where, $scheme)];
        }

        return [];
    }

    /**
     * @param mixed $value      Candidate value.
     * @param bool  $dimensions Whether a bare CSS length also counts as a literal.
     *
     * @return string|null Why it reads as a literal, or null when it does not.
     */
    public static function literalReason($value, $dimensions = false)
    {
        if (! is_string($value)) {
            return null;
        }

        $candidate = trim($value);

        foreach (self::LITERAL_PATTERNS as $reason => $pattern) {
            if (preg_match($pattern, $candidate) === 1) {
                return $reason;
            }
        }

        if ($dimensions && preg_match(self::DIMENSION_PATTERN, $candidate) === 1) {
            return 'a literal CSS length';
        }

        return null;
    }

    /**
     * @param string $value String to measure.
     *
     * @return int
     */
    public static function length($value)
    {
        return function_exists('mb_strlen') ? mb_strlen((string) $value) : strlen((string) $value);
    }
}
