<?php
/**
 * Finds the demo business's details left on a generated site.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Onboarding;

/**
 * Every kit is cut from a demo site for a made-up business. Generation and
 * the header/footer pass rewrite its copy, but a leftover name, phone number,
 * email, street or team member on the owner's live site is the worst kind of
 * mistake, so a build ends with this scan over everything it wrote.
 *
 * Needles come from the kit: the demo business and tagline (kit.json "demo")
 * and the strings seed/extract-demo-strings.php found on the template site
 * (demo.json: phones, emails, email domains, addresses, places, people),
 * plus phrases that only demo copy uses ("demo form", "fictional").
 *
 * fix() replaces what has a clear owner equivalent (the business name, the
 * phone, the email) and leaves the rest for the report. Pure.
 */
class DemoScan
{
    /** Phrases that only ever appear in demo copy. */
    const PHRASES = ['demo form', 'demonstration', 'fictional', 'nothing is sent', 'does not send', 'lorem ipsum', 'example.com'];

    /** @var array[] {text, kind} */
    private $needles = [];

    /** @var string[] Addresses the owner gave us, never a leftover (even at example.com). */
    private $ownerEmails = [];

    /**
     * @param array $demo kit demo: business, tagline, phones, emails, domains, addresses, places, people.
     */
    public function __construct(array $demo)
    {
        $add = function ($text, $kind) {
            $text = trim(html_entity_decode((string) $text, ENT_QUOTES, 'UTF-8'));
            if (mb_strlen($text) >= 3) {
                $this->needles[mb_strtolower($text)] = ['text' => $text, 'kind' => $kind];
            }
        };

        $business = isset($demo['business']) ? (string) $demo['business'] : '';
        if ($business !== '') {
            $add($business, 'business');
            // "Ashcombe Vale LLP" is mostly written "Ashcombe Vale".
            $short = preg_replace('/\s+(LLP|Ltd|Inc|Partners|Studio|Freight|Heating\s*&(amp;)?\s*Air)$/i', '', $business);
            if ($short !== $business) {
                $add($short, 'business');
            }
        }

        if (! empty($demo['tagline'])) {
            $add($demo['tagline'], 'tagline');
        }

        $kinds = ['phones' => 'phone', 'emails' => 'email', 'domains' => 'domain', 'addresses' => 'address', 'places' => 'place', 'people' => 'person', 'strings' => 'other'];
        foreach ($kinds as $key => $kind) {
            foreach (isset($demo[$key]) ? (array) $demo[$key] : [] as $text) {
                $add($text, $kind);
            }
        }

        // Words the kit draws in its graphics (badge rings, map labels): the
        // whole phrase, and each part of two words or more between separators.
        foreach (isset($demo['drawn']) ? (array) $demo['drawn'] : [] as $text) {
            $text = trim((string) $text, " \t\x{00B7}");
            $add(trim(preg_replace('/\s*[\x{00B7}\x{2022}|]\s*$/u', '', $text)), 'drawn');
            foreach (preg_split('/\s*[\x{00B7}\x{2022}|]\s*/u', $text) as $part) {
                // Three words or more: a two-word part ("Whole table") is
                // ordinary English and turns up in owners' own copy.
                if (preg_match('/\S+\s+\S+\s+\S+/u', trim($part))) {
                    $add($part, 'drawn');
                }
            }
        }

        foreach (self::PHRASES as $phrase) {
            $add($phrase, 'phrase');
        }
    }

    /**
     * Forget needles the owner uses themselves (a demo set in London and an
     * owner in London): their own town or name is not a leftover.
     *
     * @param string $ownerText Everything the owner told us, as text.
     *
     * @return self
     */
    public function exceptOwner($ownerText)
    {
        foreach ($this->needles as $key => $needle) {
            if ($needle['kind'] !== 'phrase' && preg_match(self::pattern($needle['text']), (string) $ownerText)) {
                unset($this->needles[$key]);
            }
        }

        if (preg_match_all('/[^\s<>"\'@]+@[a-z0-9.-]+\.[a-z]{2,}/i', (string) $ownerText, $emails)) {
            $this->ownerEmails = array_values(array_unique($emails[0]));
        }

        return $this;
    }

    /**
     * @return array[] {text, kind}
     */
    public function needles()
    {
        return array_values($this->needles);
    }

    /**
     * The words a visitor (or a screen reader, or a search engine) can see:
     * text, alt / title / aria-label / placeholder values and tel:/mailto:
     * targets. Image file URLs, classes and styles are left out.
     *
     * @param string $markup Markup.
     *
     * @return string
     */
    public static function visible($markup)
    {
        $markup = (string) $markup;
        $extra  = [];

        if (preg_match_all('/\b(?:alt|title|aria-label|aria-description|aria-valuetext|placeholder|value|content|label|data-label|data-title|data-text)="([^"]*)"/i', $markup, $m)) {
            $extra = array_merge($extra, $m[1]);
        }
        if (preg_match_all('/\bhref="((?:tel|mailto):[^"]*)"/i', $markup, $m)) {
            $extra = array_merge($extra, $m[1]);
        }
        // Block comment attributes that render as text (labels, form fields, messages).
        if (preg_match_all('/"(?:label|placeholder|submitButtonText|successMessage|emailSubject|recipientEmail|content|alt|caption)":"((?:[^"\\\\]|\\\\.)*)"/', $markup, $m)) {
            foreach ($m[1] as $json) {
                $decoded = json_decode('"' . $json . '"');
                $extra[] = is_string($decoded) ? $decoded : $json;
            }
        }

        $text = preg_replace('/<!--.*?-->/s', ' ', $markup);
        $text = preg_replace('/<(script|style)\b.*?<\/\1>/is', ' ', $text);
        $text = strip_tags(preg_replace('/<[^>]+>/', ' $0 ', $text));

        return html_entity_decode(preg_replace('/\s+/u', ' ', $text . ' ' . implode(' ', $extra)), ENT_QUOTES, 'UTF-8');
    }

    /**
     * @param string $text Visible text (see visible()).
     *
     * @return array[] {text, kind, count}
     */
    public function scan($text)
    {
        $hits = [];
        $text = str_ireplace($this->ownerEmails, ' ', (string) $text);

        foreach ($this->needles as $needle) {
            $count = preg_match_all(self::pattern($needle['text']), $text);
            if ($count) {
                $hits[] = ['text' => $needle['text'], 'kind' => $needle['kind'], 'count' => (int) $count];
            }
        }

        return $hits;
    }

    /**
     * Replace the leftovers that have an owner equivalent.
     *
     * @param string $markup Markup.
     * @param array  $owner  business, phone, email.
     *
     * @return string
     */
    public function fix($markup, array $owner)
    {
        $map = [
            'business' => isset($owner['business']) ? (string) $owner['business'] : '',
            'phone'    => isset($owner['phone']) ? (string) $owner['phone'] : '',
            'email'    => isset($owner['email']) ? (string) $owner['email'] : '',
        ];

        // Longest first, so "Ashcombe Vale LLP" goes before "Ashcombe Vale".
        $needles = $this->needles;
        uasort($needles, static function ($a, $b) {
            return mb_strlen($b['text']) - mb_strlen($a['text']);
        });

        foreach ($needles as $needle) {
            $with = isset($map[$needle['kind']]) ? $map[$needle['kind']] : '';
            if ($with === '') {
                continue;
            }

            $markup = self::replaceVisible((string) $markup, $needle, $with);
        }

        return (string) $markup;
    }

    /**
     * What has no owner equivalent, made neutral where that is safe:
     *
     * - words drawn in a graphic (<text>, <textPath>, <tspan> inside an SVG)
     *   that still hold a demo detail become the owner's business name,
     *   repeated round a ring the way the demo text was;
     * - a demo place or person in an image's alt text, a title or an
     *   aria-label is cut out with the little word before it ("Inside the
     *   office in Austin" reads "Inside the office").
     *
     * Visible running text is never cut here: that is copy, and it is
     * reported instead.
     *
     * @param string $markup   Markup.
     * @param string $business The owner's business name.
     *
     * @return string
     */
    public function neutralise($markup, $business)
    {
        $markup = (string) $markup;

        if ($business !== '' && stripos($markup, '<svg') !== false) {
            $markup = (string) preg_replace_callback('#<(textPath|text|tspan)\b([^>]*)>([^<]*)</\1>#i', function ($m) use ($business) {
                if (! $this->scan(html_entity_decode($m[3], ENT_QUOTES, 'UTF-8'))) {
                    return $m[0];
                }

                return '<' . $m[1] . $m[2] . '>' . CopySlots::svgText($business, $m[3]) . '</' . $m[1] . '>';
            }, $markup);
        }

        $cut = [];
        foreach ($this->needles as $needle) {
            if (in_array($needle['kind'], ['place', 'person', 'address', 'drawn', 'other'], true)) {
                $cut[] = $needle;
            }
        }
        if (! $cut) {
            return $markup;
        }
        usort($cut, static function ($a, $b) {
            return mb_strlen($b['text']) - mb_strlen($a['text']);
        });

        return (string) preg_replace_callback('/\b(alt|title|aria-label)="([^"]*)"/i', static function ($m) use ($cut) {
            $value = html_entity_decode($m[2], ENT_QUOTES, 'UTF-8');
            foreach ($cut as $needle) {
                $quoted = substr(self::pattern($needle['text']), 1, -3);
                // Used like an adjective ("a Rotterdam roaster"): just the name goes.
                $value = (string) preg_replace('/\s*' . $quoted . '(?=\s+\p{L})/iu', '', $value);
                $value = (string) preg_replace('/(?:\s+(?:in|near|at|from|of|for|by|around|across)(?:\s+(?:a|an|the|our|metro|downtown|central))*)?\s*' . $quoted . '(?:\x{2019}s|\'s)?/iu', '', $value);
            }
            $value = trim((string) preg_replace(['/\s{2,}/u', '/\s+([,.;:])/u', '/^[,.;:\s]+/u'], [' ', '$1', ''], $value));

            return $m[1] . '="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"';
        }, $markup);
    }

    /**
     * Replace a needle in text and readable attributes only, never in URLs,
     * class names or block JSON keys; tel:/mailto: targets get the new value
     * in link form.
     *
     * @param string $markup Markup.
     * @param array  $needle {text, kind}.
     * @param string $with   Replacement (plain text).
     *
     * @return string
     */
    private static function replaceVisible($markup, array $needle, $with)
    {
        $pattern = self::pattern($needle['text']);
        $safe    = htmlspecialchars($with, ENT_QUOTES, 'UTF-8');

        if ($needle['kind'] === 'phone') {
            $digits = '+' . ltrim(preg_replace('/[^0-9+]/', '', $with), '+');
            $old    = preg_replace('/[^0-9]/', '', $needle['text']);
            $markup = (string) preg_replace_callback('/href="tel:([^"]*)"/i', static function ($m) use ($old, $digits) {
                return substr(preg_replace('/[^0-9]/', '', $m[1]), -9) === substr($old, -9) ? 'href="tel:' . $digits . '"' : $m[0];
            }, $markup);
        }

        if ($needle['kind'] === 'email') {
            $markup = (string) preg_replace('/href="mailto:' . preg_quote($needle['text'], '/') . '"/i', 'href="mailto:' . $safe . '"', $markup);
        }

        // Text between tags, and readable attribute values.
        $parts = preg_split('/(<[^>]*>)/', $markup, -1, PREG_SPLIT_DELIM_CAPTURE);
        foreach ($parts as $i => $part) {
            if ($part === '' || $part[0] !== '<') {
                $decoded   = $part;
                $parts[$i] = (string) preg_replace($pattern, $safe, $decoded);
                // Entity-encoded needles (&amp;, &#8217;) in the original.
                $encoded = htmlspecialchars($needle['text'], ENT_QUOTES, 'UTF-8');
                if ($encoded !== $needle['text']) {
                    $parts[$i] = str_ireplace($encoded, $safe, $parts[$i]);
                }
                continue;
            }
            if (strpos($part, '<!--') === 0) {
                continue;
            }
            $parts[$i] = (string) preg_replace_callback('/\b(alt|title|aria-label|placeholder)="([^"]*)"/i', static function ($m) use ($pattern, $safe) {
                return $m[1] . '="' . preg_replace($pattern, $safe, $m[2]) . '"';
            }, $part);
        }

        return implode('', $parts);
    }

    /**
     * @param string $text Needle.
     *
     * @return string Case-insensitive pattern on word edges; whitespace and
     *                curly / straight apostrophes match loosely.
     */
    private static function pattern($text)
    {
        $quoted = preg_quote($text, '/');
        $quoted = preg_replace('/\\\\?[\x{2019}\']/u', "[\x{2019}']", $quoted);
        $quoted = preg_replace('/\s+/', '\s+', $quoted);

        return '/(?<![\p{L}\p{N}])' . $quoted . '(?![\p{L}\p{N}])/iu';
    }
}
