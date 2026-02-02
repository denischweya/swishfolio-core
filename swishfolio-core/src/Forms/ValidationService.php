<?php
/**
 * Validation Service
 *
 * Handles form field validation.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Forms;

class ValidationService {
	/**
	 * Validation errors.
	 *
	 * @var array
	 */
	private array $errors = [];

	/**
	 * Validate a form submission.
	 *
	 * @param array $fields     Field definitions from block attributes.
	 * @param array $submission Submitted field values.
	 * @return bool True if valid, false otherwise.
	 */
	public function validate( array $fields, array $submission ): bool {
		$this->errors = [];

		foreach ( $fields as $field ) {
			$fieldId = $field['id'] ?? '';
			$value = $submission[ $fieldId ] ?? '';
			$type = $field['type'] ?? 'text';
			$required = $field['required'] ?? false;
			$label = $field['label'] ?? $fieldId;

			// Check required fields.
			if ( $required && $this->isEmpty( $value ) ) {
				$this->errors[ $fieldId ] = sprintf(
					/* translators: %s: field label */
					__( '%s is required.', 'swishfolio-core' ),
					$label
				);
				continue;
			}

			// Skip empty non-required fields.
			if ( $this->isEmpty( $value ) ) {
				continue;
			}

			// Type-specific validation.
			$this->validateByType( $type, $fieldId, $value, $label );
		}

		return empty( $this->errors );
	}

	/**
	 * Validate field by type.
	 *
	 * @param string $type    Field type.
	 * @param string $fieldId Field ID.
	 * @param mixed  $value   Field value.
	 * @param string $label   Field label.
	 * @return void
	 */
	private function validateByType( string $type, string $fieldId, $value, string $label ): void {
		switch ( $type ) {
			case 'email':
				if ( ! is_email( $value ) ) {
					$this->errors[ $fieldId ] = sprintf(
						/* translators: %s: field label */
						__( '%s must be a valid email address.', 'swishfolio-core' ),
						$label
					);
				}
				break;

			case 'tel':
				// Basic phone validation - allow digits, spaces, dashes, parentheses, plus.
				if ( ! preg_match( '/^[\d\s\-\(\)\+\.]+$/', $value ) ) {
					$this->errors[ $fieldId ] = sprintf(
						/* translators: %s: field label */
						__( '%s must be a valid phone number.', 'swishfolio-core' ),
						$label
					);
				}
				break;

			case 'text':
			case 'textarea':
				// Sanitize but no specific format validation.
				break;

			case 'select':
				// Value should match one of the options.
				break;

			case 'checkbox':
				// Boolean validation.
				break;
		}
	}

	/**
	 * Check if a value is empty.
	 *
	 * @param mixed $value The value to check.
	 * @return bool
	 */
	private function isEmpty( $value ): bool {
		if ( is_array( $value ) ) {
			return empty( $value );
		}
		return trim( (string) $value ) === '';
	}

	/**
	 * Get validation errors.
	 *
	 * @return array
	 */
	public function getErrors(): array {
		return $this->errors;
	}

	/**
	 * Sanitize form submission data.
	 *
	 * @param array $fields     Field definitions.
	 * @param array $submission Raw submission data.
	 * @return array Sanitized data.
	 */
	public function sanitize( array $fields, array $submission ): array {
		$sanitized = [];

		foreach ( $fields as $field ) {
			$fieldId = $field['id'] ?? '';
			$type = $field['type'] ?? 'text';
			$value = $submission[ $fieldId ] ?? '';

			$sanitized[ $fieldId ] = $this->sanitizeByType( $type, $value );
		}

		return $sanitized;
	}

	/**
	 * Sanitize value by field type.
	 *
	 * @param string $type  Field type.
	 * @param mixed  $value Field value.
	 * @return mixed Sanitized value.
	 */
	private function sanitizeByType( string $type, $value ) {
		switch ( $type ) {
			case 'email':
				return sanitize_email( $value );

			case 'tel':
				return preg_replace( '/[^\d\s\-\(\)\+\.]/', '', $value );

			case 'textarea':
				return sanitize_textarea_field( $value );

			case 'checkbox':
				return (bool) $value;

			case 'text':
			case 'select':
			default:
				return sanitize_text_field( $value );
		}
	}
}
