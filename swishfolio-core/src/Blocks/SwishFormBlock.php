<?php
/**
 * Swish Form Block
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Blocks;

use SwishfolioCore\Abstracts\AbstractBlock;
use SwishfolioCore\Contracts\RenderableInterface;

class SwishFormBlock extends AbstractBlock implements RenderableInterface {
	/**
	 * Get the block name.
	 *
	 * @return string
	 */
	public function getName(): string {
		return 'swish-form';
	}

	/**
	 * Render the block on the frontend.
	 *
	 * @param array  $attributes Block attributes.
	 * @param string $content    Block content.
	 * @return string
	 */
	public function render( array $attributes, string $content ): string {
		$formId = $attributes['formId'] ?? uniqid( 'swish-form-' );
		$formType = $attributes['formType'] ?? 'contact';
		$fields = $attributes['fields'] ?? [];
		$submitButtonText = $attributes['submitButtonText'] ?? __( 'Submit', 'swishfolio-core' );
		$successMessage = $attributes['successMessage'] ?? __( 'Thank you for your submission!', 'swishfolio-core' );
		$errorMessage = $attributes['errorMessage'] ?? __( 'Something went wrong. Please try again.', 'swishfolio-core' );
		$enableHoneypot = $attributes['enableHoneypot'] ?? true;

		// Enqueue frontend script.
		wp_enqueue_script( 'swish-form-view' );

		$wrapperAttributes = $this->getWrapperAttributes( $attributes, [
			'class' => 'sfcore-swish-form--' . esc_attr( $formType ),
		] );

		ob_start();
		?>
		<div <?php echo $wrapperAttributes; ?>>
			<form
				class="sfcore-swish-form__form"
				id="<?php echo esc_attr( $formId ); ?>"
				data-form-id="<?php echo esc_attr( $formId ); ?>"
				data-form-type="<?php echo esc_attr( $formType ); ?>"
				data-success-message="<?php echo esc_attr( $successMessage ); ?>"
				data-error-message="<?php echo esc_attr( $errorMessage ); ?>"
			>
				<?php wp_nonce_field( 'swish_form_submit_' . $formId, 'swish_form_nonce' ); ?>
				<input type="hidden" name="form_id" value="<?php echo esc_attr( $formId ); ?>">
				<input type="hidden" name="form_type" value="<?php echo esc_attr( $formType ); ?>">

				<?php if ( $enableHoneypot ) : ?>
					<div class="sfcore-swish-form__honeypot" aria-hidden="true">
						<label for="<?php echo esc_attr( $formId ); ?>-hp">
							<?php esc_html_e( 'Leave this field empty', 'swishfolio-core' ); ?>
						</label>
						<input type="text" name="hp_field" id="<?php echo esc_attr( $formId ); ?>-hp" tabindex="-1" autocomplete="off">
					</div>
				<?php endif; ?>

				<div class="sfcore-swish-form__fields">
					<?php foreach ( $fields as $field ) : ?>
						<?php $this->renderField( $field, $formId ); ?>
					<?php endforeach; ?>
				</div>

				<div class="sfcore-swish-form__submit">
					<button type="submit" class="sfcore-swish-form__button">
						<span class="sfcore-swish-form__button-text"><?php echo esc_html( $submitButtonText ); ?></span>
						<span class="sfcore-swish-form__button-loading" style="display: none;">
							<?php esc_html_e( 'Sending...', 'swishfolio-core' ); ?>
						</span>
					</button>
				</div>

				<div class="sfcore-swish-form__messages" aria-live="polite"></div>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render a form field.
	 *
	 * @param array  $field  Field configuration.
	 * @param string $formId Form ID.
	 * @return void
	 */
	private function renderField( array $field, string $formId ): void {
		$fieldId = $field['id'] ?? '';
		$type = $field['type'] ?? 'text';
		$label = $field['label'] ?? '';
		$placeholder = $field['placeholder'] ?? '';
		$required = $field['required'] ?? false;
		$width = $field['width'] ?? 'full';
		$options = $field['options'] ?? [];

		$inputId = $formId . '-' . $fieldId;
		$inputName = 'fields[' . $fieldId . ']';
		$widthClass = 'sfcore-swish-form__field--' . esc_attr( $width );
		?>
		<div class="sfcore-swish-form__field <?php echo esc_attr( $widthClass ); ?>">
			<?php if ( $label && $type !== 'checkbox' ) : ?>
				<label for="<?php echo esc_attr( $inputId ); ?>" class="sfcore-swish-form__label">
					<?php echo esc_html( $label ); ?>
					<?php if ( $required ) : ?>
						<span class="sfcore-swish-form__required" aria-hidden="true">*</span>
					<?php endif; ?>
				</label>
			<?php endif; ?>

			<?php
			switch ( $type ) {
				case 'textarea':
					?>
					<textarea
						id="<?php echo esc_attr( $inputId ); ?>"
						name="<?php echo esc_attr( $inputName ); ?>"
						class="sfcore-swish-form__textarea"
						placeholder="<?php echo esc_attr( $placeholder ); ?>"
						<?php echo $required ? 'required' : ''; ?>
						rows="5"
					></textarea>
					<?php
					break;

				case 'select':
					?>
					<select
						id="<?php echo esc_attr( $inputId ); ?>"
						name="<?php echo esc_attr( $inputName ); ?>"
						class="sfcore-swish-form__select"
						<?php echo $required ? 'required' : ''; ?>
					>
						<?php if ( $placeholder ) : ?>
							<option value=""><?php echo esc_html( $placeholder ); ?></option>
						<?php endif; ?>
						<?php foreach ( $options as $option ) : ?>
							<option value="<?php echo esc_attr( $option['value'] ?? $option ); ?>">
								<?php echo esc_html( $option['label'] ?? $option ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<?php
					break;

				case 'checkbox':
					?>
					<label class="sfcore-swish-form__checkbox-label">
						<input
							type="checkbox"
							id="<?php echo esc_attr( $inputId ); ?>"
							name="<?php echo esc_attr( $inputName ); ?>"
							class="sfcore-swish-form__checkbox"
							value="1"
							<?php echo $required ? 'required' : ''; ?>
						>
						<span class="sfcore-swish-form__checkbox-text">
							<?php echo esc_html( $label ); ?>
							<?php if ( $required ) : ?>
								<span class="sfcore-swish-form__required" aria-hidden="true">*</span>
							<?php endif; ?>
						</span>
					</label>
					<?php
					break;

				default:
					// text, email, tel
					?>
					<input
						type="<?php echo esc_attr( $type ); ?>"
						id="<?php echo esc_attr( $inputId ); ?>"
						name="<?php echo esc_attr( $inputName ); ?>"
						class="sfcore-swish-form__input"
						placeholder="<?php echo esc_attr( $placeholder ); ?>"
						<?php echo $required ? 'required' : ''; ?>
					>
					<?php
					break;
			}
			?>

			<div class="sfcore-swish-form__field-error" aria-live="polite"></div>
		</div>
		<?php
	}
}
