<?php
/**
 * What the visual review needs from the site (tests replace it).
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Review;

interface ReviewSiteInterface
{
    /**
     * @return array[] Reviewed pages {key, kitKey, postId, title, url, front}, front page first.
     */
    public function pages();

    /**
     * @return \SwishfolioCore\AI\Generation\Kit|null
     */
    public function kit();

    /**
     * @return array ReviewFixes facts (also remembers each page's content hash).
     */
    public function facts();

    /**
     * @param string $unit  Kit section unit.
     * @param array  $texts Slot id => text.
     *
     * @throws \RuntimeException When the unit cannot be composed.
     *
     * @return string Section markup.
     */
    public function composeSection($unit, array $texts);

    /**
     * @param array[] $fixes Validated fixes (swaps carry their markup).
     *
     * @return array[] The fixes that were written.
     */
    public function apply(array $fixes);

    /**
     * Journal one line about the pass (done, skipped, failed).
     *
     * @param string $status  done | skipped | failed.
     * @param string $summary Human summary.
     * @param array  $data    Details.
     *
     * @return void
     */
    public function note($status, $summary, array $data = []);
}
