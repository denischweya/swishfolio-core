<?php
/**
 * Brings an image into the media library from a URL.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Tools\Write;

use SwishfolioCore\Tools\Journal\MutationJournal;
use SwishfolioCore\Tools\ToolContext;
use SwishfolioCore\Tools\ToolError;
use SwishfolioCore\Tools\ToolException;
use SwishfolioCore\Tools\ToolInterface;

/**
 * Half the Swishfolio blocks take an imageId and until now there was no way to
 * produce one, so a generated page either had empty image fields or an invented
 * ID that renders as a broken image.
 *
 * Fetching a URL the model supplied is the one genuinely dangerous thing in the
 * tool layer, so three separate things constrain it. The scheme must be http or
 * https, checked here. The fetch goes through download_url(), which uses
 * wp_safe_remote_get() and therefore wp_http_validate_url() — that is what
 * refuses loopback, link-local and private addresses, so the site cannot be used
 * to probe its own network. And the file is admitted only if
 * wp_handle_sideload() recognises it as a type this site allows, which is
 * checked from the file's own bytes rather than from the URL's extension.
 *
 * It is write_draft, not write_published: an attachment is invisible to visitors
 * until a page references it, and prompting for approval on every image would
 * make the gate noise. Rollback trashes it.
 *
 * The sideload itself is injectable, because download_url() and
 * wp_handle_sideload() need a filesystem, an HTTP stack and wp-admin includes —
 * none of which a unit test should have to provide to assert that an image lands
 * in the library with its alt text.
 */
class UploadMediaTool extends AbstractWriteTool
{
    const ALT_META = '_wp_attachment_image_alt';

    /** @var callable|null Returns ['id' => int] for (url, filename, description). */
    private $sideloader;

    /**
     * @param MutationJournal $journal    Write gateway.
     * @param callable|null   $sideloader Sideload implementation, for tests.
     */
    public function __construct(MutationJournal $journal, $sideloader = null)
    {
        parent::__construct($journal);

        $this->sideloader = $sideloader;
    }

    public function getName()
    {
        return 'swishfolio/upload-media';
    }

    public function getTitle()
    {
        return __('Add an image', 'swishfolio-core');
    }

    public function getDescription()
    {
        return 'Fetch an image from a public https URL into this site\'s media library and return its '
            . 'attachment ID, which you can then set on any block attribute that takes an image. Always supply '
            . 'alt text describing what is actually in the picture. Check list-media first: reuse beats '
            . 'uploading the same image twice.';
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
            'required'             => ['url', 'alt'],
            'properties'           => [
                'url' => [
                    'type'        => 'string',
                    'maxLength'   => 2000,
                    'description' => 'Public http or https URL of the image.',
                ],
                'alt' => [
                    'type'        => 'string',
                    'maxLength'   => 300,
                    'description' => 'What is in the image, for screen readers. Describe the picture, not the page.',
                ],
                'title' => [
                    'type'      => 'string',
                    'maxLength' => 200,
                    'default'   => '',
                ],
            ],
        ];
    }

    public function authorize(array $input, ToolContext $context)
    {
        if (! current_user_can('upload_files')) {
            $this->forbid(__('You cannot add files to this site.', 'swishfolio-core'));
        }
    }

    public function execute(array $input, ToolContext $context)
    {
        $url = $this->validateUrl((string) $input['url']);
        $alt = (string) $input['alt'];

        $result = $this->sideload($url, isset($input['title']) ? (string) $input['title'] : '');

        $attachmentId = isset($result['id']) ? (int) $result['id'] : 0;

        if ($attachmentId <= 0) {
            throw ToolException::make(
                $this->getName(),
                ToolError::WRITE_FAILED,
                isset($result['error']) && '' !== $result['error']
                    ? (string) $result['error']
                    : __('The image could not be added to the media library.', 'swishfolio-core')
            );
        }

        $summary = sprintf(
            /* translators: %s: alt text. */
            __('Added the image "%s"', 'swishfolio-core'),
            $alt
        );

        $this->journal->recordCreated($context, $this->getName(), $attachmentId, $summary);
        $this->journal->updateMeta($context, $this->getName(), $attachmentId, self::ALT_META, $alt, $summary);

        return [
            'attachmentId' => $attachmentId,
            'alt'          => $alt,
            'url'          => function_exists('wp_get_attachment_url')
                ? (string) wp_get_attachment_url($attachmentId)
                : '',
        ];
    }

    /**
     * @param string $url URL from the model.
     *
     * @throws ToolException When the URL is not a fetchable http(s) address.
     *
     * @return string
     */
    private function validateUrl($url)
    {
        $url    = trim($url);
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if (! in_array($scheme, ['http', 'https'], true) || '' === (string) parse_url($url, PHP_URL_HOST)) {
            throw ToolException::make(
                $this->getName(),
                ToolError::SCHEMA_INVALID,
                __('Images must come from a public http or https URL.', 'swishfolio-core'),
                ['field' => 'url']
            );
        }

        return $url;
    }

    /** Ceiling for one fetched image, before the network's own limits. */
    const MAX_BYTES = 10485760;

    /**
     * Sideloads skip core's check_upload_size(), which only hooks browser
     * uploads, so the network's per-file limit and the site's quota are
     * applied here.
     *
     * @return int Largest file this fetch may store, in bytes.
     */
    private function maxBytes()
    {
        $max = self::MAX_BYTES;

        if (! is_multisite()) {
            return $max;
        }

        $max = min($max, (int) get_site_option('fileupload_maxk', 1500) * KB_IN_BYTES);

        if (! get_site_option('upload_space_check_disabled')) {
            $max = min($max, (int) get_upload_space_available());
        }

        return $max;
    }

    /**
     * @param string $url   Validated URL.
     * @param string $title Optional attachment title.
     *
     * @return array ['id' => int] or ['error' => string].
     */
    private function sideload($url, $title)
    {
        if ($this->sideloader !== null) {
            return (array) call_user_func($this->sideloader, $url, $title);
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $maxBytes = $this->maxBytes();

        if ($maxBytes <= 0) {
            return ['error' => __('This site has used all of its storage space.', 'swishfolio-core')];
        }

        // wp_safe_remote_get() refuses loopback, link-local and private
        // addresses. Unlike download_url() it is given a short timeout and a
        // size cap, so a prompt cannot make the server pull gigabytes, and a
        // failed fetch reports nothing about what answered (no port oracle).
        $temp     = wp_tempnam('swish-ai-media');
        $response = wp_safe_remote_get(
            $url,
            [
                'timeout'             => 15,
                'redirection'         => 3,
                'stream'              => true,
                'filename'            => $temp,
                'limit_response_size' => $maxBytes + 1,
            ]
        );

        if (is_wp_error($response) || 200 !== (int) wp_remote_retrieve_response_code($response)) {
            wp_delete_file($temp);

            return ['error' => __('That image could not be downloaded.', 'swishfolio-core')];
        }

        if ((int) filesize($temp) > $maxBytes) {
            wp_delete_file($temp);

            return ['error' => __('That image is too large for this site.', 'swishfolio-core')];
        }

        $file = [
            'name'     => wp_basename((string) parse_url($url, PHP_URL_PATH)),
            'tmp_name' => $temp,
        ];

        // Validates the type from the file's own bytes against this site's
        // allowed types, not from the URL's extension.
        $sideloaded = wp_handle_sideload($file, ['test_form' => false]);

        if (isset($sideloaded['error'])) {
            wp_delete_file($temp);

            return ['error' => (string) $sideloaded['error']];
        }

        // The tool adds images; anything else the site happens to allow
        // (PDFs, documents, archives) has no business arriving from a prompt.
        if (0 !== strpos((string) $sideloaded['type'], 'image/')) {
            wp_delete_file($sideloaded['file']);

            return ['error' => __('Only images can be added this way.', 'swishfolio-core')];
        }

        $attachmentId = wp_insert_attachment(
            [
                'post_mime_type' => $sideloaded['type'],
                'post_title'     => '' !== $title ? $title : wp_basename($sideloaded['file']),
                'post_status'    => 'inherit',
            ],
            $sideloaded['file'],
            0,
            true
        );

        if (is_wp_error($attachmentId)) {
            return ['error' => $attachmentId->get_error_message()];
        }

        wp_update_attachment_metadata(
            (int) $attachmentId,
            wp_generate_attachment_metadata((int) $attachmentId, $sideloaded['file'])
        );

        return ['id' => (int) $attachmentId];
    }
}
