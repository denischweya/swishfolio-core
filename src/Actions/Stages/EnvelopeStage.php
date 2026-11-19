<?php
/**
 * Stage 1: the response envelope is well-formed.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Actions\Stages;

use SwishfolioCore\Actions\ActionSchema;
use SwishfolioCore\Actions\ValidationContext;
use SwishfolioCore\Actions\ValidationException;

class EnvelopeStage implements StageInterface
{
    public function getSlug()
    {
        return 'envelope';
    }

    public function process(array $response, ValidationContext $context)
    {
        $version = isset($response['schemaVersion']) ? $response['schemaVersion'] : null;

        if ($version !== ActionSchema::SCHEMA_VERSION) {
            throw new ValidationException(
                $this->getSlug(),
                sprintf('Unsupported schema version "%s".', (string) $version),
                'schemaVersion'
            );
        }

        if (! isset($response['actions']) || ! is_array($response['actions'])) {
            throw new ValidationException($this->getSlug(), 'Response has no actions array.', 'actions');
        }

        $actions = $response['actions'];

        if ($actions !== [] && array_keys($actions) !== range(0, count($actions) - 1)) {
            throw new ValidationException($this->getSlug(), 'Actions must be a list.', 'actions');
        }

        if (count($actions) > $context->getMaxActions()) {
            throw new ValidationException(
                $this->getSlug(),
                sprintf('Too many actions: %d (max %d).', count($actions), $context->getMaxActions()),
                'actions'
            );
        }

        $required = ActionSchema::requiredKeys();

        foreach ($actions as $index => $action) {
            $path = 'actions.' . $index;

            if (! is_array($action) || ! isset($action['type'])) {
                throw new ValidationException($this->getSlug(), 'Action has no type.', $path);
            }

            $type = $action['type'];

            if (! in_array($type, ActionSchema::TYPES, true)) {
                throw new ValidationException(
                    $this->getSlug(),
                    sprintf('Unknown action type "%s".', (string) $type),
                    $path . '.type'
                );
            }

            foreach ($required[$type] as $key) {
                if (! isset($action[$key]) || $action[$key] === '' || $action[$key] === []) {
                    throw new ValidationException(
                        $this->getSlug(),
                        sprintf('Action "%s" is missing "%s".', $type, $key),
                        $path . '.' . $key
                    );
                }
            }
        }

        return $response;
    }
}
