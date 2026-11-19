<?php
/**
 * Runs the action validation pipeline.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Actions;

use SwishfolioCore\Actions\Stages\AllowlistStage;
use SwishfolioCore\Actions\Stages\EnvelopeStage;
use SwishfolioCore\Actions\Stages\SanitizeStage;
use SwishfolioCore\Actions\Stages\StageInterface;
use SwishfolioCore\Actions\Stages\TargetStage;
use SwishfolioCore\Actions\Stages\ValueStage;

/**
 * All-or-nothing: any stage failure rejects the whole response. Applying half
 * an AI edit is worse than applying none of it.
 */
class ActionValidator
{
    /** @var StageInterface[] */
    private $stages;

    /**
     * @param StageInterface[] $stages Ordered pipeline.
     */
    public function __construct(array $stages)
    {
        $this->stages = $stages;
    }

    /**
     * @return self
     */
    public static function withDefaultStages()
    {
        return new self([
            new EnvelopeStage(),
            new TargetStage(),
            new AllowlistStage(),
            new ValueStage(),
            new SanitizeStage(),
        ]);
    }

    /**
     * @param array             $response Decoded AI response.
     * @param ValidationContext $context  Validation context.
     *
     * @throws ValidationException When any stage rejects the response.
     *
     * @return array Validated, normalised response.
     */
    public function validate(array $response, ValidationContext $context)
    {
        foreach ($this->stages as $stage) {
            $response = $stage->process($response, $context);
        }

        return $response;
    }
}
