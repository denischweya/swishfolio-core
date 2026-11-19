<?php
/**
 * The owner's social proof (clients, a testimonial, key numbers) and the
 * marked placeholders that stand in for what they did not give.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Generation;

/**
 * Kit templates look finished because of their social proof: a wall of
 * client logos, a testimonial, a band of headline numbers. A generated site
 * that drops those sections whenever the owner gave none looks thin, so the
 * planner keeps them (kit units whose role is in ROLES):
 *
 *  - what the owner supplied in the wizard (clean()) is real content the
 *    plan prompts show as "ownerProof" and the sections must use;
 *  - anything missing is written as one of the fixed placeholders below,
 *    obviously a placeholder to the owner (never an invented company or
 *    quote), and the finishing step lists every one it finds on the built
 *    pages (find()) so the owner knows what is left to replace.
 *
 * Everything here is pure, so the policy is unit tested without WordPress.
 */
class SocialProof
{
    /** Kit unit roles that carry social proof. */
    const ROLES = ['logos', 'testimonials', 'stats'];

    const MAX_CLIENTS  = 12;
    const CLIENT_CHARS = 60;
    const MAX_NUMBERS  = 6;
    const NUMBER_CHARS = 80;
    const QUOTE_CHARS  = 600;
    const BY_CHARS     = 100;

    /** The placeholders the prompts ask for, word for word. */
    const CLIENT = 'Client name';
    const QUOTE  = "Your client's words go here: a sentence about the result you delivered.";
    const AUTHOR = 'Client name, role';
    const NUMBER = '00+';

    /**
     * The owner's proof from the wizard's business group (keys clients,
     * testimonial, testimonialBy, numbers), cleaned and capped.
     *
     * @param mixed $business Business group (raw or already sanitised).
     *
     * @return array {clients: string[], testimonials: [{quote, by}], numbers: string[]}
     */
    public static function fromBusiness($business)
    {
        $b = is_array($business) ? $business : [];

        return self::clean([
            'clients'      => isset($b['clients']) ? $b['clients'] : '',
            'testimonials' => [[
                'quote' => isset($b['testimonial']) ? $b['testimonial'] : '',
                'by'    => isset($b['testimonialBy']) ? $b['testimonialBy'] : '',
            ]],
            'numbers'      => isset($b['numbers']) ? $b['numbers'] : '',
        ]);
    }

    /**
     * @param mixed $raw {clients: string|string[], testimonials: [{quote, by}], numbers: string|string[]}.
     *
     * @return array Same shape, plain text, de-duplicated and capped.
     */
    public static function clean($raw)
    {
        $raw = is_array($raw) ? $raw : [];

        $testimonials = [];
        foreach (isset($raw['testimonials']) && is_array($raw['testimonials']) ? $raw['testimonials'] : [] as $item) {
            if (! is_array($item) || count($testimonials) >= 3) {
                continue;
            }
            $quote = self::line(isset($item['quote']) ? $item['quote'] : '', self::QUOTE_CHARS);
            // Quote marks are the design's job; the owner may have typed their own.
            $quote = trim($quote, " \"'\u{201C}\u{201D}\u{2018}\u{2019}");

            if ($quote === '' || self::isPlaceholder($quote)) {
                continue;
            }
            $testimonials[] = ['quote' => $quote, 'by' => self::line(isset($item['by']) ? $item['by'] : '', self::BY_CHARS)];
        }

        return [
            'clients'      => self::items(isset($raw['clients']) ? $raw['clients'] : '', self::MAX_CLIENTS, self::CLIENT_CHARS, '/\R|[,;|\x{2022}]/u'),
            'testimonials' => $testimonials,
            // "1,200 followers" keeps its comma: numbers split on lines, semicolons or ", ".
            'numbers'      => self::items(isset($raw['numbers']) ? $raw['numbers'] : '', self::MAX_NUMBERS, self::NUMBER_CHARS, '/\R|[;|\x{2022}]|,\s+/u'),
        ];
    }

    /**
     * @param array $proof clean() output.
     *
     * @return bool Whether the owner gave nothing at all.
     */
    public static function isEmpty(array $proof)
    {
        return empty($proof['clients']) && empty($proof['testimonials']) && empty($proof['numbers']);
    }

    /**
     * What the plan prompts show (site facts "ownerProof"): the owner's
     * items, and for each kind they left empty, the placeholder to use.
     *
     * @param array $proof clean() output.
     *
     * @return array
     */
    public static function forPrompt(array $proof)
    {
        $proof = self::clean($proof);

        return [
            'clients'      => $proof['clients'],
            'testimonials' => $proof['testimonials'],
            'numbers'      => $proof['numbers'],
            'placeholders' => [
                'clientName' => self::CLIENT,
                'quote'      => self::QUOTE,
                'quoteBy'    => self::AUTHOR,
                'number'     => self::NUMBER,
            ],
        ];
    }

    /**
     * The owner's proof as plain lines, for prompts that only take text
     * (the copy fill, the template path's facts). Empty when there is none.
     *
     * @param array $proof clean() output.
     *
     * @return string
     */
    public static function asText(array $proof)
    {
        $proof = self::clean($proof);
        $lines = [];

        if ($proof['clients']) {
            $lines[] = 'Brands or clients they have worked with: ' . implode(', ', $proof['clients']) . '.';
        }
        foreach ($proof['testimonials'] as $t) {
            $lines[] = 'A testimonial they gave: "' . $t['quote'] . '"' . ($t['by'] !== '' ? ', from ' . $t['by'] : '') . '.';
        }
        if ($proof['numbers']) {
            $lines[] = 'Key numbers they gave: ' . implode('; ', $proof['numbers']) . '.';
        }

        return implode(' ', $lines);
    }

    /**
     * @param string $role Kit unit role.
     *
     * @return bool
     */
    public static function isProofRole($role)
    {
        return in_array((string) $role, self::ROLES, true);
    }

    /**
     * The placeholders left in some visible text, by kind.
     *
     * @param string $text Visible text (DemoScan::visible()).
     *
     * @return int[] kind ('clients'|'testimonial'|'numbers') => how many.
     */
    public static function find($text)
    {
        $text = str_replace("\u{2019}", "'", (string) $text);
        $out  = [];

        $quotes = preg_match_all("/your client's words go here/i", $text);
        // "Client name, role" signs a placeholder quote; a bare "Client name" is a logo or a list entry.
        $authors = preg_match_all('/\bclient name,\s*role\b/i', $text);
        // Only the exact placeholder: a form's "Your brand name" field is real copy.
        $names   = preg_match_all('/\bclient name\b/i', $text) - $authors;
        $numbers = preg_match_all('/(?<![\w:.,\/])00(?:\+|%)(?!\d)/u', $text);

        if ($names > 0) {
            $out['clients'] = $names;
        }
        if ($quotes > 0 || $authors > 0) {
            $out['testimonial'] = max($quotes, $authors);
        }
        if ($numbers > 0) {
            $out['numbers'] = $numbers;
        }

        return $out;
    }

    /**
     * One "left to check" line for the owner per kind found on a page.
     *
     * @param string $kind  find() key.
     * @param string $where Page title.
     *
     * @return string
     */
    public static function label($kind, $where)
    {
        $labels = [
            'clients'     => 'Client or brand names on %s: replace "Client name" with the brands you have worked with, or remove the section.',
            'testimonial' => 'A testimonial on %s: replace the placeholder with a real client\'s words and name, or remove it.',
            'numbers'     => 'Numbers on %s: replace "00+" with your real figures, or remove them.',
        ];

        return sprintf(isset($labels[$kind]) ? $labels[$kind] : 'A placeholder on %s to replace.', (string) $where);
    }

    /**
     * @param string $text Some copy.
     *
     * @return bool Whether it is (or contains) one of the placeholders.
     */
    public static function isPlaceholder($text)
    {
        return self::find($text) !== [];
    }

    /**
     * @param mixed  $raw   A string (split on $split) or a list.
     * @param int    $max   Max items.
     * @param int    $chars Max characters per item.
     * @param string $split Split pattern for a string.
     *
     * @return string[]
     */
    private static function items($raw, $max, $chars, $split)
    {
        $parts = is_array($raw) ? $raw : preg_split($split, is_scalar($raw) ? (string) $raw : '');
        $out   = [];
        $seen  = [];

        foreach ((array) $parts as $part) {
            $part = trim(self::line($part, $chars), " \t-*\u{2022}.");
            $key  = function_exists('mb_strtolower') ? mb_strtolower($part) : strtolower($part);

            if ($part === '' || isset($seen[$key]) || self::isPlaceholder($part)) {
                continue;
            }
            $seen[$key] = true;
            $out[]      = $part;

            if (count($out) >= $max) {
                break;
            }
        }

        return $out;
    }

    /**
     * @param mixed $value Raw.
     * @param int   $max   Max length.
     *
     * @return string One plain line.
     */
    private static function line($value, $max)
    {
        $value = is_scalar($value) ? (string) $value : '';
        $value = strip_tags($value);
        // Long dashes are not house style; the owner's words get a comma instead.
        $value = (string) preg_replace('/\s*\x{2014}\s*|\s+\x{2013}\s+/u', ', ', $value);
        $value = trim((string) preg_replace('/\s+/u', ' ', (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value)));

        return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
    }
}
