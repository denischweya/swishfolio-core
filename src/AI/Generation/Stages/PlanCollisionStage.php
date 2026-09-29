<?php
/**
 * Stage 6: nothing already on the site is overwritten by accident.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Generation\Stages;

use SwishfolioCore\AI\Generation\PlanContext;
use SwishfolioCore\AI\Generation\PlanSchema;
use SwishfolioCore\Actions\ValidationException;

/**
 * A slug that is already taken is never silently reused or replaced. The plan has
 * to say which it wants — "suffix" to sit alongside the existing page, or "skip"
 * to leave that page alone and point navigation at it instead — and the choice is
 * recorded on the page so the executor does not have to guess.
 */
class PlanCollisionStage implements PlanStageInterface
{
    public function getSlug()
    {
        return 'plan-collision';
    }

    public function process(array $plan, PlanContext $context)
    {
        $seenSlugs = [];

        foreach ($plan['pages'] as $index => $page) {
            $path = 'pages.' . $index;
            $slug = $page['slug'];

            if ($slug === '') {
                throw new ValidationException($this->getSlug(), 'Page slug is empty after sanitising.', $path . '.slug');
            }

            if (isset($seenSlugs[$slug])) {
                throw new ValidationException(
                    $this->getSlug(),
                    sprintf('Two pages in this plan both want the slug "%s".', $slug),
                    $path . '.slug'
                );
            }

            $seenSlugs[$slug] = true;

            $existingId = $context->getExistingPostId($slug);

            if ($existingId === null) {
                $plan['pages'][$index]['onConflict']     = null;
                $plan['pages'][$index]['existingPostId'] = null;
                continue;
            }

            $strategy = isset($page['onConflict']) ? (string) $page['onConflict'] : '';

            if (! in_array($strategy, PlanSchema::CONFLICT_STRATEGIES, true)) {
                throw new ValidationException(
                    $this->getSlug(),
                    sprintf(
                        'A page with the slug "%s" already exists. Choose onConflict "suffix" or "skip".',
                        $slug
                    ),
                    $path . '.onConflict'
                );
            }

            $plan['pages'][$index]['onConflict']     = $strategy;
            $plan['pages'][$index]['existingPostId'] = $strategy === 'skip' ? $existingId : null;
        }

        return $plan;
    }
}
