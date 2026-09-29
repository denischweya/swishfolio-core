<?php
/**
 * JSON Schema for a site plan.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Generation;

use SwishfolioCore\Actions\ActionSchema;

/**
 * The contract handed to the AI provider for planning. It shares its version
 * with ActionSchema deliberately: one schemaVersion covers everything the AI is
 * asked to emit, so there is one number to bump rather than two to keep in step.
 *
 * Like ActionSchema this leaves the per-unit maps ("content", "attributes") open
 * on purpose. Which slots a unit declares and which attributes a block exposes
 * differ per unit and per block, and the authoritative constraint is the unit
 * sidecar and the capability manifest, enforced by the validation stages.
 */
class PlanSchema
{
    const SCHEMA_VERSION = ActionSchema::SCHEMA_VERSION;

    const MAX_PAGES              = 12;
    const MAX_SECTIONS_PER_PAGE  = 14;
    const MAX_SECTIONS_TOTAL     = 60;

    const CONFLICT_STRATEGIES = ['suffix', 'skip'];

    /** Page status is never the AI's choice; publishing is a human act. */
    const STATUS = 'draft';

    /**
     * @param string $scope     "site" for a whole site, "page" for one page.
     * @param int    $maxPages  Page count cap.
     *
     * @return array JSON Schema.
     */
    public static function build($scope = 'site', $maxPages = self::MAX_PAGES)
    {
        $schema = [
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => ['schemaVersion', 'summary', 'pages'],
            'properties'           => [
                'schemaVersion' => [
                    'type' => 'string',
                    'enum' => [self::SCHEMA_VERSION],
                ],
                'summary' => [
                    'type'        => 'string',
                    'maxLength'   => 400,
                    'description' => 'One or two sentences describing the plan, shown to the user for review.',
                ],
                'pages' => [
                    'type'     => 'array',
                    'minItems' => 1,
                    'maxItems' => $scope === 'page' ? 1 : (int) $maxPages,
                    'items'    => self::pageSchema(),
                ],
            ],
        ];

        if ($scope !== 'page') {
            $schema['required'][]        = 'site';
            $schema['properties']['site'] = self::siteSchema();
        }

        return $schema;
    }

    /**
     * @return array
     */
    private static function siteSchema()
    {
        return [
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => ['frontPage', 'navigation'],
            'properties'           => [
                'title' => ['type' => 'string', 'maxLength' => 120],
                'tagline' => ['type' => 'string', 'maxLength' => 200],
                'styleVariation' => [
                    'type'        => 'string',
                    'description' => 'Title of a style variation this theme ships. Omit to leave the current one alone.',
                ],
                'frontPage' => [
                    'type'        => 'string',
                    'description' => 'The key of the page that should become the front page.',
                ],
                'blogPage' => [
                    'type'        => 'string',
                    'description' => 'The key of the page that should list blog posts. Omit when there is none.',
                ],
                'navigation' => [
                    'type'        => 'array',
                    'maxItems'    => (int) self::MAX_PAGES,
                    'description' => 'Page keys, in menu order.',
                    'items'       => ['type' => 'string'],
                ],
            ],
        ];
    }

    /**
     * @return array
     */
    private static function pageSchema()
    {
        return [
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => ['key', 'title', 'slug', 'sections'],
            'properties'           => [
                'key' => [
                    'type'        => 'string',
                    'description' => 'Lowercase identifier unique within the plan. The only way pages refer to each other.',
                ],
                'title' => ['type' => 'string', 'maxLength' => 120],
                'slug'  => ['type' => 'string', 'maxLength' => 120],
                'status' => [
                    'type'        => 'string',
                    'enum'        => [self::STATUS],
                    'description' => 'Always "draft". Nothing is ever published for the user.',
                ],
                'template' => [
                    'type'        => 'string',
                    'description' => 'A custom template name this theme declares, or omit for the default.',
                ],
                'onConflict' => [
                    'type'        => 'string',
                    'enum'        => self::CONFLICT_STRATEGIES,
                    'description' => '"suffix" to create the page alongside an existing one with the same slug, "skip" to reuse the existing page.',
                ],
                'sections' => [
                    'type'     => 'array',
                    'minItems' => 1,
                    'maxItems' => (int) self::MAX_SECTIONS_PER_PAGE,
                    'items'    => self::sectionSchema(),
                ],
            ],
        ];
    }

    /**
     * @return array
     */
    private static function sectionSchema()
    {
        return [
            'type'                 => 'object',
            'additionalProperties' => false,
            'properties'           => [
                'pattern' => [
                    'type'        => 'string',
                    'description' => 'Id of a generation unit from the catalogue. Use this or "block", never both.',
                ],
                'content' => [
                    'type'        => 'object',
                    'description' => 'Slot name to copy, for the slots that unit declares.',
                ],
                'block' => [
                    'type'        => 'string',
                    'description' => 'Name of a dynamic block from the catalogue. Use this or "pattern", never both.',
                ],
                'attributes' => [
                    'type'        => 'object',
                    'description' => 'Attributes for that block, from its capability manifest.',
                ],
            ],
        ];
    }
}
