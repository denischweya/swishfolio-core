<?php

namespace SwishfolioCore\Tests\Unit\Generation;

use PHPUnit\Framework\TestCase;

/**
 * A `ref` unit's summary is how the planner decides whether to use it, so a
 * summary naming a block the pattern does not contain steers generation wrong.
 *
 * Two shipped summaries did exactly that — they were written from slug names
 * rather than from the patterns — so this compares every claim against the
 * markup, using block titles read from block.json rather than a hardcoded list.
 */
class UnitSummaryAccuracyTest extends TestCase
{
    const THEME_PATTERNS = SFCORE_PLUGIN_DIR . '../../themes/swishfolio/patterns/';

    /**
     * @return array Block title => block name, longest title first so
     *               "Bento Cards" is matched before "Card".
     */
    private function blockTitles()
    {
        $titles = [];

        foreach ((array) glob(SFCORE_PLUGIN_DIR . 'blocks/*/block.json') as $file) {
            $data = json_decode((string) file_get_contents($file), true);

            if (is_array($data) && ! empty($data['title']) && ! empty($data['name'])) {
                $titles[(string) $data['title']] = (string) $data['name'];
            }
        }

        uksort($titles, function ($a, $b) {
            return strlen($b) - strlen($a);
        });

        return $titles;
    }

    /**
     * @param string $slug Theme pattern slug, without the swishfolio/ prefix.
     *
     * @return string[] Plugin block names present in that pattern.
     */
    private function blocksIn($slug)
    {
        $file = self::THEME_PATTERNS . $slug . '.php';

        if (! file_exists($file)) {
            return [];
        }

        preg_match_all(
            '/wp:(swishfolio-core\/[a-z0-9-]+)/',
            (string) file_get_contents($file),
            $matches
        );

        return array_values(array_unique($matches[1]));
    }

    public function test_the_corpus_is_present_so_this_guard_is_not_vacuous()
    {
        $units = glob(SFCORE_PLUGIN_DIR . 'src/AI/Generation/Patterns/section-*.json');

        $this->assertGreaterThanOrEqual(25, count((array) $units));
        $this->assertNotSame([], $this->blockTitles());
    }

    public function test_no_unit_summary_names_a_block_its_pattern_does_not_contain()
    {
        $titles  = $this->blockTitles();
        $wrong   = [];

        foreach ((array) glob(SFCORE_PLUGIN_DIR . 'src/AI/Generation/Patterns/section-*.json') as $file) {
            $unit = json_decode((string) file_get_contents($file), true);

            if (! is_array($unit) || empty($unit['ref'])) {
                continue;
            }

            $slug    = substr($unit['ref'], strlen('swishfolio/'));
            $present = $this->blocksIn($slug);
            $summary = isset($unit['summary']) ? (string) $unit['summary'] : '';

            foreach ($titles as $title => $blockName) {
                // Only a claim of the form "... the <Title> block" counts;
                // "gallery" as an English word must not trip this.
                if (stripos($summary, $title . ' block') === false) {
                    continue;
                }

                if (! in_array($blockName, $present, true)) {
                    $wrong[] = sprintf('%s claims "%s block" but %s has none', $unit['id'], $title, $slug);
                }
            }
        }

        $this->assertSame([], $wrong, implode('; ', $wrong));
    }
}
