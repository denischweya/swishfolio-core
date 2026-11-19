<?php
/**
 * The copy, images and repeatable items of an existing page, addressable by id.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Onboarding;

/**
 * Template customisation rewrites a designed page without redesigning it.
 *
 * Asking a model to re-emit 50KB of hand-built block markup is slow, costly
 * and lossy: it drops classes, inline SVG icons and attributes the design
 * depends on. So the page is reduced to what an owner would change, each piece
 * with an id:
 *
 *   t*  a text slot: the inner HTML of the outermost element that holds only
 *       text and inline tags (<h2>, <p>, <li>, a <span> beside an icon), or a
 *       bare text run beside non-inline siblings;
 *   a*  an attribute an owner reads (aria-label, placeholder, title, input value) or a
 *       tel:/mailto: link target not inside a text slot;
 *   i*  an <img>;
 *   u*  a removable / duplicable unit: a top-level section, or a block or
 *       element that has a sibling of the same kind (cards, list items,
 *       columns, FAQ entries).
 *
 * The model answers with edits by id, and apply() splices them into the
 * original string at the recorded offsets. Everything not named is kept byte
 * for byte, so the design survives by construction rather than by the model's
 * care. Inside a non-core block nothing is addressable: a static custom block
 * keeps its text in the comment attributes too, and changing the HTML alone
 * would leave the editor reporting an invalid block.
 *
 * Text is sanitised through an injectable callable (wp_kses with a small
 * inline allowlist by default), attribute values are escaped, and image swaps
 * take a URL the caller resolved from an attachment ID. New sections arrive
 * already filtered by the caller.
 */
class CopySlots
{
    const MAX_SLOT_CHARS = 700;

    /** Tags that may sit inside a text slot. */
    const INLINE = [
        'a', 'abbr', 'b', 'bdi', 'bdo', 'br', 'cite', 'data', 'dfn', 'em', 'i', 'kbd', 'mark', 'q', 's',
        'samp', 'small', 'span', 'strong', 'sub', 'sup', 'time', 'u', 'var', 'wbr',
    ];

    /** Elements whose content is never copy. */
    const SKIP = ['script', 'style', 'svg', 'code', 'pre', 'textarea', 'select', 'noscript', 'template', 'iframe', 'math', 'canvas'];

    const VOID = ['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'source', 'track', 'wbr'];

    /** Attributes worth offering for a rewrite. */
    const ATTRS = ['aria-label', 'placeholder', 'title'];

    /** Blocks whose comment carries the media id / url of the image inside them. */
    const MEDIA_BLOCKS = ['core/image', 'core/cover', 'core/media-text'];

    /** Allowed inline markup in replacement text. */
    const INLINE_KSES = [
        'a'      => ['href' => true, 'class' => true, 'target' => true, 'rel' => true],
        'b'      => [],
        'strong' => [],
        'em'     => [],
        'i'      => ['class' => true],
        'span'   => ['class' => true],
        'br'     => [],
        'small'  => [],
        'mark'   => [],
        'sup'    => [],
        'sub'    => [],
        'u'      => [],
        's'      => [],
        'abbr'   => ['title' => true],
        'time'   => ['datetime' => true],
    ];

    /** @var string */
    private $content;

    /** @var array<string,array> */
    private $slots = [];

    /** @var array<string,array> */
    private $units = [];

    /** @var array[] Parsed blocks. */
    private $blocks = [];

    /** @var array[] Parsed elements. */
    private $elements = [];

    /** @var callable */
    private $sanitize;

    /**
     * @param string        $content  Serialized block markup.
     * @param callable|null $sanitize Cleans one replacement text (HTML in, HTML out).
     */
    public function __construct($content, $sanitize = null)
    {
        $this->content  = (string) $content;
        $this->sanitize = $sanitize !== null ? $sanitize : static function ($html) {
            return wp_kses((string) $html, self::INLINE_KSES);
        };

        $this->parse();
    }

    /**
     * @return array<string,array> Slots by id.
     */
    public function slots()
    {
        return $this->slots;
    }

    /**
     * @return array<string,array> Units by id.
     */
    public function units()
    {
        return $this->units;
    }

    /**
     * @return bool Whether there is anything to rewrite.
     */
    public function isEmpty()
    {
        return $this->slots === [];
    }

    /**
     * @return string md5 of the content the ids refer to.
     */
    public function hash()
    {
        return md5($this->content);
    }

    /**
     * Compact, indented outline for the prompt: units as headers, slots as lines.
     *
     * @param array $notes Slot id => a note for the writer, appended to its line.
     *
     * @return string
     */
    public function outline(array $notes = [])
    {
        $items = [];

        foreach ($this->units as $id => $unit) {
            $items[] = ['start' => $unit['start'], 'end' => $unit['end'], 'order' => 0, 'id' => $id, 'unit' => $unit];
        }

        foreach ($this->slots as $id => $slot) {
            $items[] = ['start' => $slot['start'], 'end' => $slot['end'], 'order' => 1, 'id' => $id, 'slot' => $slot];
        }

        usort($items, static function ($a, $b) {
            if ($a['start'] !== $b['start']) {
                return $a['start'] - $b['start'];
            }
            if ($a['order'] !== $b['order']) {
                return $a['order'] - $b['order'];
            }

            return $b['end'] - $a['end'];
        });

        $lines = [];
        $open  = [];

        foreach ($items as $item) {
            while ($open && $item['start'] >= end($open)) {
                array_pop($open);
            }

            $pad = str_repeat('  ', count($open));

            if (isset($item['unit'])) {
                $u       = $item['unit'];
                $lines[] = $pad . '[' . $item['id'] . '] ' . ($u['top'] ? 'section ' : '') . $u['label'];
                $open[]  = $item['end'];
                continue;
            }

            $s = $item['slot'];

            if ($s['type'] === 'image') {
                $lines[] = $pad . $item['id'] . ' <img> file=' . $s['file'] . ($s['alt'] !== '' ? ' alt="' . $s['alt'] . '"' : '');
            } elseif ($s['type'] === 'attr') {
                $lines[] = $pad . $item['id'] . ' ' . $s['attr'] . ' on <' . $s['tag'] . '>: ' . $s['value'];
            } else {
                $value   = preg_replace('/\s+/', ' ', trim($s['value']));
                $lines[] = $pad . $item['id'] . ' ' . ($s['type'] === 'run' ? 'text' : '<' . $s['label'] . '>') . ': '
                    . (mb_strlen($value) > self::MAX_SLOT_CHARS ? mb_substr($value, 0, self::MAX_SLOT_CHARS) . '…' : $value)
                    . (isset($notes[$item['id']]) ? ' (' . $notes[$item['id']] . ')' : '');
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Splice edits into the original markup.
     *
     * @param array $edits {
     *     @type array $texts  id => replacement inner HTML.
     *     @type array $attrs  id => new attribute value.
     *     @type array $images id => ['id' => attachment id, 'url' => string, 'alt' => string|null].
     *     @type array $remove Unit ids.
     *     @type array $clones [['unit' => id, 'texts' => [...], 'images' => [...], 'attrs' => [...]]].
     *     @type array $append [['after' => top-level unit id, 'markup' => already filtered block markup]].
     * }
     *
     * @return string
     */
    public function apply(array $edits)
    {
        $base = $this->overrides($edits);
        $ops  = [];

        foreach ($this->slots as $id => $slot) {
            $ops[] = ['start' => $slot['start'], 'end' => $slot['end'], 'type' => 'slot', 'slot' => $id];

            if ($slot['type'] === 'image' && $slot['comment'] !== null) {
                $ops[] = ['start' => $slot['comment']['start'], 'end' => $slot['comment']['end'], 'type' => 'comment', 'slot' => $id];
            }
        }

        $removed = [];
        foreach (isset($edits['remove']) ? (array) $edits['remove'] : [] as $unitId) {
            if (isset($this->units[$unitId])) {
                $removed[$unitId] = true;
                $ops[]            = ['start' => $this->units[$unitId]['start'], 'end' => $this->units[$unitId]['end'], 'type' => 'remove'];
            }
        }

        $n = 0;
        foreach (isset($edits['clones']) ? (array) $edits['clones'] : [] as $clone) {
            $unitId = is_array($clone) && isset($clone['unit']) ? (string) $clone['unit'] : '';

            if (! isset($this->units[$unitId]) || isset($removed[$unitId])) {
                continue;
            }

            $ops[] = [
                'start'     => $this->units[$unitId]['end'],
                'end'       => $this->units[$unitId]['end'],
                'type'      => 'clone',
                'unit'      => $unitId,
                'overrides' => $this->overrides($clone),
                'n'         => $n++,
            ];
        }

        foreach (isset($edits['append']) ? (array) $edits['append'] : [] as $section) {
            $after  = is_array($section) && isset($section['after']) ? (string) $section['after'] : '';
            $markup = is_array($section) && isset($section['markup']) ? trim((string) $section['markup']) : '';

            if ($markup === '' || ! isset($this->units[$after]) || ! $this->units[$after]['top'] || isset($removed[$after])) {
                continue;
            }

            $ops[] = ['start' => $this->units[$after]['end'], 'end' => $this->units[$after]['end'], 'type' => 'append', 'markup' => $markup, 'n' => 1000 + $n++];
        }

        usort($ops, static function ($a, $b) {
            if ($a['start'] !== $b['start']) {
                return $a['start'] - $b['start'];
            }

            $ia = in_array($a['type'], ['clone', 'append'], true);
            $ib = in_array($b['type'], ['clone', 'append'], true);

            if ($ia !== $ib) {
                return $ia ? -1 : 1;
            }

            if ($ia) {
                return $a['n'] - $b['n'];
            }

            return $b['end'] - $a['end'];
        });

        return $this->render($ops, 0, strlen($this->content), $base, true);
    }

    /**
     * @param array $ops           Sorted operations.
     * @param int   $from          Start offset.
     * @param int   $to            End offset.
     * @param array $edits         Effective edits for this render.
     * @param bool  $insertsAtEnd  Whether inserts at $to belong to this render.
     *
     * @return string
     */
    private function render(array $ops, $from, $to, array $edits, $insertsAtEnd)
    {
        $out    = '';
        $cursor = $from;

        foreach ($ops as $op) {
            if ($op['start'] < $cursor || $op['end'] > $to) {
                continue;
            }

            if ($op['start'] > $to) {
                break;
            }

            $insert = in_array($op['type'], ['clone', 'append'], true);

            if ($insert && $op['start'] === $to && ! $insertsAtEnd) {
                continue;
            }

            if (! $insert && $op['start'] === $to) {
                continue;
            }

            $out   .= substr($this->content, $cursor, $op['start'] - $cursor);
            $cursor = $op['start'];

            if ($op['type'] === 'append') {
                $out .= "\n\n" . $op['markup'];
                continue;
            }

            if ($op['type'] === 'clone') {
                $unit   = $this->units[$op['unit']];
                $merged = $this->merge($edits, $op['overrides']);
                $sep    = $unit['kind'] === 'block' ? "\n\n" : ($unit['start'] > 0 && $this->content[$unit['start'] - 1] === "\n" ? "\n" : '');
                $out   .= $sep . $this->render($ops, $unit['start'], $unit['end'], $merged, false);
                continue;
            }

            $original = substr($this->content, $op['start'], $op['end'] - $op['start']);
            $cursor   = $op['end'];

            if ($op['type'] === 'remove') {
                continue;
            }

            $out .= $this->replacement($op, $original, $edits);
        }

        return $out . substr($this->content, $cursor, $to - $cursor);
    }

    /**
     * @param array  $op       Slot or comment op.
     * @param string $original Original substring.
     * @param array  $edits    Effective edits.
     *
     * @return string
     */
    private function replacement(array $op, $original, array $edits)
    {
        $id   = $op['slot'];
        $slot = $this->slots[$id];

        if ($slot['type'] === 'image') {
            if (! isset($edits['images'][$id])) {
                return $original;
            }

            $image = $edits['images'][$id];

            return $op['type'] === 'comment'
                ? $this->imageComment($original, $slot, $image)
                : $this->imageTag($original, $slot, $image);
        }

        if ($slot['type'] === 'attr') {
            if (! isset($edits['attrs'][$id])) {
                return $original;
            }

            $value = trim((string) $edits['attrs'][$id]);

            if ($slot['attr'] === 'href') {
                if (! preg_match('#^(tel:\+?[0-9 ().-]{3,30}|mailto:[^\s"\'<>]{3,200})$#i', $value)) {
                    return $original;
                }
                $value = preg_replace('/\s+/', '', $value);
            }

            return $this->escAttr(strip_tags($value));
        }

        if (! isset($edits['texts'][$id])) {
            return $original;
        }

        if (! empty($slot['svg'])) {
            return self::svgText((string) $edits['texts'][$id], $slot['value']);
        }

        $html = (string) call_user_func($this->sanitize, str_replace(["\r", "\n"], ' ', (string) $edits['texts'][$id]));

        // A slot emptied entirely would leave a hollow element; a deliberate
        // removal goes through "remove" on its unit.
        return trim(strip_tags($html)) === '' ? $original : $html;
    }

    /**
     * Text drawn inside an inline SVG (the words round a badge ring): plain
     * text only, escaped. A ring is drawn to a fixed length (textLength), so
     * a much shorter replacement is repeated round it with the separator the
     * demo used and a much longer one is cut back at a word, keeping the
     * letter spacing close to what the design was drawn with.
     *
     * @param string $new      Replacement (HTML or text).
     * @param string $original Demo text (escaped).
     *
     * @return string Escaped text.
     */
    public static function svgText($new, $original)
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $new), ENT_QUOTES, 'UTF-8')));
        $demo = trim(html_entity_decode(strip_tags((string) $original), ENT_QUOTES, 'UTF-8'));

        if ($text === '') {
            return (string) $original;
        }

        $sep = preg_match('/\s([\x{00B7}\x{2022}\x{2726}*\/|+])\s?$/u', $demo . ' ', $m) || preg_match('/\s([\x{00B7}\x{2022}\x{2726}*\/|+])\s/u', $demo, $m) ? $m[1] : '';
        $target = mb_strlen($demo);

        if ($sep !== '' && $target > 0) {
            $unit = rtrim($text, ' ' . $sep) . ' ' . $sep . ' ';
            $out  = $unit;
            while (mb_strlen($out) + mb_strlen($unit) <= $target * 1.15) {
                $out .= $unit;
            }
            $text = rtrim($out);
            // Ends on the separator, as the demo did, when it did.
            if (! preg_match('/' . preg_quote($sep, '/') . '\s*$/u', $demo)) {
                $text = rtrim($text, ' ' . $sep);
            }
        }
        if ($target > 0 && mb_strlen($text) > $target * 1.3) {
            $cut  = mb_substr($text, 0, (int) floor($target * 1.3));
            $text = trim((string) preg_replace('/\s+\S*$/u', '', $cut));
        }

        return htmlspecialchars($text . (preg_match('/\s$/u', (string) $original) ? ' ' : ''), ENT_QUOTES, 'UTF-8');
    }

    /**
     * @param string $tag   Original <img> tag.
     * @param array  $slot  Slot.
     * @param array  $image ['id', 'url', 'alt'].
     *
     * @return string
     */
    private function imageTag($tag, array $slot, array $image)
    {
        $url = (string) $image['url'];
        $id  = (int) $image['id'];

        $tag = preg_replace('/\s(srcset|sizes)\s*=\s*("[^"]*"|\'[^\']*\')/i', '', $tag);
        $tag = $this->setAttr($tag, 'src', $url);

        if (isset($image['alt']) && $image['alt'] !== null && trim((string) $image['alt']) !== '') {
            $tag = $this->setAttr($tag, 'alt', strip_tags((string) $image['alt']));
        } elseif (isset($image['alt']) && $image['alt'] === '' && ! empty($image['clearAlt'])) {
            // A decorative stand-in: the demo photo's description no longer applies.
            $tag = $this->setAttr($tag, 'alt', '');
        }

        // A plugin image (a kit photo, flagged kitAsset by server code) has no
        // attachment: only the file changes, and the old attachment's
        // wp-image-<id> class goes, as core/image saves none without an id.
        if ($id <= 0) {
            if (preg_match('/\sclass\s*=\s*"([^"]*)"/i', $tag, $m) && preg_match('/\bwp-image-\d+\b/', $m[1])) {
                $classes = trim(preg_replace('/\s+/', ' ', preg_replace('/\bwp-image-\d+\b/', '', $m[1])));
                $tag     = $classes === '' ? preg_replace('/\sclass\s*=\s*"[^"]*"/i', '', $tag) : $this->setAttr($tag, 'class', $classes);
            }

            return $tag;
        }

        if (preg_match('/\sclass\s*=\s*"([^"]*)"/i', $tag, $m)) {
            $classes = preg_replace('/\bwp-image-\d+\b/', 'wp-image-' . $id, $m[1], -1, $count);
            if (! $count && $slot['comment'] !== null) {
                $classes = trim($classes . ' wp-image-' . $id);
            }
            $tag = $this->setAttr($tag, 'class', $classes);
        } elseif ($slot['comment'] !== null && $slot['comment']['block'] === 'core/image') {
            $tag = $this->setAttr($tag, 'class', 'wp-image-' . $id);
        }

        return $tag;
    }

    /**
     * Rewrite the media block's comment so the editor agrees with the HTML.
     *
     * @param string $comment Original opening comment.
     * @param array  $slot    Slot.
     * @param array  $image   ['id', 'url'].
     *
     * @return string
     */
    private function imageComment($comment, array $slot, array $image)
    {
        $attrs = $slot['comment']['attrs'];

        if ($slot['comment']['block'] === 'core/media-text') {
            $attrs['mediaId']  = (int) $image['id'];
            $attrs['mediaLink'] = (string) $image['url'];
            if (isset($attrs['mediaUrl'])) {
                $attrs['mediaUrl'] = (string) $image['url'];
            }
        } else {
            if ((int) $image['id'] > 0) {
                $attrs['id'] = (int) $image['id'];
            } else {
                unset($attrs['id']);
            }
            if (isset($attrs['url']) || $slot['comment']['block'] === 'core/cover') {
                $attrs['url'] = (string) $image['url'];
            }
        }

        $json = function_exists('serialize_block_attributes') ? serialize_block_attributes($attrs) : json_encode($attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $name = strpos($slot['comment']['block'], 'core/') === 0 ? substr($slot['comment']['block'], 5) : $slot['comment']['block'];

        return '<!-- wp:' . $name . ' ' . $json . ' ' . ($slot['comment']['selfClosing'] ? '/-->' : '-->');
    }

    /**
     * @param string $tag   Tag markup.
     * @param string $name  Attribute.
     * @param string $value Unescaped value.
     *
     * @return string
     */
    private function setAttr($tag, $name, $value)
    {
        $escaped = $this->escAttr($value);
        $pattern = '/(\s' . preg_quote($name, '/') . '\s*=\s*)("[^"]*"|\'[^\']*\')/i';

        if (preg_match($pattern, $tag)) {
            return preg_replace_callback($pattern, static function ($m) use ($escaped) {
                return $m[1] . '"' . $escaped . '"';
            }, $tag, 1);
        }

        return preg_replace('/^<([a-zA-Z0-9]+)/', '<$1 ' . $name . '="' . str_replace('$', '\$', $escaped) . '"', $tag, 1);
    }

    /**
     * @param string $value Value.
     *
     * @return string
     */
    private function escAttr($value)
    {
        return function_exists('esc_attr') ? esc_attr($value) : htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * @param array $edits Raw edits (texts, attrs, images keyed by slot id).
     *
     * @return array Only the entries that name a slot of the right type.
     */
    private function overrides(array $edits)
    {
        $out = ['texts' => [], 'attrs' => [], 'images' => []];

        foreach (isset($edits['texts']) ? (array) $edits['texts'] : [] as $id => $html) {
            if (isset($this->slots[$id]) && in_array($this->slots[$id]['type'], ['text', 'run'], true)) {
                $out['texts'][$id] = (string) $html;
            }
        }

        foreach (isset($edits['attrs']) ? (array) $edits['attrs'] : [] as $id => $value) {
            if (isset($this->slots[$id]) && $this->slots[$id]['type'] === 'attr') {
                $out['attrs'][$id] = (string) $value;
            }
        }

        foreach (isset($edits['images']) ? (array) $edits['images'] : [] as $id => $image) {
            if (isset($this->slots[$id]) && $this->slots[$id]['type'] === 'image' && is_array($image) && ! empty($image['url']) && (! empty($image['id']) || ! empty($image['kitAsset']))) {
                $out['images'][$id] = $image;
            }
        }

        return $out;
    }

    /**
     * @param array $base     Edits.
     * @param array $override Edits that win.
     *
     * @return array
     */
    private function merge(array $base, array $override)
    {
        foreach (['texts', 'attrs', 'images'] as $key) {
            $base[$key] = array_replace($base[$key], $override[$key]);
        }

        return $base;
    }

    /* ------------------------------------------------------------------
     * Parsing
     * --------------------------------------------------------------- */

    /**
     * @return void
     */
    private function parse()
    {
        preg_match_all('/<!--.*?-->|<\/?[a-zA-Z][^>]*>|[^<]+|</s', $this->content, $matches, PREG_OFFSET_CAPTURE);

        $blockStack = [];
        $elStack    = [];
        $texts      = [];
        $tags       = [];

        foreach ($matches[0] as $match) {
            $token = $match[0];
            $start = $match[1];
            $end   = $start + strlen($token);
            $block = $blockStack ? end($blockStack) : -1;

            if (strncmp($token, '<!--', 4) === 0) {
                foreach ($elStack as $e) {
                    $this->elements[$e]['comment'] = true;
                }

                if (! preg_match('#^<!--\s+(/)?wp:([a-z][a-z0-9_-]*(?:/[a-z][a-z0-9_-]*)?)(?:\s+(\{.*\}))?\s+(/)?-->$#s', $token, $m)) {
                    continue;
                }

                $name = strpos($m[2], '/') === false ? 'core/' . $m[2] : $m[2];

                if ($m[1] === '/') {
                    // Close the innermost open block of this name.
                    for ($i = count($blockStack) - 1; $i >= 0; $i--) {
                        if ($this->blocks[$blockStack[$i]]['name'] === $name) {
                            $this->blocks[$blockStack[$i]]['end'] = $end;
                            array_splice($blockStack, $i);
                            break;
                        }
                    }
                    continue;
                }

                $attrs = isset($m[3]) && $m[3] !== '' ? json_decode($m[3], true) : [];
                $index = count($this->blocks);

                $this->blocks[] = [
                    'name'        => $name,
                    'attrs'       => is_array($attrs) ? $attrs : [],
                    'start'       => $start,
                    'openEnd'     => $end,
                    'end'         => $end,
                    'parent'      => $block,
                    'foreign'     => strpos($name, 'core/') !== 0 || ($block >= 0 && $this->blocks[$block]['foreign']),
                    'selfClosing' => isset($m[4]) && $m[4] === '/',
                ];

                if (! $this->blocks[$index]['selfClosing']) {
                    $blockStack[] = $index;
                }
                continue;
            }

            if ($token[0] === '<' && strlen($token) > 1) {
                $closing = $token[1] === '/';
                preg_match('#^</?([a-zA-Z][a-zA-Z0-9-]*)#', $token, $tm);
                $tag = strtolower($tm[1]);

                if ($closing) {
                    for ($i = count($elStack) - 1; $i >= 0; $i--) {
                        if ($this->elements[$elStack[$i]]['tag'] === $tag) {
                            for ($j = count($elStack) - 1; $j >= $i; $j--) {
                                $this->closeElement($elStack[$j], $start, $end);
                                array_pop($elStack);
                            }
                            break;
                        }
                    }
                    continue;
                }

                $parent = $elStack ? end($elStack) : -1;
                $skip   = in_array($tag, self::SKIP, true) || ($parent >= 0 && $this->elements[$parent]['skip']);

                if ($parent >= 0 && ! in_array($tag, self::INLINE, true)) {
                    $this->elements[$parent]['inline'] = false;
                }

                $tags[] = ['tag' => $tag, 'start' => $start, 'end' => $end, 'token' => $token, 'block' => $block, 'skip' => $skip];

                if (in_array($tag, self::VOID, true) || substr($token, -2) === '/>') {
                    continue;
                }

                $index            = count($this->elements);
                $this->elements[] = [
                    'tag'        => $tag,
                    'class'      => $this->attr($token, 'class'),
                    'start'      => $start,
                    'innerStart' => $end,
                    'innerEnd'   => $end,
                    'end'        => $end,
                    'parent'     => $parent,
                    'block'      => $block,
                    'skip'       => $skip,
                    'inline'     => true,
                    'text'       => false,
                    'comment'    => false,
                    'closed'     => false,
                ];
                $elStack[] = $index;
                continue;
            }

            if (trim(str_replace('&nbsp;', ' ', $token)) === '') {
                continue;
            }

            $el = $elStack ? end($elStack) : -1;

            if ($el >= 0) {
                $this->elements[$el]['text'] = true;
            }

            $texts[] = ['start' => $start, 'end' => $end, 'el' => $el, 'block' => $block];
        }

        $this->collect($texts, $tags);
    }

    /**
     * @param int $index      Element.
     * @param int $closeStart Offset of its closing tag.
     * @param int $closeEnd   Offset after it.
     *
     * @return void
     */
    private function closeElement($index, $closeStart, $closeEnd)
    {
        $el = &$this->elements[$index];

        $el['innerEnd'] = $closeStart;
        $el['end']      = $closeEnd;
        $el['closed']   = true;

        $parent = $el['parent'];

        if ($parent >= 0) {
            if (! $el['inline']) {
                $this->elements[$parent]['inline'] = false;
            }
            if ($el['text']) {
                $this->elements[$parent]['text'] = true;
            }
            if ($el['comment']) {
                $this->elements[$parent]['comment'] = true;
            }
        }
    }

    /**
     * Turn the parsed tree into slots and units.
     *
     * @param array $texts Text tokens.
     * @param array $tags  Opening tags.
     *
     * @return void
     */
    private function collect(array $texts, array $tags)
    {
        // Text slots: outermost elements holding only text and inline tags.
        $candidates = [];
        foreach ($this->elements as $i => $el) {
            if ($el['closed'] && ! $el['skip'] && $el['inline'] && $el['text'] && ! $el['comment'] && ! $this->foreign($el['block'])) {
                $candidates[] = $i;
            }
        }
        usort($candidates, function ($a, $b) {
            return $this->elements[$a]['start'] - $this->elements[$b]['start'] ?: $this->elements[$b]['end'] - $this->elements[$a]['end'];
        });

        $taken   = [];
        $lastEnd = -1;
        foreach ($candidates as $i) {
            if ($this->elements[$i]['start'] < $lastEnd) {
                continue;
            }
            $taken[] = $i;
            $lastEnd = $this->elements[$i]['end'];
        }

        $ranges = [];
        $found  = [];

        foreach ($taken as $i) {
            $el       = $this->elements[$i];
            $ranges[] = [$el['start'], $el['end']];
            $found[]  = [
                'type'  => 'text',
                'start' => $el['innerStart'],
                'end'   => $el['innerEnd'],
                'value' => substr($this->content, $el['innerStart'], $el['innerEnd'] - $el['innerStart']),
                'label' => $el['tag'] . $this->classLabel($el['class']),
                'block' => $el['block'],
            ];
        }

        $inside = static function ($start, $end) use ($ranges) {
            foreach ($ranges as $r) {
                if ($start >= $r[0] && $end <= $r[1]) {
                    return true;
                }
            }

            return false;
        };

        // Bare text runs beside icons and other non-inline siblings.
        foreach ($texts as $t) {
            if ($inside($t['start'], $t['end']) || $this->foreign($t['block']) || ($t['el'] >= 0 && $this->elements[$t['el']]['skip'])) {
                continue;
            }
            $found[] = [
                'type'  => 'run',
                'start' => $t['start'],
                'end'   => $t['end'],
                'value' => substr($this->content, $t['start'], $t['end'] - $t['start']),
                'label' => '',
                'block' => $t['block'],
            ];
        }

        // Images and attributes outside text slots. A text slot is its element's
        // inner HTML, so that element's own opening tag (the href of a phone
        // link whose label is a text slot) is still free to address.
        $ownTag = static function ($start) use ($ranges) {
            foreach ($ranges as $r) {
                if ($start === $r[0]) {
                    return true;
                }
            }

            return false;
        };

        foreach ($tags as $tag) {
            // An inline SVG's own label (a map's aria-label) is read out like copy.
            $svgRoot = $tag['tag'] === 'svg' && ! $this->foreign($tag['block']) && ! $inside($tag['start'], $tag['end']);
            if ($svgRoot) {
                foreach (['aria-label', 'title'] as $name) {
                    $range = $this->attrRange($tag['token'], $name);
                    $value = $range === null ? '' : substr($tag['token'], $range[0], $range[1]);
                    if ($range !== null && preg_match('/\p{L}/u', $value)) {
                        $found[] = [
                            'type'  => 'attr',
                            'svg'   => true,
                            'start' => $tag['start'] + $range[0],
                            'end'   => $tag['start'] + $range[0] + $range[1],
                            'value' => $value,
                            'attr'  => $name,
                            'tag'   => 'svg' . $this->classLabel($this->attr($tag['token'], 'class')),
                            'block' => $tag['block'],
                        ];
                    }
                }
            }
            if ($tag['skip'] || $this->foreign($tag['block']) || ($inside($tag['start'], $tag['end']) && ! $ownTag($tag['start']))) {
                continue;
            }

            if ($tag['tag'] === 'img') {
                $src     = $this->attr($tag['token'], 'src');
                $found[] = [
                    'type'    => 'image',
                    'start'   => $tag['start'],
                    'end'     => $tag['end'],
                    'value'   => $src,
                    'file'    => basename((string) parse_url($src, PHP_URL_PATH)),
                    'alt'     => $this->attr($tag['token'], 'alt'),
                    'comment' => $this->mediaComment($tag['block']),
                    'block'   => $tag['block'],
                ];
                continue;
            }

            $names = self::ATTRS;
            if ($tag['tag'] === 'a') {
                $names[] = 'href';
            }
            // Prefilled demo form fields (a sample postcode in a booking widget).
            if ($tag['tag'] === 'input') {
                $names[] = 'value';
            }

            foreach ($names as $name) {
                $range = $this->attrRange($tag['token'], $name);
                if ($range === null) {
                    continue;
                }
                $value = substr($tag['token'], $range[0], $range[1]);
                if ($name === 'href' && ! preg_match('#^(tel|mailto):#i', $value)) {
                    continue;
                }
                if (trim($value) === '') {
                    continue;
                }
                $found[] = [
                    'type'  => 'attr',
                    'start' => $tag['start'] + $range[0],
                    'end'   => $tag['start'] + $range[0] + $range[1],
                    'value' => $value,
                    'attr'  => $name,
                    'tag'   => $tag['tag'] . $this->classLabel($this->attr($tag['token'], 'class')),
                    'block' => $tag['block'],
                ];
            }
        }

        usort($found, static function ($a, $b) {
            return $a['start'] - $b['start'];
        });

        // Words drawn inside an inline SVG (<text>, <textPath>, <title>) are
        // copy a visitor reads too. They are numbered after every other text
        // slot, so the ids of the rest stay what they always were.
        foreach ($this->svgTexts() as $svg) {
            $found[] = $svg;
        }

        $counters = ['text' => 0, 'attr' => 0, 'image' => 0];
        $svgLast  = array_merge(array_filter($found, static function ($slot) {
            return empty($slot['svg']);
        }), array_filter($found, static function ($slot) {
            return ! empty($slot['svg']);
        }));
        foreach ($svgLast as $slot) {
            $prefix = $slot['type'] === 'image' ? 'i' : ($slot['type'] === 'attr' ? 'a' : 't');
            $key    = $slot['type'] === 'run' ? 'text' : $slot['type'];
            $id     = $prefix . $counters[$key]++;

            if (! isset($slot['comment'])) {
                $slot['comment'] = null;
            }

            $this->slots[$id] = $slot;
        }

        $this->collectUnits($ranges);
    }

    /**
     * Text-only <text>, <textPath>, <tspan> and <title> elements inside an
     * inline SVG, holding at least one letter, outside foreign blocks.
     *
     * @return array[] Slots (type text, svg true).
     */
    private function svgTexts()
    {
        $out = [];
        foreach ($this->elements as $el) {
            if (! $el['closed'] || ! in_array($el['tag'], ['text', 'textpath', 'tspan', 'title'], true) || $this->foreign($el['block'])) {
                continue;
            }
            $inner = substr($this->content, $el['innerStart'], $el['innerEnd'] - $el['innerStart']);
            if (strpos($inner, '<') !== false || ! preg_match('/\p{L}/u', html_entity_decode($inner, ENT_QUOTES, 'UTF-8')) || ! $this->insideSvg($el)) {
                continue;
            }
            $out[] = [
                'type'  => 'text',
                'svg'   => true,
                'start' => $el['innerStart'],
                'end'   => $el['innerEnd'],
                'value' => $inner,
                'label' => ($el['tag'] === 'textpath' ? 'textPath' : $el['tag']) . ' in a drawing, plain text, about ' . mb_strlen(trim(html_entity_decode($inner, ENT_QUOTES, 'UTF-8'))) . ' characters',
                'block' => $el['block'],
            ];
        }

        return $out;
    }

    /**
     * @param array $el Element.
     *
     * @return bool
     */
    private function insideSvg(array $el)
    {
        for ($p = $el['parent']; $p >= 0; $p = $this->elements[$p]['parent']) {
            if ($this->elements[$p]['tag'] === 'svg') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array $textRanges Ranges of taken text-slot elements.
     *
     * @return void
     */
    private function collectUnits(array $textRanges)
    {
        $units = [];

        // Blocks: top-level sections, and blocks with a same-kind sibling.
        $groups = [];
        foreach ($this->blocks as $i => $b) {
            $groups[$this->blockKind($b)][] = $i;
        }

        foreach ($this->blocks as $i => $b) {
            $key  = $this->blockKind($b);
            $top  = $b['parent'] < 0;
            if (! $top && count($groups[$key]) < 2) {
                continue;
            }
            if (! $this->hasSlot($b['start'], $b['end'])) {
                continue;
            }
            $units[] = [
                'kind'  => 'block',
                'start' => $b['start'],
                'end'   => $b['end'],
                'top'   => $top,
                'label' => substr($b['name'], 0, 5) === 'core/' ? substr($b['name'], 5) . $this->classLabel(isset($b['attrs']['className']) ? (string) $b['attrs']['className'] : '') : $b['name'],
            ];
        }

        // Elements inside a block's HTML with a same-kind sibling (cards in a wp:html block).
        $groups = [];
        foreach ($this->elements as $i => $el) {
            if ($el['closed'] && ! $el['skip']) {
                $groups[$el['parent'] . '|' . $el['block'] . '|' . $el['tag'] . '|' . $el['class']][] = $i;
            }
        }

        foreach ($groups as $members) {
            if (count($members) < 2) {
                continue;
            }

            foreach ($members as $i) {
                $el = $this->elements[$i];

                if ($el['parent'] < 0 || $el['comment'] || $this->foreign($el['block'])) {
                    continue;
                }

                $insideText = false;
                foreach ($textRanges as $r) {
                    // The slot's own element may be a unit (a list item); anything inside it may not.
                    if ($el['start'] >= $r[0] && $el['end'] <= $r[1] && ! ($el['start'] === $r[0] && $el['end'] === $r[1])) {
                        $insideText = true;
                        break;
                    }
                }

                if ($insideText || ! $this->hasSlot($el['start'], $el['end'])) {
                    continue;
                }

                $units[] = [
                    'kind'  => 'element',
                    'start' => $el['start'],
                    'end'   => $el['end'],
                    'top'   => false,
                    'label' => '<' . $el['tag'] . $this->classLabel($el['class']) . '>',
                ];
            }
        }

        usort($units, static function ($a, $b) {
            return $a['start'] - $b['start'] ?: $b['end'] - $a['end'];
        });

        foreach ($units as $n => $unit) {
            $this->units['u' . $n] = $unit;
        }
    }

    /**
     * Sibling key for a block. Class-less blocks (a row of core/html blocks)
     * also compare their root element, so a stats panel and the quotes beside
     * it in a two-column grid are not "the same kind" and neither can be
     * removed on its own, which would break the grid.
     *
     * @param array $b Block record.
     *
     * @return string
     */
    private function blockKind(array $b)
    {
        $key = $b['parent'] . '|' . $b['name'] . '|' . (isset($b['attrs']['className']) ? $b['attrs']['className'] : '');

        if (! isset($b['attrs']['className'])) {
            $inner = substr($this->content, $b['start'], min(600, $b['end'] - $b['start']));
            $inner = preg_replace('/^\s*<!--.*?-->/s', '', $inner);
            if (preg_match('/^\s*<([a-z0-9]+)([^>]*)>/i', (string) $inner, $m)) {
                $key .= '|' . strtolower($m[1]) . (preg_match('/\bclass="([^"]*)"/', $m[2], $c) ? '.' . $c[1] : '');
            }
        }

        return $key;
    }

    /**
     * @param int $start Range start.
     * @param int $end   Range end.
     *
     * @return bool
     */
    private function hasSlot($start, $end)
    {
        foreach ($this->slots as $slot) {
            if ($slot['start'] >= $start && $slot['end'] <= $end) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param int $block Block index or -1.
     *
     * @return bool
     */
    private function foreign($block)
    {
        return $block >= 0 && $this->blocks[$block]['foreign'];
    }

    /**
     * @param int $block Innermost block of an <img>.
     *
     * @return array|null The media block's opening comment, when the image is its own.
     */
    private function mediaComment($block)
    {
        if ($block < 0 || ! in_array($this->blocks[$block]['name'], self::MEDIA_BLOCKS, true)) {
            return null;
        }

        $b = $this->blocks[$block];

        return [
            'start'       => $b['start'],
            'end'         => $b['openEnd'],
            'attrs'       => $b['attrs'],
            'block'       => $b['name'],
            'selfClosing' => $b['selfClosing'],
        ];
    }

    /**
     * @param string $class Class attribute.
     *
     * @return string ".a.b" for the first two meaningful classes.
     */
    private function classLabel($class)
    {
        $parts = array_filter(preg_split('/\s+/', trim((string) $class)), static function ($c) {
            return $c !== '' && strpos($c, 'wp-block-') !== 0 && strpos($c, 'wp-element-') !== 0 && strpos($c, 'has-') !== 0 && strpos($c, 'is-layout-') !== 0;
        });

        return $parts ? '.' . implode('.', array_slice(array_values($parts), 0, 2)) : '';
    }

    /**
     * @param string $token Tag markup.
     * @param string $name  Attribute.
     *
     * @return string Decoded value, or ''.
     */
    private function attr($token, $name)
    {
        $range = $this->attrRange($token, $name);

        return $range === null ? '' : html_entity_decode(substr($token, $range[0], $range[1]), ENT_QUOTES, 'UTF-8');
    }

    /**
     * @param string $token Tag markup.
     * @param string $name  Attribute.
     *
     * @return array|null [offset, length] of the raw value inside the token.
     */
    private function attrRange($token, $name)
    {
        if (! preg_match('/\s' . preg_quote($name, '/') . '\s*=\s*("([^"]*)"|\'([^\']*)\')/i', $token, $m, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $group = isset($m[3]) && $m[3][1] >= 0 ? $m[3] : $m[2];

        return [$group[1], strlen($group[0])];
    }
}
