<?php
/**
 * Sets the alt text on an image already in the library.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Tools\Write;

use SwishfolioCore\Tools\ToolContext;
use SwishfolioCore\Tools\ToolError;
use SwishfolioCore\Tools\ToolException;
use SwishfolioCore\Tools\ToolInterface;

/**
 * Separate from the upload tool because the common case is a library full of
 * images that were uploaded by hand years ago with no alt text at all. Asking
 * the owner to re-upload them to fix that would be absurd.
 *
 * write_draft: alt text changes what a screen reader announces on pages that
 * already use the image, which is a real change — but it is additive, corrective
 * and fully reversible from the journal, and gating it would mean an approval
 * prompt per image on exactly the task where volume is the point.
 */
class SetAltTextTool extends AbstractWriteTool
{
    public function getName()
    {
        return 'swishfolio/set-alt-text';
    }

    public function getTitle()
    {
        return __('Set image alt text', 'swishfolio-core');
    }

    public function getDescription()
    {
        return 'Set the alt text on an image already in the media library, named by its attachment ID from '
            . 'list-media. Describe what is actually in the picture, as a screen reader would need it — not the '
            . 'page it sits on, and not a keyword list.';
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
            'required'             => ['attachmentId', 'alt'],
            'properties'           => [
                'attachmentId' => [
                    'type'    => 'integer',
                    'minimum' => 1,
                ],
                'alt' => [
                    'type'        => 'string',
                    'maxLength'   => 300,
                    'description' => 'What is in the image.',
                ],
            ],
        ];
    }

    public function authorize(array $input, ToolContext $context)
    {
        $attachmentId = isset($input['attachmentId']) ? (int) $input['attachmentId'] : 0;

        if ($attachmentId <= 0 || get_post_type($attachmentId) !== 'attachment') {
            throw ToolException::make(
                $this->getName(),
                ToolError::TARGET_MISSING,
                __('That is not an image in the media library.', 'swishfolio-core')
            );
        }

        if (! current_user_can('edit_post', $attachmentId)) {
            $this->forbid(__('You cannot edit that image.', 'swishfolio-core'));
        }
    }

    public function execute(array $input, ToolContext $context)
    {
        $attachmentId = (int) $input['attachmentId'];
        $alt          = (string) $input['alt'];

        $this->journal->updateMeta(
            $context,
            $this->getName(),
            $attachmentId,
            UploadMediaTool::ALT_META,
            $alt,
            sprintf(
                /* translators: 1: attachment ID, 2: alt text. */
                __('Set alt text on image #%1$d to "%2$s"', 'swishfolio-core'),
                $attachmentId,
                $alt
            )
        );

        return [
            'attachmentId' => $attachmentId,
            'alt'          => $alt,
        ];
    }
}
