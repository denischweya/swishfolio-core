<?php
/**
 * Stage 2: every action targets a block the editor actually submitted.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Actions\Stages;

use SwishfolioCore\Actions\ValidationContext;
use SwishfolioCore\Actions\ValidationException;

/**
 * The server cannot independently prove a clientId is real — it only knows what
 * the editor sent. That is acceptable because the server never writes; the
 * client re-checks returned clientIds against the set it sent before applying.
 */
class TargetStage implements StageInterface
{
    public function getSlug()
    {
        return 'target';
    }

    public function process(array $response, ValidationContext $context)
    {
        foreach ($response['actions'] as $index => $action) {
            $path = 'actions.' . $index;

            if ($action['type'] === 'insert_block') {
                $this->requireKnownBlock($context, $action['parentClientId'], $path . '.parentClientId');
                continue;
            }

            $this->requireKnownBlock($context, $action['clientId'], $path . '.clientId');

            if ($action['type'] === 'remove_block' && ! $context->isWithinSelection($action['clientId'])) {
                throw new ValidationException(
                    $this->getSlug(),
                    sprintf('Block "%s" is outside the current selection.', $action['clientId']),
                    $path . '.clientId'
                );
            }
        }

        return $response;
    }

    private function requireKnownBlock(ValidationContext $context, $clientId, $path)
    {
        if ($context->getBlock($clientId) === null) {
            throw new ValidationException(
                $this->getSlug(),
                sprintf('Unknown block "%s".', (string) $clientId),
                $path
            );
        }
    }
}
