<?php
/**
 * The visual review (polish) pass of a newly built site.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Review;

use SwishfolioCore\AI\AIRequest;
use SwishfolioCore\AI\AIResponse;
use SwishfolioCore\AI\Copy\Dashes;
use SwishfolioCore\AI\UsageLogger;

/**
 * Nothing in the build ever looks at the rendered site, so a clash (art that
 * fights the palette, an empty block, a section that reads oddly, text with
 * too little contrast) goes unnoticed. This pass, run after the site is live:
 *
 *  1. renders Home and one other page on desktop and Home on a phone with
 *     headless Chrome (ScreenshotRenderer, a node process, never PHP-FPM);
 *  2. sends the JPEGs and a summary of the site plan to the network's strong
 *     design model (ModelRouter::KIND_VISUAL_REVIEW);
 *  3. accepts only fixes from ReviewFixes' fixed list, checked strictly, at
 *     most ReviewFixes::MAX_FIXES; a swapped-in section gets its copy from one
 *     short follow-up call (KIND_VISUAL_REVIEW_COPY);
 *  4. writes them through the mutation journal under its own run.
 *
 * It never throws and never touches publishing: every way out (turned off,
 * no Chrome, no vision provider, render failed, provider failed) is one
 * journal line and a result array.
 */
class VisualReview
{
    const MAX_TOKENS      = 16000;
    const COPY_MAX_TOKENS = 4000;

    /** @var VisualReviewConfig */
    private $config;

    /** @var ScreenshotRenderer */
    private $renderer;

    /** @var ReviewSiteInterface */
    private $site;

    /** @var \SwishfolioCore\AI\Contracts\AIProviderInterface|null */
    private $provider;

    /** @var string */
    private $model;

    /** @var string */
    private $copyModel;

    /** @var UsageLogger|null */
    private $logger;

    /** @var string Business summary for the prompt. */
    private $brief = '';

    /** @var string|null Keep screenshots here instead of a temp dir. */
    private $keepDir = null;

    /**
     * @param VisualReviewConfig       $config    Settings.
     * @param ScreenshotRenderer       $renderer  Renderer.
     * @param ReviewSiteInterface      $site      The site.
     * @param object|null              $provider  AI provider (null: none configured).
     * @param string                   $model     Review model.
     * @param string                   $copyModel Model for a swapped section's copy.
     * @param UsageLogger|null         $logger    Usage log.
     */
    public function __construct(VisualReviewConfig $config, ScreenshotRenderer $renderer, ReviewSiteInterface $site, $provider, $model, $copyModel, UsageLogger $logger = null)
    {
        $this->config    = $config;
        $this->renderer  = $renderer;
        $this->site      = $site;
        $this->provider  = $provider;
        $this->model     = (string) $model;
        $this->copyModel = (string) $copyModel;
        $this->logger    = $logger;
    }

    /**
     * @param string $brief Business name, brief and design wishes.
     *
     * @return self
     */
    public function withBrief($brief)
    {
        $this->brief = (string) $brief;

        return $this;
    }

    /**
     * @param string $dir Directory to keep the screenshots in.
     *
     * @return self
     */
    public function keepShotsIn($dir)
    {
        $this->keepDir = (string) $dir;

        return $this;
    }

    /**
     * @return array {status, reason?, issues, applied, rejected, usage, shots, ms}
     */
    public function run()
    {
        $started = microtime(true);
        $result  = ['status' => 'skipped', 'reason' => '', 'issues' => [], 'applied' => [], 'rejected' => [], 'usage' => ['in' => 0, 'out' => 0, 'calls' => 0], 'shots' => []];

        if (! $this->config->enabled()) {
            return $this->skip($result, 'off', __('Visual review skipped: it is turned off, or no Chrome is installed.', 'swishfolio-core'));
        }
        if ($this->provider === null || $this->provider->getSlug() !== 'anthropic') {
            return $this->skip($result, 'no-vision-provider', __('Visual review skipped: the AI provider cannot look at images.', 'swishfolio-core'));
        }

        $pages = $this->site->pages();
        if (! $pages) {
            return $this->skip($result, 'no-pages', __('Visual review skipped: no built pages to look at.', 'swishfolio-core'));
        }

        $facts = $this->site->facts();
        $dir   = $this->keepDir !== null ? $this->keepDir : rtrim(sys_get_temp_dir(), '/') . '/swish-vr-' . bin2hex(random_bytes(6));

        try {
            $shots = $this->renderer->render(ScreenshotRenderer::standardShots((string) $pages[0]['url'], isset($pages[1]) ? (string) $pages[1]['url'] : ''), $dir);
        } catch (RenderException $e) {
            $this->cleanup($dir);
            $result['ms'] = (int) round((microtime(true) - $started) * 1000);

            return $this->skip($result, 'render-failed', sprintf(/* translators: %s: error. */ __('Visual review skipped: the screenshots could not be taken (%s).', 'swishfolio-core'), $e->getMessage()));
        }

        $result['shots'] = array_map(static function ($s) {
            return ['name' => $s['name'], 'file' => $s['file'], 'width' => $s['width'], 'height' => $s['height'], 'bytes' => $s['bytes']];
        }, $shots);
        $result['renderMs'] = (int) round((microtime(true) - $started) * 1000);

        if (function_exists('do_action')) {
            /** Metering: a provider call for this site starts now (swishfolio-extra opens a credits meter). */
            do_action('swishfolio_visual_review_started', self::blogId());
        }

        $status = 'failed';
        try {
            $request = (new AIRequest(self::systemPrompt(), $this->userPrompt($facts, $shots), ReviewFixes::schema(), $this->model, self::MAX_TOKENS))
                ->withImages(array_map(static function ($s) {
                    return 'data:image/jpeg;base64,' . $s['data'];
                }, $shots));

            $t0       = microtime(true);
            $response = $this->provider->generate($request);
            $this->count($result, $response, $t0, 'visual-review');

            $decoded            = (array) $response->getDecoded();
            $result['issues']   = array_slice(array_map('strval', isset($decoded['issues']) ? (array) $decoded['issues'] : []), 0, 12);
            $checked            = ReviewFixes::validate($decoded, $facts);
            $result['rejected'] = $checked['rejected'];

            $fixes = [];
            foreach ($checked['accepted'] as $fix) {
                if ($fix['type'] === ReviewFixes::SWAP_SECTION) {
                    $fix = $this->withSwapMarkup($fix, $facts, $result);
                    if ($fix === null) {
                        continue;
                    }
                }
                $fixes[] = $fix;
            }

            $result['applied'] = array_map(static function ($f) {
                unset($f['markup']);
                return $f;
            }, $fixes ? $this->site->apply($fixes) : []);
            $status = 'done';
        } catch (\Exception $e) {
            $result['reason'] = $e->getMessage();
        } finally {
            if (function_exists('do_action')) {
                do_action('swishfolio_visual_review_finished', self::blogId(), $status);
            }
            if ($this->keepDir === null) {
                $this->cleanup($dir);
            }
        }

        $result['status'] = $status;
        $result['ms']     = (int) round((microtime(true) - $started) * 1000);

        $summary = $status === 'done'
            ? sprintf(/* translators: 1: fixes applied, 2: fixes refused. */ __('Visual review: %1$d fixes applied, %2$d refused', 'swishfolio-core'), count($result['applied']), count($result['rejected']))
            : sprintf(/* translators: %s: error. */ __('Visual review failed: %s', 'swishfolio-core'), $result['reason']);

        $this->site->note($status, $summary, [
            'issues'   => $result['issues'],
            'applied'  => $result['applied'],
            'rejected' => array_map(static function ($r) {
                return ['type' => is_array($r['fix']) && isset($r['fix']['type']) ? $r['fix']['type'] : '?', 'reason' => $r['reason']];
            }, $result['rejected']),
            'usage'    => $result['usage'],
            'model'    => $this->model,
        ]);

        return $result;
    }

    /**
     * @return int
     */
    private static function blogId()
    {
        return function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 0;
    }

    /**
     * @param array  $result Result so far.
     * @param string $reason Machine reason.
     * @param string $note   Journal line.
     *
     * @return array
     */
    private function skip(array $result, $reason, $note)
    {
        $result['status'] = 'skipped';
        $result['reason'] = $reason;
        $this->site->note('skipped', $note, ['reason' => $reason]);

        return $result;
    }

    /**
     * One short call for the copy of a swapped-in section, from the words of
     * the section it replaces. Null when it cannot be written in full.
     *
     * @param array $fix    Accepted swap.
     * @param array $facts  Facts.
     * @param array $result Result (usage, rejected).
     *
     * @return array|null The fix with its markup.
     */
    private function withSwapMarkup(array $fix, array $facts, array &$result)
    {
        $kit = $this->site->kit();
        if ($kit === null) {
            return null;
        }

        try {
            $required = $kit->requiredSlots($fix['unit']);
            $outline  = $kit->slots($fix['unit'])->outline($kit->slotNotes($fix['unit']));

            $old = [];
            foreach ($facts['pages'][$fix['page']]['texts'] as $slot) {
                if ((int) $slot['section'] === (int) $fix['section']) {
                    $old[] = '- ' . $slot['text'];
                }
            }

            $user = "Business:\n" . $this->brief . "\n\nThe section being replaced said:\n" . ($old ? implode("\n", $old) : '(no long text)')
                . "\n\nNew section outline (slot id, element, the template's demo copy):\n" . $outline
                . "\n\nWrite \"texts\": a JSON object of slot id => new text, for at least these slots: " . implode(', ', $required)
                . '. Plain text only, about as long as the demo copy, true to the business, no invented facts, figures, names or prices, no dashes as punctuation.';

            $schema = [
                'type'                 => 'object',
                'additionalProperties' => false,
                'required'             => ['texts'],
                'properties'           => ['texts' => ['type' => 'object', 'description' => 'slot id => text']],
            ];

            $t0       = microtime(true);
            $response = $this->provider->generate(new AIRequest('You write short, plain website copy for one section of a small business site, in British English.', $user, $schema, $this->copyModel, self::COPY_MAX_TOKENS));
            $this->count($result, $response, $t0, 'visual-review-copy');

            $payload = (array) $response->getDecoded();
            $texts   = [];
            foreach (isset($payload['texts']) && is_array($payload['texts']) ? $payload['texts'] : [] as $slot => $text) {
                $text = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $text)));
                if (preg_match('/^t\d+$/', (string) $slot) && $text !== '' && mb_strlen($text) <= 400) {
                    $texts[(string) $slot] = esc_html(Dashes::text($text));
                }
            }

            if (array_diff($required, array_keys($texts))) {
                $result['rejected'][] = ['fix' => $fix, 'reason' => 'the new section\'s copy could not be written in full'];

                return null;
            }

            $fix['markup'] = $this->site->composeSection($fix['unit'], $texts);

            return $fix;
        } catch (\Exception $e) {
            $result['rejected'][] = ['fix' => $fix, 'reason' => 'swap failed: ' . $e->getMessage()];

            return null;
        }
    }

    /**
     * @param array      $result   Result (usage added).
     * @param AIResponse $response Provider response.
     * @param float      $t0       Start time.
     * @param string     $outcome  Usage log outcome.
     *
     * @return void
     */
    private function count(array &$result, AIResponse $response, $t0, $outcome)
    {
        $result['usage']['in']    += (int) $response->getInputTokens();
        $result['usage']['out']   += (int) $response->getOutputTokens();
        $result['usage']['calls'] += 1;
        if ($this->logger !== null) {
            $this->logger->record($response, 0, (int) round((microtime(true) - $t0) * 1000), $outcome);
        }
    }

    /**
     * @param string $dir Temp dir.
     *
     * @return void
     */
    private function cleanup($dir)
    {
        foreach ((array) glob(rtrim($dir, '/') . '/*') as $file) {
            @unlink($file); // phpcs:ignore WordPress.PHP.NoSilencedErrors
        }
        @rmdir($dir); // phpcs:ignore WordPress.PHP.NoSilencedErrors
    }

    /**
     * @return string
     */
    public static function systemPrompt()
    {
        return <<<'TXT'
You are a senior web designer doing the final visual check of a small business website that an AI pipeline has just built from a template kit and published. You see screenshots: desktop pages at half size and the home page on a phone. The page structure, slot ids and options you may use are listed in the text.

Look for clear, visible problems a careful designer would fix before showing the client:
- decorative art, icons or photos that clash with the colour palette or do not suit the business;
- empty, broken or placeholder-looking blocks, sections that look unfinished or repeat each other;
- a section that reads awkwardly in its place, or a page that runs on too long;
- text with too little contrast against its background;
- headlines or paragraphs so long they wrap badly or crowd the layout, especially on the phone.

Rules:
- Only fixes from the fixed list, only pointing at ids, sections, units, images and tokens the text lists. Anything else is thrown away.
  remove_section {page, section}: never the first section, never a section with the contact form.
  swap_section {page, section, unit}: unit must be one of the alternatives listed for that section.
  swap_image {page, slot (i*), image}: a kit image name from the list, or "neutral" for a quiet abstract placeholder in the site's colours. A neutral placeholder already on the page stays: never replace it with a kit photo (kit photos of people would show a stranger as the owner).
  adjust_token {token, color}: a small change to one palette colour (same hue family, modest lightness change) to fix contrast or a clash. It changes that colour everywhere it is used.
  shorten_copy {page, slot (t*), text}: a clearly shorter version of that exact text, same meaning, plain text, no new facts, British English, never dashes as punctuation.
- At most 6 fixes. Fewer is better: fix what is clearly wrong, not matters of taste. If the site looks good, answer verdict "good" with no fixes.
- Each fix gets a short reason naming what you saw.
- "issues" lists what you noticed, including anything you could not fix with the list.
TXT;
    }

    /**
     * @param array   $facts Facts.
     * @param array[] $shots Rendered shots.
     *
     * @return string
     */
    private function userPrompt(array $facts, array $shots)
    {
        $lines   = [];
        $kit     = $this->site->kit();
        $lines[] = 'Business and what the owner asked for:';
        $lines[] = $this->brief !== '' ? $this->brief : '(not recorded)';
        $lines[] = '';
        if ($kit !== null) {
            $lines[] = sprintf('Template kit: %s (%s). %s', $kit->getName(), $kit->getTitle(), $kit->getLook());
        }
        $tokens = [];
        foreach ($facts['tokens'] as $slug => $hex) {
            $tokens[] = $slug . ' ' . $hex;
        }
        $lines[] = 'Palette tokens: ' . ($tokens ? implode(', ', $tokens) : '(none)');
        $lines[] = '';
        $lines[] = 'Screenshots, in order:';
        foreach ($shots as $i => $s) {
            $lines[] = sprintf('%d. %s', $i + 1, $s['label']);
        }

        foreach ($facts['pages'] as $key => $page) {
            $lines[] = '';
            $lines[] = sprintf('Page "%s": %s (%s)', $key, $page['title'], $page['url']);
            $lines[] = 'Sections, top to bottom:';
            foreach ($page['sections'] as $n => $s) {
                $line = sprintf('  %d. %s%s%s', $n, $s['role'], $s['unit'] !== '' ? ' [' . $s['unit'] . ']' : '', $s['form'] ? ' (contact form)' : '');
                $lines[] = $line;
                foreach ($s['alternatives'] as $unit => $summary) {
                    $lines[] = '     alternative ' . $unit . ': ' . $summary;
                }
            }
            if ($page['texts']) {
                $lines[] = 'Longer text slots (id, section, element, length: text):';
                foreach ($page['texts'] as $id => $t) {
                    $text    = mb_strlen($t['text']) > 220 ? mb_substr($t['text'], 0, 220) . '…' : $t['text'];
                    $lines[] = sprintf('  %s s%d %s %d%s: %s', $id, $t['section'], $t['label'], $t['len'], $t['html'] ? ' (has links, cannot shorten)' : '', $text);
                }
            }
            if ($page['images']) {
                $lines[] = 'Images (id, section: file, alt):';
                foreach ($page['images'] as $id => $img) {
                    $lines[] = sprintf('  %s s%d: %s%s%s', $id, $img['section'], basename($img['file']), $img['alt'] !== '' ? ', "' . mb_substr($img['alt'], 0, 80) . '"' : '', ! empty($img['neutral']) ? ' (neutral placeholder the build chose because no photo fitted: never swap it for a kit photo)' : '');
                }
            }
        }

        if ($facts['kitImages']) {
            $lines[] = '';
            $lines[] = 'Kit images for swap_image (name: what it shows):';
            foreach (array_slice($facts['kitImages'], 0, 40, true) as $name => $alt) {
                $lines[] = '  ' . $name . ': ' . mb_substr($alt, 0, 90);
            }
        }

        return implode("\n", $lines);
    }
}
