<?php
/**
 * The shape a CV is reduced to.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Onboarding;

use SwishfolioCore\AI\Settings;

/**
 * The enums are the same closed vocabularies Settings::saveBrand validates
 * against, read from Settings rather than restated. A schema that let the model
 * suggest "elegant-modern" would produce an answer the brand screen silently
 * discarded, and the owner would see their choice not stick with no explanation.
 */
class ProfileSchema
{
    const MAX_ITEMS = 8;

    /**
     * @return array JSON Schema for the extracted profile.
     */
    public static function build()
    {
        return [
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => ['name', 'headline', 'summary'],
            'properties'           => [
                'name' => [
                    'type'        => 'string',
                    'description' => 'The person or business the site is for.',
                ],
                'headline' => [
                    'type'        => 'string',
                    'description' => 'One short line describing what they do.',
                ],
                'summary' => [
                    'type'        => 'string',
                    'description' => 'Two or three sentences for an About section, in their voice.',
                ],
                'services' => [
                    'type'     => 'array',
                    'maxItems' => self::MAX_ITEMS,
                    'items'    => [
                        'type'                 => 'object',
                        'additionalProperties' => false,
                        'required'             => ['title', 'description'],
                        'properties'           => [
                            'title'       => ['type' => 'string'],
                            'description' => ['type' => 'string'],
                        ],
                    ],
                ],
                'projects' => [
                    'type'     => 'array',
                    'maxItems' => self::MAX_ITEMS,
                    'items'    => [
                        'type'                 => 'object',
                        'additionalProperties' => false,
                        'required'             => ['title', 'description'],
                        'properties'           => [
                            'title'       => ['type' => 'string'],
                            'description' => ['type' => 'string'],
                            'role'        => ['type' => 'string'],
                            'year'        => ['type' => 'string'],
                        ],
                    ],
                ],
                'skills' => [
                    'type'     => 'array',
                    'maxItems' => 20,
                    'items'    => ['type' => 'string'],
                ],
                'designStyle' => [
                    'type'        => 'string',
                    'enum'        => Settings::DESIGN_STYLES,
                    'description' => 'The design style that suits this person.',
                ],
                'copywritingTone' => [
                    'type'        => 'string',
                    'enum'        => Settings::TONES,
                    'description' => 'The tone their copy should be written in.',
                ],
                'missing' => [
                    'type'     => 'array',
                    'maxItems' => 6,
                    'items'    => ['type' => 'string'],
                    'description' => 'Things a good site would need that the CV does not say.',
                ],
            ],
        ];
    }
}
