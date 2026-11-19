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
 * Slot edits are lists of closed {id, html} / {id, value} objects rather than
 * open maps, so structured-output providers can hold the model to the shape.
 * Which ids a section has differs per unit; the authority is the unit's own
 * markup (CopySlots) and, for dynamic blocks, the capability manifest, both
 * enforced by the validation stages. "attributes" stays open for that reason.
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
     * @param string $kit       Slug of the one kit the plan may use.
     *
     * @return array JSON Schema.
     */
    public static function build($scope = 'site', $maxPages = self::MAX_PAGES, $kit = '')
    {
        $schema = [
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => ['schemaVersion', 'summary', 'kit', 'pages'],
            'properties'           => [
                'schemaVersion' => [
                    'type' => 'string',
                    'enum' => [self::SCHEMA_VERSION],
                ],
                'kit' => $kit === ''
                    ? ['type' => 'string', 'description' => 'Slug of the kit every section comes from.']
                    : ['type' => 'string', 'enum' => [(string) $kit], 'description' => 'Slug of the kit every section comes from.'],
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
        $text = [
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => ['id', 'html'],
            'properties'           => [
                'id'   => ['type' => 'string', 'description' => 'A t* slot id from the unit\'s outline.'],
                'html' => ['type' => 'string', 'description' => 'New inner HTML: the same inline tags and classes, new words.'],
            ],
        ];
        $attr = [
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => ['id', 'value'],
            'properties'           => [
                'id'    => ['type' => 'string', 'description' => 'An a* slot id from the unit\'s outline.'],
                'value' => ['type' => 'string'],
            ],
        ];

        return [
            'type'                 => 'object',
            'additionalProperties' => false,
            'properties'           => [
                'pattern' => [
                    'type'        => 'string',
                    'description' => 'Id of a section unit from the kit. Use this or "block", never both.',
                ],
                'texts' => [
                    'type'        => 'array',
                    'description' => 'New copy for the unit\'s text slots. Every slot with demo copy must be rewritten or sit in a removed unit.',
                    'items'       => $text,
                ],
                'attrs' => [
                    'type'        => 'array',
                    'description' => 'New values for the unit\'s attribute slots (aria-labels, placeholders, tel:/mailto: links).',
                    'items'       => $attr,
                ],
                'remove' => [
                    'type'        => 'array',
                    'description' => 'Ids of [u*] units to leave out (cards, list items, questions the owner has no use for).',
                    'items'       => ['type' => 'string'],
                ],
                'clones' => [
                    'type'        => 'array',
                    'description' => 'Extra copies of a repeatable [u*] unit, each placed after the original with its own copy.',
                    'items'       => [
                        'type'                 => 'object',
                        'additionalProperties' => false,
                        'required'             => ['unit', 'texts'],
                        'properties'           => [
                            'unit'  => ['type' => 'string'],
                            'texts' => ['type' => 'array', 'items' => $text],
                            'attrs' => ['type' => 'array', 'items' => $attr],
                        ],
                    ],
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
