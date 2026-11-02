<?php
/**
 * Categorises a post, page or project.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Tools\Write;

use SwishfolioCore\Tools\ToolContext;
use SwishfolioCore\Tools\ToolError;
use SwishfolioCore\Tools\ToolException;
use SwishfolioCore\Tools\ToolInterface;

/**
 * Terms are named, not numbered, in the input: a model that had to supply term
 * IDs would have to call a term-listing tool first, and would invent IDs the
 * moment it skipped that step. So names come in and this resolves them, which
 * also means an unrecognised name is reported as unrecognised rather than
 * silently applied to the wrong term.
 *
 * Creating missing terms is possible but off by default. A model guessing at a
 * taxonomy tree is how a portfolio ends up with "Web Design", "web design" and
 * "Webdesign" as three separate categories, and the owner cannot see it happen.
 * When the owner asks for a new category, the model passes createMissing.
 *
 * write_draft: a category on a draft is invisible, and on a published post it
 * changes an archive listing rather than the page itself. Fully reversible —
 * the prior term IDs are journalled.
 */
class SetTermsTool extends AbstractWriteTool
{
    const MAX_TERMS = 20;

    public function getName()
    {
        return 'swishfolio/set-terms';
    }

    public function getTitle()
    {
        return __('Set categories', 'swishfolio-core');
    }

    public function getDescription()
    {
        return 'Replace the terms a page, post or project has in one taxonomy, naming the terms as text. '
            . 'This replaces rather than adds, so pass the full set you want. Unrecognised names are reported '
            . 'back and not applied unless you pass createMissing, which you should only do when the owner '
            . 'asked for a new category.';
    }

    public function getSideEffect()
    {
        return ToolInterface::EFFECT_WRITE_DRAFT;
    }

    public function getInputSchema()
    {
        return [
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => ['postId', 'taxonomy', 'terms'],
            'properties'           => [
                'postId' => [
                    'type'    => 'integer',
                    'minimum' => 1,
                ],
                'taxonomy' => [
                    'type'        => 'string',
                    'maxLength'   => 60,
                    'description' => 'Taxonomy name, as site-info reports it for this post type.',
                ],
                'terms' => [
                    'type'     => 'array',
                    'maxItems' => self::MAX_TERMS,
                    'items'    => ['type' => 'string', 'maxLength' => 200],
                ],
                'createMissing' => [
                    'type'        => 'boolean',
                    'default'     => false,
                    'description' => 'Create terms that do not exist yet. Only when the owner asked for one.',
                ],
            ],
        ];
    }

    public function authorize(array $input, ToolContext $context)
    {
        $postId   = isset($input['postId']) ? (int) $input['postId'] : 0;
        $taxonomy = isset($input['taxonomy']) ? (string) $input['taxonomy'] : '';

        if (! current_user_can('edit_post', $postId)) {
            $this->forbid(__('You cannot edit that content.', 'swishfolio-core'));
        }

        $object = $this->taxonomyObject($taxonomy);

        if (null === $object) {
            throw ToolException::make(
                $this->getName(),
                ToolError::TARGET_MISSING,
                sprintf(
                    /* translators: %s: taxonomy name. */
                    __('"%s" is not a taxonomy on this site.', 'swishfolio-core'),
                    $taxonomy
                ),
                ['field' => 'taxonomy']
            );
        }

        // The taxonomy's own capability, read from the registered object, so a
        // site that remaps it is respected.
        $assign = isset($object->cap->assign_terms) ? (string) $object->cap->assign_terms : 'edit_posts';

        if (! current_user_can($assign)) {
            $this->forbid(__('You cannot change categories on this site.', 'swishfolio-core'));
        }

        if (! empty($input['createMissing'])) {
            $edit = isset($object->cap->edit_terms) ? (string) $object->cap->edit_terms : 'manage_categories';

            if (! current_user_can($edit)) {
                $this->forbid(__('You cannot create new categories on this site.', 'swishfolio-core'));
            }
        }
    }

    public function execute(array $input, ToolContext $context)
    {
        $postId   = (int) $input['postId'];
        $taxonomy = (string) $input['taxonomy'];
        $create   = ! empty($input['createMissing']);

        if (! is_object_in_taxonomy((string) get_post_type($postId), $taxonomy)) {
            throw ToolException::make(
                $this->getName(),
                ToolError::TARGET_MISSING,
                sprintf(
                    /* translators: %s: taxonomy name. */
                    __('That kind of content does not use "%s".', 'swishfolio-core'),
                    $taxonomy
                ),
                ['field' => 'taxonomy']
            );
        }

        $resolved = [];
        $unknown  = [];
        $created  = [];

        foreach ((array) $input['terms'] as $name) {
            $name = trim((string) $name);

            if ('' === $name) {
                continue;
            }

            $term = get_term_by('name', $name, $taxonomy);

            if (! $term) {
                $term = get_term_by('slug', sanitize_title($name), $taxonomy);
            }

            if ($term) {
                $resolved[] = (int) $term->term_id;

                continue;
            }

            if (! $create) {
                $unknown[] = $name;

                continue;
            }

            $inserted = wp_insert_term($name, $taxonomy);

            if (is_wp_error($inserted) || ! isset($inserted['term_id'])) {
                $unknown[] = $name;

                continue;
            }

            $resolved[] = (int) $inserted['term_id'];
            $created[]  = $name;
        }

        if ($unknown !== [] && $resolved === []) {
            throw ToolException::make(
                $this->getName(),
                ToolError::TARGET_MISSING,
                sprintf(
                    /* translators: %s: comma-separated term names. */
                    __('No such terms: %s. Pass createMissing to add them.', 'swishfolio-core'),
                    implode(', ', $unknown)
                ),
                ['field' => 'terms', 'unknown' => $unknown]
            );
        }

        $this->journal->setTerms(
            $context,
            $this->getName(),
            $postId,
            $taxonomy,
            $resolved,
            sprintf(
                /* translators: 1: title, 2: taxonomy name. */
                __('Set the %2$s of "%1$s"', 'swishfolio-core'),
                (string) get_post_field('post_title', $postId),
                $taxonomy
            )
        );

        $out = [
            'postId'   => $postId,
            'taxonomy' => $taxonomy,
            'termIds'  => $resolved,
        ];

        if ($created !== []) {
            $out['created'] = $created;
        }

        if ($unknown !== []) {
            $out['unknown'] = $unknown;
        }

        return $out;
    }

    /**
     * @param string $taxonomy Taxonomy name.
     *
     * @return object|null
     */
    private function taxonomyObject($taxonomy)
    {
        if ('' === $taxonomy || ! function_exists('get_taxonomy')) {
            return null;
        }

        $object = get_taxonomy($taxonomy);

        return false === $object ? null : $object;
    }
}
