<?php
/**
 * JSON Schema for AI action responses.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Actions;

/**
 * The contract handed to the AI provider, which enforces it server-side.
 *
 * The schema constrains the envelope and the shape of each action. It leaves
 * "changes" an open object on purpose: permitted keys differ per block, and the
 * authoritative constraint is the capability manifest enforced by the allowlist
 * and value stages. The manifests go into the prompt, not the schema.
 */
class ActionSchema
{
    const SCHEMA_VERSION = '1.0';

    const TYPES = [
        'update_block_attributes',
        'update_block_content',
        'insert_block',
        'remove_block',
    ];

    const DEFAULT_MAX_ACTIONS = 25;

    /**
     * @param int $maxActions Action count cap.
     *
     * @return array JSON Schema.
     */
    public static function build($maxActions = self::DEFAULT_MAX_ACTIONS)
    {
        return [
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => ['schemaVersion', 'summary', 'actions'],
            'properties'           => [
                'schemaVersion' => [
                    'type' => 'string',
                    'enum' => [self::SCHEMA_VERSION],
                ],
                'summary' => [
                    'type'        => 'string',
                    'maxLength'   => 300,
                    'description' => 'One sentence describing the change, shown to the user.',
                ],
                'actions' => [
                    'type'     => 'array',
                    'maxItems' => (int) $maxActions,
                    'items'    => [
                        'type'                 => 'object',
                        'additionalProperties' => false,
                        'required'             => ['type'],
                        'properties'           => [
                            'type'           => ['type' => 'string', 'enum' => self::TYPES],
                            'clientId'       => ['type' => 'string'],
                            'parentClientId' => ['type' => 'string'],
                            'blockName'      => ['type' => 'string'],
                            'index'          => ['type' => 'integer', 'minimum' => 0],
                            'content'        => ['type' => 'string'],
                            'changes'        => ['type' => 'object'],
                            'attributes'     => ['type' => 'object'],
                            'reason'         => ['type' => 'string', 'maxLength' => 200],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Required payload keys per action type, enforced by the envelope stage.
     *
     * @return array
     */
    public static function requiredKeys()
    {
        return [
            'update_block_attributes' => ['clientId', 'changes'],
            'update_block_content'    => ['clientId', 'content'],
            'insert_block'            => ['parentClientId', 'blockName'],
            'remove_block'            => ['clientId'],
        ];
    }
}
