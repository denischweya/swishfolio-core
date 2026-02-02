<?php
/**
 * Columns Block Extension
 *
 * Extends core/columns and core/column blocks with vertical alignment options.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Extensions;

class ColumnsExtension {

	/**
	 * Initialize the extension.
	 */
	public function __construct() {
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_assets' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_styles' ] );
		add_action( 'enqueue_block_assets', [ $this, 'enqueue_frontend_styles' ] );
		add_filter( 'render_block', [ $this, 'render_block' ], 10, 2 );
	}

	/**
	 * Enqueue editor assets.
	 */
	public function enqueue_editor_assets() {
		$asset_file = SFCORE_PLUGIN_DIR . 'assets/js/columns-extension.asset.php';

		if ( file_exists( $asset_file ) ) {
			$asset = require $asset_file;
		} else {
			$asset = [
				'dependencies' => [ 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-compose', 'wp-hooks' ],
				'version'      => SFCORE_VERSION,
			];
		}

		wp_enqueue_script(
			'sfcore-columns-extension',
			SFCORE_PLUGIN_URL . 'assets/js/columns-extension.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_enqueue_style(
			'sfcore-columns-extension-editor',
			SFCORE_PLUGIN_URL . 'assets/css/columns-extension-editor.css',
			[],
			SFCORE_VERSION
		);
	}

	/**
	 * Enqueue frontend styles.
	 */
	public function enqueue_frontend_styles() {
		wp_enqueue_style(
			'sfcore-columns-extension',
			SFCORE_PLUGIN_URL . 'assets/css/columns-extension.css',
			[],
			SFCORE_VERSION
		);
	}

	/**
	 * Filter block rendering to add alignment classes.
	 *
	 * @param string $block_content The block content.
	 * @param array  $block         The block data.
	 * @return string
	 */
	public function render_block( $block_content, $block ) {
		if ( ! in_array( $block['blockName'], [ 'core/columns', 'core/column' ], true ) ) {
			return $block_content;
		}

		$vertical_alignment = $block['attrs']['sfcoreVerticalAlignment'] ?? '';

		if ( empty( $vertical_alignment ) ) {
			return $block_content;
		}

		// Add the alignment class to the block.
		$class_to_add = 'sfcore-vertical-align-' . esc_attr( $vertical_alignment );

		// Use regex to add class to the first element.
		$block_content = preg_replace(
			'/^(<\w+)(\s+class=["\'])/',
			'$1$2' . $class_to_add . ' ',
			$block_content,
			1
		);

		// If no class attribute exists, add one.
		if ( strpos( $block_content, $class_to_add ) === false ) {
			$block_content = preg_replace(
				'/^(<\w+)/',
				'$1 class="' . $class_to_add . '"',
				$block_content,
				1
			);
		}

		return $block_content;
	}
}
