<?php
/**
 * Puts the owner's uploaded photos in place of a kit's placeholder photos.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Onboarding;

use SwishfolioCore\AI\AIRequest;
use SwishfolioCore\AI\AIResponse;
use SwishfolioCore\AI\Contracts\AIProviderInterface;
use SwishfolioCore\AI\Prompt\PromptManager;
use SwishfolioCore\Tools\ToolContext;
use SwishfolioCore\Tools\ToolRunner;

/**
 * Generated pages keep the kit's photos (i* slots are fixed for the
 * planner). After the build, one short provider call sees every image slot
 * on the new pages (page, position, file, alt) and the owner's photos (role,
 * caption, size) and answers with placements; each page is then rewritten
 * once through rewrite-copy with image edits only, so nothing but the <img>
 * (and its block comment's id) changes. When the call fails, photos go into
 * the home page's slots in order, which is never worse than no photos.
 */
class KitImages
{
    /** Not design-heavy: runs on the follow-up model and never marks the site. */
    const KIND      = 'onboarding-images';
    const MAX_SLOTS = 60;
    const MAX_USES  = 2;
    /** Most slots that get a stock photo search in one build (time and cost bound). */
    const MAX_STOCK = 8;

    /** @var AIProviderInterface|null */
    private $provider;

    /** @var PromptManager|null */
    private $prompts;

    /** @var ToolRunner */
    private $runner;

    /**
     * @param AIProviderInterface|null $provider Provider (null: fallback placement only).
     * @param PromptManager|null       $prompts  Prompts.
     * @param ToolRunner               $runner   Runner holding rewrite-copy.
     */
    public function __construct($provider, $prompts, ToolRunner $runner)
    {
        $this->provider = $provider;
        $this->prompts  = $prompts;
        $this->runner   = $runner;
    }

    /**
     * Image slots of the given pages, front page first.
     *
     * @param array[] $pages {postId, title, front}
     *
     * @return array[] {ref, postId, slot, page, position, file, alt}
     */
    public static function slots(array $pages)
    {
        usort($pages, static function ($a, $b) {
            return ((int) ! empty($b['front'])) - ((int) ! empty($a['front']));
        });

        $out = [];

        foreach ($pages as $page) {
            $content = (string) get_post_field('post_content', (int) $page['postId']);
            $n       = 0;

            foreach ((new CopySlots($content))->slots() as $id => $slot) {
                if ($slot['type'] !== 'image') {
                    continue;
                }
                $n++;
                $out[] = [
                    'ref'      => (int) $page['postId'] . ':' . $id,
                    'postId'   => (int) $page['postId'],
                    'slot'     => (string) $id,
                    'page'     => (string) $page['title'] . (! empty($page['front']) ? ' (home page)' : ''),
                    'position' => $n === 1 ? 'first photo on the page' : 'photo ' . $n . ' on the page',
                    'file'     => (string) $slot['file'],
                    'alt'      => (string) $slot['alt'],
                ];
                if (count($out) >= self::MAX_SLOTS) {
                    return $out;
                }
            }
        }

        return $out;
    }

    /**
     * Ask for placements.
     *
     * @param array[] $slots    slots().
     * @param array[] $photos   {id, role, note, size}.
     * @param string  $business Business name and brief.
     * @param string  $model    Model.
     *
     * @return array {placements, response}
     */
    public function plan(array $slots, array $photos, $business, $model, array $catalogue = [], $stock = false)
    {
        $photoLines = [];
        foreach ($photos as $photo) {
            $photoLines[] = sprintf('- %d: %s%s%s', $photo['id'], $photo['role'], $photo['note'] !== '' ? ', "' . $photo['note'] . '"' : '', $photo['size'] !== '' ? ', ' . $photo['size'] : '');
        }
        $kitLines = [];
        foreach ($catalogue as $name => $alt) {
            $kitLines[] = '- ' . $name . ': ' . $alt;
        }

        $slotLines = [];
        foreach ($slots as $slot) {
            $slotLines[] = sprintf('%s | %s | %s | %s%s', $slot['ref'], $slot['page'], $slot['position'], $slot['file'], $slot['alt'] !== '' ? ' "' . $slot['alt'] . '"' : '');
        }

        $user = $this->prompts->task('place-images', [
            'business' => (string) $business,
            'photos'   => $photoLines ? implode("\n", $photoLines) : '(none: the owner has not uploaded any photos)',
            'slots'    => implode("\n", $slotLines),
            'kit'      => $kitLines ? implode("\n", $kitLines) : '(none)',
        ]);

        // Photo sourcing on (a stock library key is configured): the same call
        // also proposes a search query per spot. Off: the request is unchanged.
        if ($stock) {
            $user = preg_replace('/\n*Return the placements and the unfit photos\.\s*$/', '', $user) . "\n\n" . $this->prompts->task('place-images-stock', []) . "\n\nReturn the placements, the unfit photos and the stock queries.";
        }

        $response   = $this->provider->generate(new AIRequest('You place a site owner\'s photos on their new website and check that every other photo suits their business. You answer only with the structured result.', $user, self::schema($stock), $model, $stock ? 4000 : 3000));
        $decoded    = (array) $response->getDecoded();
        $placements = self::placements($decoded, $slots, $photos);

        return [
            'placements' => $placements,
            'recasts'    => self::recasts($decoded, $slots, $catalogue, $placements),
            'stock'      => $stock ? self::stockQueries($decoded, $slots, $placements) : [],
            'response'   => $response,
        ];
    }

    /**
     * Pure: the template photos the model called wrong for this business,
     * each to become another of the kit's photos that fits (swapTo, a name
     * from the catalogue, at most once per page) or a neutral stand-in
     * (swapTo ''). Slots that got an owner's photo are left to that.
     *
     * @param array   $decoded    Model answer.
     * @param array[] $slots      slots().
     * @param array   $catalogue  Kit image name => alt.
     * @param array[] $placements placements().
     *
     * @return array[] {postId, slot, image} (image '' for the stand-in)
     */
    public static function recasts(array $decoded, array $slots, array $catalogue, array $placements)
    {
        $refs = [];
        foreach ($slots as $slot) {
            $refs[$slot['ref']] = $slot;
        }
        $placed = [];
        foreach ($placements as $p) {
            $placed[$p['postId'] . ':' . $p['slot']] = true;
        }

        $out     = [];
        $seen    = [];
        $perPage = [];
        foreach ((array) (isset($decoded['unfit']) ? $decoded['unfit'] : []) as $u) {
            $ref = is_array($u) && isset($u['slot']) ? trim((string) $u['slot']) : '';
            if (! isset($refs[$ref]) || isset($placed[$ref]) || isset($seen[$ref])) {
                continue;
            }
            $seen[$ref] = true;
            $postId     = $refs[$ref]['postId'];
            $to         = is_array($u) && isset($u['swapTo']) ? trim((string) $u['swapTo']) : '';
            // The same photo again, or one already on this page, is no better.
            if ($to !== '' && (! isset($catalogue[$to]) || isset($perPage[$postId][$to]) || strpos($refs[$ref]['file'], $to . '.') === 0)) {
                $to = '';
            }
            if ($to !== '') {
                $perPage[$postId][$to] = true;
            }
            $out[] = ['postId' => $postId, 'slot' => $refs[$ref]['slot'], 'image' => $to];
        }

        return $out;
    }

    /**
     * Write recasts: each page once, image edits only.
     *
     * @param array[]  $recasts recasts().
     * @param callable $url     Kit image name => URL (null when unknown).
     * @param array    $alts    Kit image name => alt.
     * @param callable $write   (postId, before, after): bool, the journalled write.
     *
     * @return int Pages changed.
     */
    public static function recast(array $recasts, $url, array $alts, $write)
    {
        $byPage = [];
        $neutral = null;
        foreach ($recasts as $r) {
            if ($r['image'] !== '' && ($u = call_user_func($url, $r['image']))) {
                $byPage[$r['postId']][$r['slot']] = ['id' => 0, 'kitAsset' => true, 'url' => $u, 'alt' => isset($alts[$r['image']]) ? (string) $alts[$r['image']] : ''];
                continue;
            }
            if ($neutral === null) {
                $neutral = NeutralImage::attachment() ?: false;
            }
            if ($neutral) {
                $byPage[$r['postId']][$r['slot']] = ['id' => $neutral['id'], 'url' => $neutral['url'], 'alt' => '', 'clearAlt' => true];
            }
        }

        $changed = 0;
        foreach ($byPage as $postId => $images) {
            $before = (string) get_post_field('post_content', (int) $postId);
            $after  = (new CopySlots($before))->apply(['images' => $images]);
            $changed += call_user_func($write, (int) $postId, $before, $after) ? 1 : 0;
        }

        return $changed;
    }

    /**
     * Pure: keep only placements into listed slots with listed photos, each
     * slot once, each photo at most MAX_USES times and once per page.
     *
     * @param array   $decoded Model answer.
     * @param array[] $slots   slots().
     * @param array[] $photos  Photos.
     *
     * @return array[] {postId, slot, attachmentId, alt}
     */
    public static function placements(array $decoded, array $slots, array $photos)
    {
        $refs = [];
        foreach ($slots as $slot) {
            $refs[$slot['ref']] = $slot;
        }
        $ids = [];
        foreach ($photos as $photo) {
            $ids[(int) $photo['id']] = true;
        }

        $out     = [];
        $taken   = [];
        $uses    = [];
        $perPage = [];

        foreach ((array) (isset($decoded['placements']) ? $decoded['placements'] : []) as $p) {
            $ref = is_array($p) && isset($p['slot']) ? trim((string) $p['slot']) : '';
            $id  = is_array($p) && isset($p['attachmentId']) ? (int) $p['attachmentId'] : 0;

            if (! isset($refs[$ref], $ids[$id]) || isset($taken[$ref])) {
                continue;
            }

            $postId = $refs[$ref]['postId'];
            if ((isset($uses[$id]) ? $uses[$id] : 0) >= self::MAX_USES || isset($perPage[$postId][$id])) {
                continue;
            }

            $taken[$ref]            = true;
            $uses[$id]              = (isset($uses[$id]) ? $uses[$id] : 0) + 1;
            $perPage[$postId][$id]  = true;
            $out[] = [
                'postId'       => $postId,
                'slot'         => $refs[$ref]['slot'],
                'attachmentId' => $id,
                'alt'          => \SwishfolioCore\AI\Copy\Dashes::text(mb_substr(trim(isset($p['alt']) ? (string) $p['alt'] : ''), 0, 160)),
            ];
        }

        return $out;
    }

    /**
     * Pure: without a model, the owner's photos fill the home page's slots in order.
     *
     * @param array[] $slots  slots(), front page first.
     * @param array[] $photos Photos.
     *
     * @return array[]
     */
    public static function fallback(array $slots, array $photos)
    {
        $out = [];
        if (! $slots || ! $photos) {
            return $out;
        }

        $first = $slots[0]['postId'];
        $i     = 0;

        foreach ($slots as $slot) {
            if ($slot['postId'] !== $first || $i >= count($photos)) {
                break;
            }
            $photo = $photos[$i++];
            $out[] = ['postId' => $first, 'slot' => $slot['slot'], 'attachmentId' => (int) $photo['id'], 'alt' => $photo['note'] !== '' ? $photo['note'] : ''];
        }

        return $out;
    }

    /**
     * Write placements, one rewrite-copy per page (image edits only).
     *
     * @param array[]     $placements placements().
     * @param ToolContext $context    Run.
     *
     * @return int Pages changed.
     */
    public function apply(array $placements, ToolContext $context)
    {
        $byPage = [];
        foreach ($placements as $p) {
            $byPage[$p['postId']][$p['slot']] = ['attachmentId' => $p['attachmentId'], 'alt' => $p['alt']];
        }

        $changed = 0;
        foreach ($byPage as $postId => $images) {
            $content = (string) get_post_field('post_content', (int) $postId);
            $result  = $this->runner->run('swishfolio/rewrite-copy', [
                'postId'      => (int) $postId,
                'contentHash' => md5($content),
                'edits'       => wp_json_encode(['texts' => [], 'attrs' => [], 'images' => $images, 'remove' => [], 'clones' => [], 'append' => []]),
            ], $context);
            $changed += empty($result['updated']) ? 0 : 1;
        }

        return $changed;
    }

    /**
     * Pure: the stock photo queries for slots no owner's photo went into,
     * each slot once, at most MAX_STOCK, in the model's order.
     *
     * @param array   $decoded    Model answer.
     * @param array[] $slots      slots().
     * @param array[] $placements placements().
     *
     * @return array[] {ref, postId, slot, page, position, alt, query, orientation}
     */
    public static function stockQueries(array $decoded, array $slots, array $placements)
    {
        $refs = [];
        foreach ($slots as $slot) {
            $refs[$slot['ref']] = $slot;
        }
        $taken = [];
        foreach ($placements as $p) {
            $taken[$p['postId'] . ':' . $p['slot']] = true;
        }

        $out = [];
        foreach ((array) (isset($decoded['stock']) ? $decoded['stock'] : []) as $q) {
            $ref   = is_array($q) && isset($q['slot']) ? trim((string) $q['slot']) : '';
            $query = is_array($q) && isset($q['query']) ? trim(preg_replace('/\s+/', ' ', sanitize_text_field((string) $q['query']))) : '';
            if (! isset($refs[$ref]) || isset($taken[$ref]) || $query === '' || count($out) >= self::MAX_STOCK) {
                continue;
            }
            $taken[$ref] = true;
            $orientation = isset($q['orientation']) && in_array($q['orientation'], ['landscape', 'portrait', 'square'], true) ? $q['orientation'] : 'landscape';
            $out[] = array_merge($refs[$ref], ['query' => mb_substr($query, 0, 60), 'orientation' => $orientation]);
        }

        return $out;
    }

    /**
     * Pure: slots showing the kit's demo persona (a "portrait" photo, or one
     * whose description names a person from the demo business). A stranger's
     * face must not stand in for the owner.
     *
     * @param array[] $slots  slots().
     * @param array   $assets Kit image name => {file, alt}.
     * @param array   $people Demo people's names.
     *
     * @return array[] The matching slots.
     */
    public static function portraitSlots(array $slots, array $assets, array $people)
    {
        $names = [];
        foreach ($assets as $name => $asset) {
            $alt      = isset($asset['alt']) ? (string) $asset['alt'] : '';
            $isPerson = (bool) preg_match('/(^|-)portrait(-|$)/', (string) $name);
            foreach ($people as $person) {
                $person = trim((string) $person);
                if ($person !== '' && stripos($alt . ' ' . (isset($asset['title']) ? $asset['title'] : ''), $person) !== false) {
                    $isPerson = true;
                }
            }
            if ($isPerson) {
                $names[] = (string) $name;
            }
        }

        $out = [];
        foreach ($slots as $slot) {
            foreach ($names as $name) {
                // "portrait-river.jpg", or a media library copy "portrait-river-1024x1536.jpg".
                if (preg_match('/^' . preg_quote($name, '/') . '[.-]/', $slot['file'])) {
                    $out[] = $slot;
                    break;
                }
            }
        }

        return $out;
    }

    /**
     * @param bool $stock Also ask for stock photo queries.
     *
     * @return array
     */
    public static function schema($stock = false)
    {
        $schema = [
            'type'       => 'object',
            'required'   => ['placements', 'unfit'],
            'properties' => [
                'placements' => ['type' => 'array', 'items' => [
                    'type'       => 'object',
                    'required'   => ['slot', 'attachmentId', 'alt'],
                    'properties' => [
                        'slot'         => ['type' => 'string'],
                        'attachmentId' => ['type' => 'integer'],
                        'alt'          => ['type' => 'string'],
                    ],
                ]],
                'unfit' => ['type' => 'array', 'items' => [
                    'type'       => 'object',
                    'required'   => ['slot', 'swapTo'],
                    'properties' => [
                        'slot'   => ['type' => 'string'],
                        'swapTo' => ['type' => 'string'],
                    ],
                ]],
            ],
        ];

        if ($stock) {
            $schema['required'][]          = 'stock';
            $schema['properties']['stock'] = ['type' => 'array', 'items' => [
                'type'       => 'object',
                'required'   => ['slot', 'query', 'orientation'],
                'properties' => [
                    'slot'        => ['type' => 'string'],
                    'query'       => ['type' => 'string'],
                    'orientation' => ['type' => 'string', 'enum' => ['landscape', 'portrait', 'square']],
                ],
            ]];
        }

        return $schema;
    }

    /**
     * @param AIResponse|null $response Response.
     *
     * @return array [in, out]
     */
    public static function usage($response)
    {
        return $response instanceof AIResponse ? [(int) $response->getInputTokens(), (int) $response->getOutputTokens()] : [0, 0];
    }
}
