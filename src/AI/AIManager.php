<?php
/**
 * Orchestrates one AI edit: context, provider, validation, logging.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI;

use SwishfolioCore\Actions\ActionValidator;
use SwishfolioCore\Actions\ValidationContext;
use SwishfolioCore\Actions\ValidationException;
use SwishfolioCore\AI\Context\ContextAssembler;
use SwishfolioCore\AI\Contracts\AIProviderInterface;

/**
 * Returns validated actions. It never applies them: application happens
 * client-side in the block editor, which is what makes AI edits reversible
 * with native undo and keeps PHP without a write path.
 */
class AIManager
{
    /** @var AIProviderInterface */
    private $provider;

    /** @var ContextAssembler */
    private $assembler;

    /** @var ActionValidator */
    private $validator;

    /** @var UsageLogger */
    private $logger;

    public function __construct(
        AIProviderInterface $provider,
        ContextAssembler $assembler,
        ActionValidator $validator,
        UsageLogger $logger
    ) {
        $this->provider  = $provider;
        $this->assembler = $assembler;
        $this->validator = $validator;
        $this->logger    = $logger;
    }

    /**
     * @param array             $selection   Editor selection payload.
     * @param string            $instruction Natural-language request.
     * @param ValidationContext $context     Validation context.
     * @param string            $model       Model identifier.
     *
     * @throws ProviderException   When the provider call fails.
     * @throws ValidationException When the response is rejected.
     *
     * @return array Validated response: schemaVersion, summary, actions.
     */
    public function edit(array $selection, $instruction, ValidationContext $context, $model)
    {
        $startedAt = microtime(true);

        $request  = $this->assembler->forSelection($selection, $instruction, $model, $context->getMaxActions());
        $response = $this->provider->generate($request);

        $duration = (int) round((microtime(true) - $startedAt) * 1000);

        try {
            $validated = $this->validator->validate($response->getDecoded(), $context);
        } catch (ValidationException $e) {
            $this->logger->recordFailure(
                $e->getStage(),
                $response->getProviderSlug(),
                $response->getModel(),
                $duration
            );

            throw $e;
        }

        $this->logger->record($response, count($validated['actions']), $duration, 'proposed');

        return $validated;
    }
}
