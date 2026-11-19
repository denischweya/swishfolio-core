<?php
/**
 * Turns a streaming site plan into progress steps.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Progress;

use SwishfolioCore\Jobs\JobProgress;

/**
 * A plan streams as {"schemaVersion", "summary", "pages": [{"key", "title",
 * "slug", ..., "sections": [{"pattern" | "block", "content"}]}], "site": {...}}.
 * This reports the summary once complete, one "Writing <page>" step per page as
 * its title arrives (with its sections counted as they appear), and the site
 * settings step when the "site" object starts.
 */
class PlanStreamTracker
{
    const PAGE    = '#"key"\s*:\s*"[^"]*"[^{}\[\]]{0,300}?"title"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"#s';
    const SECTION = '#"(?:pattern|block)"\s*:\s*"([^"]*)"#';
    const SITE    = '#"site"\s*:\s*\{#';

    /** @var JobProgress */
    private $progress;

    /** @var string */
    private $text = '';

    /** @var bool */
    private $writing = false;

    /** @var bool */
    private $summaryDone = false;

    /** @var array[] Pages seen: offset, title, sections. */
    private $pages = [];

    /** @var bool */
    private $site = false;

    /**
     * @param JobProgress $progress Where steps go.
     */
    public function __construct(JobProgress $progress)
    {
        $this->progress = $progress;
    }

    /**
     * @param array $event Provider progress event.
     *
     * @return void
     */
    public function __invoke(array $event)
    {
        $type = isset($event['type']) ? $event['type'] : '';

        if ($type === 'restart') {
            $this->text        = '';
            $this->writing     = false;
            $this->summaryDone = false;
            $this->pages       = [];
            $this->site        = false;
            $this->progress->step('ask', __('Asking AI again', 'swishfolio-core'), __('The first attempt was interrupted', 'swishfolio-core'));

            return;
        }

        if ($type === 'thinking' && ! $this->writing) {
            $this->progress->detail('ask', __('Thinking about your pages', 'swishfolio-core'));

            return;
        }

        if ($type !== 'text') {
            return;
        }

        if (! $this->writing) {
            $this->writing = true;
            $this->progress->step('outline', __('Outlining your site', 'swishfolio-core'));
        }

        $this->text .= (string) $event['delta'];
        $this->process();
    }

    /**
     * @return void
     */
    public function finish()
    {
        $this->progress->done();
    }

    /**
     * @return void
     */
    private function process()
    {
        if (! $this->summaryDone && preg_match('#"summary"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"#s', $this->text, $m)) {
            $this->summaryDone = true;
            $this->progress->done('outline', BuilderStreamTracker::unescape($m[1]));
        }

        $from = $this->pages ? end($this->pages)['offset'] + 1 : 0;

        while (preg_match(self::PAGE, $this->text, $m, PREG_OFFSET_CAPTURE, $from)) {
            $this->countSections($m[0][1]); // Final count for the page being closed.

            $title         = BuilderStreamTracker::unescape($m[1][0]);
            $this->pages[] = ['offset' => $m[0][1], 'title' => $title, 'sections' => []];
            $from          = $m[0][1] + 1;

            $this->progress->step(
                'page-' . count($this->pages),
                /* translators: %s: page title. */
                sprintf(__('Writing %s', 'swishfolio-core'), $title)
            );
        }

        if (! $this->site && preg_match(self::SITE, $this->text, $m, PREG_OFFSET_CAPTURE)) {
            $this->countSections($m[0][1]);
            $this->site = true;
            $this->progress->step('site', __('Choosing navigation and style', 'swishfolio-core'));
        }

        if (! $this->site) {
            $this->countSections();
        }
    }

    /**
     * Update the current page's detail with its sections so far.
     *
     * @param int|null $end Where the page ends, when known.
     *
     * @return void
     */
    private function countSections($end = null)
    {
        if (! $this->pages) {
            return;
        }

        $last   = count($this->pages) - 1;
        $offset = $this->pages[$last]['offset'];

        if (! preg_match_all(self::SECTION, $end === null ? substr($this->text, $offset) : substr($this->text, $offset, $end - $offset), $m)) {
            return;
        }

        $names = array_map(function ($id) {
            $short = substr($id, strrpos('/' . $id, '/'));

            return str_replace(['-', '_'], ' ', $short);
        }, $m[1]);

        if ($names === $this->pages[$last]['sections']) {
            return;
        }

        $this->pages[$last]['sections'] = $names;

        $this->progress->detail(
            'page-' . ($last + 1),
            /* translators: 1: number of sections, 2: list of section names. */
            sprintf(_n('%1$d section: %2$s', '%1$d sections: %2$s', count($names), 'swishfolio-core'), count($names), implode(', ', $names))
        );
    }
}
