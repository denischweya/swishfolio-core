<?php
/**
 * SVG Upload Support
 *
 * Enables SVG uploads with basic sanitization and admin preview fixes.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Services;

class SvgSupport {

	/**
	 * Initialize SVG support.
	 */
	public function __construct() {
		// Allow SVG uploads.
		add_filter( 'upload_mimes', [ $this, 'allow_svg_upload' ] );
		add_filter( 'wp_check_filetype_and_ext', [ $this, 'fix_svg_mime_type' ], 10, 4 );

		// Sanitize SVG on upload.
		add_filter( 'wp_handle_upload_prefilter', [ $this, 'sanitize_svg' ] );

		// Fix admin display issues.
		add_filter( 'wp_prepare_attachment_for_js', [ $this, 'fix_admin_preview' ], 10, 3 );
		add_filter( 'wp_get_attachment_image_src', [ $this, 'fix_svg_dimensions' ], 10, 4 );
		add_filter( 'wp_generate_attachment_metadata', [ $this, 'generate_svg_metadata' ], 10, 2 );

		// Admin styles for SVG preview.
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_styles' ] );
	}

	/**
	 * Allow SVG file uploads.
	 *
	 * @param array $mimes Allowed mime types.
	 * @return array
	 */
	public function allow_svg_upload( $mimes ) {
		if ( current_user_can( 'upload_files' ) ) {
			$mimes['svg']  = 'image/svg+xml';
			$mimes['svgz'] = 'image/svg+xml';
		}
		return $mimes;
	}

	/**
	 * Fix SVG mime type detection.
	 *
	 * @param array  $data     File data.
	 * @param string $file     File path.
	 * @param string $filename File name.
	 * @param array  $mimes    Allowed mimes.
	 * @return array
	 */
	public function fix_svg_mime_type( $data, $file, $filename, $mimes ) {
		$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

		if ( 'svg' === $ext ) {
			$data['type'] = 'image/svg+xml';
			$data['ext']  = 'svg';
		} elseif ( 'svgz' === $ext ) {
			$data['type'] = 'image/svg+xml';
			$data['ext']  = 'svgz';
		}

		return $data;
	}

	/**
	 * Sanitize SVG file on upload.
	 *
	 * @param array $file File data.
	 * @return array
	 */
	public function sanitize_svg( $file ) {
		if ( ! isset( $file['tmp_name'] ) || ! isset( $file['name'] ) ) {
			return $file;
		}

		$ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );

		if ( 'svg' !== $ext && 'svgz' !== $ext ) {
			return $file;
		}

		if ( ! current_user_can( 'upload_files' ) ) {
			$file['error'] = __( 'Sorry, you are not allowed to upload SVG files.', 'swishfolio-core' );
			return $file;
		}

		$content = file_get_contents( $file['tmp_name'] );

		// Check for gzipped SVG.
		if ( 'svgz' === $ext || $this->is_gzipped( $content ) ) {
			$content = @gzdecode( $content );
			if ( false === $content ) {
				$file['error'] = __( 'Could not decode compressed SVG file.', 'swishfolio-core' );
				return $file;
			}
		}

		// Basic sanitization - remove potentially dangerous elements and attributes.
		$sanitized = $this->sanitize_svg_content( $content );

		if ( false === $sanitized ) {
			$file['error'] = __( 'SVG file could not be sanitized for security reasons.', 'swishfolio-core' );
			return $file;
		}

		// Re-compress if it was gzipped.
		if ( 'svgz' === $ext ) {
			$sanitized = gzencode( $sanitized );
		}

		file_put_contents( $file['tmp_name'], $sanitized );

		return $file;
	}

	/**
	 * Sanitize SVG content.
	 *
	 * @param string $content SVG content.
	 * @return string|false
	 */
	private function sanitize_svg_content( $content ) {
		// Remove XML declaration processing instructions that could be harmful.
		$content = preg_replace( '/<\?xml.*\?>/i', '', $content );

		// Remove DOCTYPE declarations.
		$content = preg_replace( '/<!DOCTYPE[^>]*>/i', '', $content );

		// Remove script tags.
		$content = preg_replace( '/<script[^>]*>.*?<\/script>/is', '', $content );

		// Remove on* event attributes.
		$content = preg_replace( '/\s+on\w+\s*=\s*["\'][^"\']*["\']/i', '', $content );
		$content = preg_replace( '/\s+on\w+\s*=\s*[^\s>]+/i', '', $content );

		// Remove javascript: URLs.
		$content = preg_replace( '/href\s*=\s*["\']?\s*javascript:[^"\'>\s]*/i', '', $content );
		$content = preg_replace( '/xlink:href\s*=\s*["\']?\s*javascript:[^"\'>\s]*/i', '', $content );

		// Remove data: URLs (can contain scripts).
		$content = preg_replace( '/href\s*=\s*["\']?\s*data:[^"\'>\s]*/i', '', $content );
		$content = preg_replace( '/xlink:href\s*=\s*["\']?\s*data:[^"\'>\s]*/i', '', $content );

		// Remove foreignObject elements (can embed HTML).
		$content = preg_replace( '/<foreignObject[^>]*>.*?<\/foreignObject>/is', '', $content );

		// Remove set and animate elements with dangerous attributes.
		$content = preg_replace( '/<set[^>]*attributeName\s*=\s*["\']?on\w+[^>]*>/i', '', $content );

		// Check if it's still valid SVG.
		if ( stripos( $content, '<svg' ) === false ) {
			return false;
		}

		return trim( $content );
	}

	/**
	 * Check if content is gzipped.
	 *
	 * @param string $content Content to check.
	 * @return bool
	 */
	private function is_gzipped( $content ) {
		return 0 === strpos( $content, "\x1f\x8b\x08" );
	}

	/**
	 * Fix admin preview for SVGs.
	 *
	 * @param array   $response   Attachment response.
	 * @param WP_Post $attachment Attachment object.
	 * @param array   $meta       Attachment metadata.
	 * @return array
	 */
	public function fix_admin_preview( $response, $attachment, $meta ) {
		if ( 'image/svg+xml' !== $response['mime'] ) {
			return $response;
		}

		$dimensions = $this->get_svg_dimensions( $attachment->ID );

		if ( $dimensions ) {
			$response['width']  = $dimensions['width'];
			$response['height'] = $dimensions['height'];
		}

		$response['sizes'] = [
			'full' => [
				'url'         => $response['url'],
				'width'       => $dimensions ? $dimensions['width'] : 100,
				'height'      => $dimensions ? $dimensions['height'] : 100,
				'orientation' => 'portrait',
			],
		];

		$response['icon'] = $response['url'];

		return $response;
	}

	/**
	 * Fix SVG dimensions in image src.
	 *
	 * @param array|false $image         Image data.
	 * @param int         $attachment_id Attachment ID.
	 * @param string      $size          Image size.
	 * @param bool        $icon          Whether it's an icon.
	 * @return array|false
	 */
	public function fix_svg_dimensions( $image, $attachment_id, $size, $icon ) {
		if ( 'image/svg+xml' !== get_post_mime_type( $attachment_id ) ) {
			return $image;
		}

		if ( ! $image ) {
			return $image;
		}

		$dimensions = $this->get_svg_dimensions( $attachment_id );

		if ( $dimensions ) {
			$image[1] = $dimensions['width'];
			$image[2] = $dimensions['height'];
		} else {
			$image[1] = 100;
			$image[2] = 100;
		}

		return $image;
	}

	/**
	 * Generate metadata for SVG attachments.
	 *
	 * @param array $metadata      Attachment metadata.
	 * @param int   $attachment_id Attachment ID.
	 * @return array
	 */
	public function generate_svg_metadata( $metadata, $attachment_id ) {
		if ( 'image/svg+xml' !== get_post_mime_type( $attachment_id ) ) {
			return $metadata;
		}

		$dimensions = $this->get_svg_dimensions( $attachment_id );

		if ( $dimensions ) {
			$metadata['width']  = $dimensions['width'];
			$metadata['height'] = $dimensions['height'];
		}

		return $metadata;
	}

	/**
	 * Get SVG dimensions.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array|false
	 */
	private function get_svg_dimensions( $attachment_id ) {
		$svg_path = get_attached_file( $attachment_id );

		if ( ! $svg_path || ! file_exists( $svg_path ) ) {
			return false;
		}

		$content = file_get_contents( $svg_path );

		// Handle gzipped SVGs.
		if ( $this->is_gzipped( $content ) ) {
			$content = @gzdecode( $content );
			if ( false === $content ) {
				return false;
			}
		}

		// Try to parse with SimpleXML.
		libxml_use_internal_errors( true );
		$svg = @simplexml_load_string( $content );
		libxml_clear_errors();

		if ( ! $svg ) {
			return false;
		}

		$attributes = $svg->attributes();
		$width      = 0;
		$height     = 0;

		// Try viewBox first.
		if ( isset( $attributes->viewBox ) ) {
			$viewbox = explode( ' ', (string) $attributes->viewBox );
			if ( count( $viewbox ) === 4 ) {
				$width  = floatval( $viewbox[2] );
				$height = floatval( $viewbox[3] );
			}
		}

		// Fall back to width/height attributes.
		if ( ! $width && ! $height ) {
			if ( isset( $attributes->width ) && isset( $attributes->height ) ) {
				$width  = floatval( $attributes->width );
				$height = floatval( $attributes->height );
			}
		}

		if ( ! $width || ! $height ) {
			return false;
		}

		return [
			'width'       => $width,
			'height'      => $height,
			'orientation' => $width > $height ? 'landscape' : 'portrait',
		];
	}

	/**
	 * Add admin styles for SVG display.
	 */
	public function admin_styles() {
		$css = '
			.attachment-266x266.size-266x266[src$=".svg"],
			.attachment-150x150.size-150x150[src$=".svg"],
			img[src$=".svg"].attachment-post-thumbnail {
				width: 100%;
				height: auto;
			}
			.media-icon img[src$=".svg"] {
				width: 100%;
				height: auto;
				max-width: 48px;
			}
		';
		wp_add_inline_style( 'wp-admin', $css );
	}
}
