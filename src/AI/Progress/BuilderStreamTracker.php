<?php
/**
 * Turns a streaming builder reply into progress steps.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Progress;

use SwishfolioCore\Jobs\JobProgress;

/**
 * The builder's reply is JSON: {"summary": "...", "operations": [{"op", "index",
 * "markup"}, ...]}. While it streams, this reads the partial text and reports
 * what is actually being written: the summary as soon as it is complete, then
 * one step per top-level block of markup (a "section"), labelled with the
 * section's first heading once it appears. Block boundaries are found from the
 * block comment delimiters, counting depth, so nested blocks do not count as
 * sections.
 *
 * It only reads; the full reply is parsed and validated after the stream ends.
 */
class BuilderStreamTracker
{
    // JSON may escape "/" as "\/", so both spellings are accepted.
    const OPENER = '#<!--\s+((?:\\\\)?/)?wp:([a-z0-9/\\\\_-]+)#';
    const OP     = '#"op"\s*:\s*"([a-z_]+)"\s*,\s*"index"\s*:\s*(-?\d+)#';

    /** @var JobProgress */
    private $progress;

    /** @var string page|selection */
    private $scope;

    /** @var int */
    private $sectionCount;

    /** @var string */
    private $text = '';

    /** @var bool */
    private $writing = false;

    /** @var bool */
    private $summaryDone = false;

    /** @var int Where to look for the next operation header. */
    private $opScan = 0;

    /** @var array|null */
    private $op = null;

    /** @var int Where block scanning resumes. */
    private $scan = 0;

    /** @var int */
    private $depth = 0;

    /** @var array|null Section being written. */
    private $section = null;

    /** @var int */
    private $written = 0;

    /** @var int */
    private $appended = 0;

    /**
     * @param JobProgress $progress     Where steps go.
     * @param string      $scope        "page" or "selection".
     * @param int         $sectionCount Sections sent to the model.
     */
    public function __construct(JobProgress $progress, $scope, $sectionCount)
    {
        $this->progress     = $progress;
        $this->scope        = (string) $scope;
        $this->sectionCount = (int) $sectionCount;
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
            $this->reset();
            $this->progress->step('ask', __('Asking AI again', 'swishfolio-core'), __('The first attempt was interrupted', 'swishfolio-core'));

            return;
        }

        if ($type === 'thinking' && ! $this->writing) {
            $this->progress->detail('ask', __('Thinking it through', 'swishfolio-core'));

            return;
        }

        if ($type !== 'text') {
            return;
        }

        if (! $this->writing) {
            $this->writing = true;
            $this->progress->step('plan', __('Planning the changes', 'swishfolio-core'));
        }

        $this->text .= (string) $event['delta'];
        $this->process();
    }

    /**
     * Close the last open section once the stream has ended.
     *
     * @return void
     */
    public function finish()
    {
        if ($this->section !== null) {
            $this->closeSection();
        }

        $this->progress->flush();
    }

    /**
     * @return int Sections seen in the stream.
     */
    public function written()
    {
        return $this->written;
    }

    /**
     * @return void
     */
    private function reset()
    {
        $this->text        = '';
        $this->writing     = false;
        $this->summaryDone = false;
        $this->opScan      = 0;
        $this->op          = null;
        $this->scan        = 0;
        $this->depth       = 0;
        $this->section     = null;
        $this->written     = 0;
        $this->appended    = 0;
    }

    /**
     * @return void
     */
    private function process()
    {
        if (! $this->summaryDone && preg_match('#"summary"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"#s', $this->text, $m)) {
            $this->summaryDone = true;
            $summary           = self::unescape($m[1]);

            $this->progress->done('plan', $summary);
        }

        while (true) {
            $next  = preg_match(self::OP, $this->text, $m, PREG_OFFSET_CAPTURE, $this->opScan) ? $m : null;
            $limit = $next ? $next[0][1] : strlen($this->text);

            if ($this->op !== null) {
                $this->scanBlocks($limit);
            }

            if ($next === null) {
                break;
            }

            if ($this->section !== null) {
                $this->closeSection();
            }

            $this->op     = ['op' => $next[1][0], 'index' => (int) $next[2][0], 'blocks' => 0];
            $this->opScan = $next[0][1] + strlen($next[0][0]);
            $this->scan   = $this->opScan;
            $this->depth  = 0;

            if ($this->op['op'] === 'remove') {
                $this->progress->instant(
                    'remove-' . $this->op['index'],
                    /* translators: %d: section number. */
                    sprintf(__('Removing section %d', 'swishfolio-core'), $this->op['index'] + 1)
                );
            }
        }

        if ($this->section !== null && $this->section['heading'] === '') {
            $this->findHeading();
        }
    }

    /**
     * @param int $limit Scan no further than this offset.
     *
     * @return void
     */
    private function scanBlocks($limit)
    {
        while (preg_match(self::OPENER, $this->text, $m, PREG_OFFSET_CAPTURE, $this->scan) && $m[0][1] < $limit) {
            $end = strpos($this->text, '-->', $m[0][1]);

            if ($end === false || $end >= $limit) {
                return; // The comment is still arriving.
            }

            $this->scan = $end + 3;
            $closing    = $m[1][0] !== '';
            $selfClose  = ! $closing && substr(rtrim(substr($this->text, $m[0][1], $end - $m[0][1])), -1) === '/';

            if ($closing) {
                $this->depth = max(0, $this->depth - 1);

                if ($this->depth === 0 && $this->section !== null) {
                    $this->closeSection();
                }

                continue;
            }

            if ($this->depth === 0) {
                $this->openSection(str_replace('\\', '', $m[2][0]), $m[0][1]);

                if ($selfClose) {
                    $this->closeSection();
                } else {
                    $this->depth = 1;
                }

                continue;
            }

            if (! $selfClose) {
                $this->depth++;
            }

            $this->section['blocks']++;
            $this->progress->detail($this->section['key'], $this->sectionDetail());
        }
    }

    /**
     * @param string $blockName Block name.
     * @param int    $offset    Where it starts.
     *
     * @return void
     */
    private function openSection($blockName, $offset)
    {
        $this->written++;
        $this->op['blocks']++;

        $this->section = [
            'key'     => 'section-' . $this->written,
            'block'   => $blockName,
            'offset'  => $offset,
            'heading' => '',
            'blocks'  => 0,
        ];

        $this->progress->step($this->section['key'], $this->sectionLabel(), $this->blockLabel($blockName));
    }

    /**
     * @return void
     */
    private function closeSection()
    {
        $this->findHeading();
        $this->progress->done($this->section['key'], $this->sectionDetail());
        $this->section = null;
    }

    /**
     * @return string
     */
    private function sectionLabel()
    {
        $op    = $this->op['op'];
        $index = $this->op['index'];
        $first = $this->op['blocks'] === 1;

        if ($op === 'replace' && $first) {
            if ($this->scope === 'selection') {
                return $this->sectionCount > 1
                    /* translators: 1: block number, 2: number of selected blocks. */
                    ? sprintf(__('Rewriting selected block %1$d of %2$d', 'swishfolio-core'), $index + 1, $this->sectionCount)
                    : __('Rewriting the selected block', 'swishfolio-core');
            }

            /* translators: 1: section number, 2: number of sections. */
            return sprintf(__('Rewriting section %1$d of %2$d', 'swishfolio-core'), $index + 1, $this->sectionCount);
        }

        if ($op === 'append' && $this->scope === 'page') {
            $this->appended++;

            /* translators: %d: section number. */
            return sprintf(__('Writing section %d', 'swishfolio-core'), $this->sectionCount + $this->appended);
        }

        if ($first && $this->scope === 'page' && ($op === 'insert_before' || $op === 'insert_after')) {
            return $op === 'insert_before'
                /* translators: %d: section number. */
                ? sprintf(__('Writing a new section before section %d', 'swishfolio-core'), $index + 1)
                /* translators: %d: section number. */
                : sprintf(__('Writing a new section after section %d', 'swishfolio-core'), $index + 1);
        }

        return __('Writing a new section', 'swishfolio-core');
    }

    /**
     * @return string
     */
    private function sectionDetail()
    {
        $label = $this->section['heading'] !== '' ? '"' . $this->section['heading'] . '"' : $this->blockLabel($this->section['block']);

        if ($this->section['blocks'] > 0) {
            /* translators: %d: number of blocks. */
            $count = sprintf(_n('%d block', '%d blocks', $this->section['blocks'] + 1, 'swishfolio-core'), $this->section['blocks'] + 1);
            $label = $label === '' ? $count : $label . ' · ' . $count;
        }

        return $label;
    }

    /**
     * @param string $name Block name.
     *
     * @return string
     */
    private function blockLabel($name)
    {
        $short = substr((string) $name, strrpos('/' . $name, '/'));

        if (in_array($short, ['group', 'columns', 'cover'], true) && (strpos($name, '/') === false || strpos($name, 'core/') === 0)) {
            return '';
        }

        return ucfirst(str_replace(['-', '_'], ' ', $short));
    }

    /**
     * @return void
     */
    private function findHeading()
    {
        if ($this->section === null || $this->section['heading'] !== '') {
            return;
        }

        if (preg_match('#<h[1-6][^>]*>(.*?)<\\\\?/h[1-6]>#s', substr($this->text, $this->section['offset']), $m)) {
            // Every tag (br, spans, img) becomes a space so words never run together.
            $heading = html_entity_decode(preg_replace('#<[^>]*>#', ' ', self::unescape($m[1])), ENT_QUOTES, 'UTF-8');
            $heading = trim(preg_replace('/\s+/u', ' ', $heading));
            $heading = preg_replace('/\s+([,.!?;:])/u', '$1', $heading);

            if ($heading !== '') {
                $this->section['heading'] = mb_strimwidth($heading, 0, 60, '…');
                $this->progress->detail($this->section['key'], $this->sectionDetail());
            }
        }
    }

    /**
     * @param string $fragment JSON string contents without quotes.
     *
     * @return string
     */
    public static function unescape($fragment)
    {
        $decoded = json_decode('"' . $fragment . '"');

        return is_string($decoded) ? $decoded : stripslashes($fragment);
    }
}
