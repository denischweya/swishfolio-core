<?php
/**
 * The WordPress side of a visual review: what the site is, and the writes.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Review;

use SwishfolioCore\AI\Generation\BlockComposer;
use SwishfolioCore\AI\Generation\Kit;
use SwishfolioCore\AI\Onboarding\CopySlots;
use SwishfolioCore\AI\Onboarding\NeutralImage;
use SwishfolioCore\Tools\Journal\JournalEntry;
use SwishfolioCore\Tools\Journal\MutationJournal;
use SwishfolioCore\Tools\ToolContext;
use SwishfolioCore\Tools\ToolRegistry;
use SwishfolioCore\Tools\ToolRunner;
use SwishfolioCore\Tools\Write\SetDesignTokensTool;
use SwishfolioCore\Tools\Write\TrustedMarkup;

/**
 * Every write goes through the mutation journal under one run ID (the pass's
 * own), so the whole polish is one undo in the AI audit log. Page writes are
 * spliced into the stored markup (CopySlots for slots, PageSections for whole
 * sections), so nothing the review did not name changes.
 */
class ReviewSite implements ReviewSiteInterface
{
    const TOOL = 'swishfolio/visual-review';

    /** Same-role alternatives offered per section. */
    const MAX_ALTERNATIVES = 3;

    /** Text slots listed per page (the longest first). */
    const MAX_TEXTS = 40;

    /** @var Kit|null */
    private $kit;

    /** @var array[] {key, kitKey, postId, title, url, front} */
    private $pages;

    /** @var array kit page key => post ID, for links in a swapped section. */
    private $kitPages;

    /** @var MutationJournal */
    private $journal;

    /** @var ToolContext */
    private $context;

    /** @var array page key => md5 of the content the facts were read from. */
    private $hashes = [];

    /**
     * @param Kit|null        $kit      The kit the site was built from.
     * @param array[]         $pages    Reviewed pages {key, kitKey, postId, title, url, front}.
     * @param array           $kitPages Kit page key => post ID for every built page.
     * @param MutationJournal $journal  Journal.
     * @param ToolContext     $context  The pass's run (owner, confirmed).
     */
    public function __construct($kit, array $pages, array $kitPages, MutationJournal $journal, ToolContext $context)
    {
        $this->kit      = $kit;
        $this->pages    = $pages;
        $this->kitPages = $kitPages;
        $this->journal  = $journal;
        $this->context  = $context;
        TrustedMarkup::register();
    }

    public function pages()
    {
        return $this->pages;
    }

    public function kit()
    {
        return $this->kit;
    }

    public function facts()
    {
        $facts = ['pages' => [], 'kitImages' => [], 'tokens' => []];
        $used  = $this->usedUnits();

        foreach ($this->pages as $page) {
            $content = (string) get_post_field('post_content', (int) $page['postId']);
            $this->hashes[$page['key']] = md5($content);
            $facts['pages'][$page['key']] = self::pageFacts($content, $this->kit, $used) + [
                'postId' => (int) $page['postId'],
                'title'  => (string) $page['title'],
                'url'    => (string) $page['url'],
            ];
        }

        if ($this->kit !== null) {
            foreach ($this->kit->getAssets() as $name => $asset) {
                if (! empty($asset['alt']) && $this->kit->assetUrl((string) $name) !== null) {
                    $facts['kitImages'][(string) $name] = (string) $asset['alt'];
                }
            }
        }

        foreach (SetDesignTokensTool::themePalette() as $entry) {
            $hex = isset($entry['color']) ? SetDesignTokensTool::hex((string) $entry['color']) : '';
            if (isset($entry['slug']) && $hex !== '') {
                $facts['tokens'][(string) $entry['slug']] = $hex;
            }
        }

        return $facts;
    }

    /**
     * Pure apart from the kit: sections, text and image slots of one page.
     *
     * @param string   $content Page markup.
     * @param Kit|null $kit     Kit.
     * @param array    $used    Unit ids the site already shows.
     *
     * @return array {sections, texts, images}
     */
    public static function pageFacts($content, $kit, array $used = [])
    {
        $sections = [];
        $byClass  = $kit !== null ? self::unitsByClass($kit) : [];

        foreach (PageSections::split($content) as $n => $s) {
            $unit = isset($byClass[$s['className']]) ? $byClass[$s['className']] : '';
            $role = $unit !== '' ? $kit->get($unit)->getRole() : '';
            $body = substr($content, $s['start'], $s['end'] - $s['start']);

            $alternatives = [];
            if ($unit !== '') {
                foreach ($kit->units('section') as $id => $candidate) {
                    if ($id === $unit || isset($used[$id]) || $candidate->getRole() !== $role || array_intersect($candidate->getNeeds(), ['form', 'projects', 'script'])) {
                        continue;
                    }
                    $alternatives[$id] = $candidate->getSummary();
                    if (count($alternatives) >= self::MAX_ALTERNATIVES) {
                        break;
                    }
                }
            }

            $sections[$n] = [
                'unit'         => $unit,
                'role'         => $role !== '' ? $role : 'unknown',
                'form'         => (bool) preg_match('/<form\b|wp:swishfolio-core\/form|wp:swishfolio\/form/i', $body),
                'start'        => $s['start'],
                'end'          => $s['end'],
                'alternatives' => $alternatives,
            ];
        }

        $sectionAt = static function ($offset) use ($sections) {
            foreach ($sections as $n => $s) {
                if ($offset >= $s['start'] && $offset < $s['end']) {
                    return $n;
                }
            }
            return 0;
        };

        $texts  = [];
        $images = [];
        foreach ((new CopySlots($content))->slots() as $id => $slot) {
            if ($slot['type'] === 'image') {
                $images[(string) $id] = ['file' => (string) $slot['file'], 'alt' => (string) $slot['alt'], 'section' => $sectionAt($slot['start']), 'neutral' => strpos((string) $slot['file'], 'swish-placeholder') !== false];
                continue;
            }
            if (! in_array($slot['type'], ['text', 'run'], true) || ! empty($slot['svg'])) {
                continue;
            }
            $html  = (string) $slot['value'];
            $plain = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8')));
            if (mb_strlen($plain) < 20) {
                continue;
            }
            $texts[(string) $id] = [
                'text'    => $plain,
                'len'     => mb_strlen($plain),
                'html'    => (bool) preg_match('/<(a|span|img|svg)\b/i', $html),
                'label'   => (string) $slot['label'],
                'section' => $sectionAt($slot['start']),
            ];
        }

        // The longest first, page order kept within the cut.
        if (count($texts) > self::MAX_TEXTS) {
            $lens = array_map(static function ($t) {
                return $t['len'];
            }, $texts);
            arsort($lens);
            $keep  = array_slice(array_keys($lens), 0, self::MAX_TEXTS);
            $texts = array_intersect_key($texts, array_flip($keep));
        }

        return ['sections' => $sections, 'texts' => $texts, 'images' => $images];
    }

    /**
     * @param Kit $kit Kit.
     *
     * @return array className of a unit's outer block => unit id (first wins).
     */
    private static function unitsByClass(Kit $kit)
    {
        $out = [];
        foreach ($kit->units('section') as $id => $unit) {
            try {
                $first = PageSections::split($kit->markup($id));
            } catch (\RuntimeException $e) {
                continue;
            }
            if (isset($first[1]) && $first[1]['className'] !== '' && ! isset($out[$first[1]['className']])) {
                $out[$first[1]['className']] = (string) $id;
            }
        }

        return $out;
    }

    /**
     * @return array Unit ids the built pages use.
     */
    private function usedUnits()
    {
        if ($this->kit === null) {
            return [];
        }
        $byClass = self::unitsByClass($this->kit);
        $used    = [];
        foreach ($this->kitPages as $postId) {
            foreach (PageSections::split((string) get_post_field('post_content', (int) $postId)) as $s) {
                if (isset($byClass[$s['className']])) {
                    $used[$byClass[$s['className']]] = true;
                }
            }
        }

        return $used;
    }

    public function composeSection($unit, array $texts)
    {
        $links = [];
        foreach ($this->kitPages as $key => $postId) {
            $url = get_permalink((int) $postId);
            if ($url) {
                $links[(string) $key] = $url;
            }
        }

        return (new BlockComposer($this->kit))->composePattern((string) $unit, ['texts' => $texts], $links);
    }

    public function apply(array $fixes)
    {
        $applied = [];
        $byPage  = [];
        $tokens  = [];

        foreach ($fixes as $fix) {
            if ($fix['type'] === ReviewFixes::ADJUST_TOKEN) {
                $tokens[] = $fix;
            } else {
                $byPage[$fix['page']][] = $fix;
            }
        }

        foreach ($this->pages as $page) {
            if (empty($byPage[$page['key']])) {
                continue;
            }
            $applied = array_merge($applied, $this->applyPage($page, $byPage[$page['key']]));
        }

        if ($tokens) {
            $runner = new ToolRunner(new ToolRegistry([new SetDesignTokensTool($this->journal)]), $this->journal);
            $swaps  = [];
            foreach ($tokens as $fix) {
                $swaps[] = ['from' => $fix['from'], 'to' => $fix['color']];
            }
            $out = $runner->run('swishfolio/set-design-tokens', ['colorSwaps' => $swaps], $this->context);
            if (! empty($out['applied'])) {
                $applied = array_merge($applied, $tokens);
            }
        }

        return $applied;
    }

    /**
     * @param array   $page  Page.
     * @param array[] $fixes Its fixes.
     *
     * @return array[] Applied fixes.
     */
    private function applyPage(array $page, array $fixes)
    {
        $postId = (int) $page['postId'];
        $before = (string) get_post_field('post_content', $postId);

        // The owner edited the page since it was photographed: leave it alone.
        if (! isset($this->hashes[$page['key']]) || md5($before) !== $this->hashes[$page['key']]) {
            return [];
        }

        $texts   = [];
        $images  = [];
        $splices = [];
        $applied = [];
        $neutral = null;

        foreach ($fixes as $fix) {
            switch ($fix['type']) {
                case ReviewFixes::SHORTEN_COPY:
                    $texts[$fix['slot']] = esc_html($fix['text']);
                    $applied[]           = $fix;
                    break;
                case ReviewFixes::SWAP_IMAGE:
                    // The same edit KitImages::recast() makes: a kit photo, or the palette stand-in.
                    $url = $fix['image'] !== 'neutral' && $this->kit !== null ? $this->kit->assetUrl($fix['image']) : null;
                    if ($url) {
                        $assets = $this->kit->getAssets();
                        $images[$fix['slot']] = ['id' => 0, 'kitAsset' => true, 'url' => $url, 'alt' => isset($assets[$fix['image']]['alt']) ? (string) $assets[$fix['image']]['alt'] : ''];
                    } else {
                        $neutral = $neutral !== null ? $neutral : (NeutralImage::attachment() ?: false);
                        if (! $neutral) {
                            break;
                        }
                        $images[$fix['slot']] = ['id' => $neutral['id'], 'url' => $neutral['url'], 'alt' => '', 'clearAlt' => true];
                    }
                    $applied[] = $fix;
                    break;
                case ReviewFixes::REMOVE_SECTION:
                    $splices[$fix['section']] = '';
                    $applied[]                = $fix;
                    break;
                case ReviewFixes::SWAP_SECTION:
                    if (! empty($fix['markup'])) {
                        $splices[$fix['section']] = (string) $fix['markup'];
                        $applied[]                = $fix;
                    }
                    break;
            }
        }

        // 1. Slot edits on the page as photographed (sections keep their order).
        $after = ($texts || $images) ? (new CopySlots($before))->apply(['texts' => $texts, 'images' => $images]) : $before;

        // 2. Whole sections, by number.
        if ($splices) {
            $after = PageSections::splice($after, $splices);
        }

        if ($after === $before) {
            return [];
        }

        TrustedMarkup::allow($postId, $before);
        TrustedMarkup::allow($postId, $after);
        $this->journal->updatePost(
            $this->context,
            self::TOOL,
            wp_slash(['ID' => $postId, 'post_content' => $after]),
            sprintf(/* translators: %s: page title. */ __('Polished "%s" after looking at it', 'swishfolio-core'), (string) $page['title'])
        );

        return $applied;
    }

    public function note($status, $summary, array $data = [])
    {
        $this->journal->recordCall(
            $this->context,
            new VisualReviewTool(),
            ['status' => (string) $status] + $data,
            (string) $summary,
            $status === 'failed' ? JournalEntry::RESULT_ERROR : JournalEntry::RESULT_OK
        );
    }
}
