<?php
/**
 * One step in the action validation pipeline.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Actions\Stages;

use SwishfolioCore\Actions\ValidationContext;

interface StageInterface
{
    /**
     * @param array             $response AI response, as returned by the previous stage.
     * @param ValidationContext $context  Validation context.
     *
     * @throws \SwishfolioCore\Actions\ValidationException When the response is unacceptable.
     *
     * @return array The response, possibly transformed.
     */
    public function process(array $response, ValidationContext $context);

    /**
     * @return string Stage slug reported on failure.
     */
    public function getSlug();
}
