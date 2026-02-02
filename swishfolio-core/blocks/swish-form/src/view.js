/**
 * Swish Form Block - Frontend View Script
 *
 * Handles form submission via AJAX.
 *
 * @package SwishfolioCore
 */

( function() {
	'use strict';

	/**
	 * Initialize form handlers
	 */
	function init() {
		const forms = document.querySelectorAll( '.sfcore-swish-form__form' );

		forms.forEach( ( form ) => {
			form.addEventListener( 'submit', handleSubmit );
		} );
	}

	/**
	 * Handle form submission
	 *
	 * @param {Event} event Submit event
	 */
	async function handleSubmit( event ) {
		event.preventDefault();

		const form = event.target;
		const formId = form.dataset.formId;
		const formType = form.dataset.formType;
		const successMessage = form.dataset.successMessage;
		const errorMessage = form.dataset.errorMessage;

		const submitButton = form.querySelector( '.sfcore-swish-form__button' );
		const buttonText = submitButton.querySelector( '.sfcore-swish-form__button-text' );
		const buttonLoading = submitButton.querySelector( '.sfcore-swish-form__button-loading' );
		const messagesContainer = form.querySelector( '.sfcore-swish-form__messages' );

		// Clear previous messages
		messagesContainer.textContent = '';
		messagesContainer.className = 'sfcore-swish-form__messages';
		clearFieldErrors( form );

		// Check honeypot
		const honeypot = form.querySelector( 'input[name="hp_field"]' );
		if ( honeypot && honeypot.value ) {
			// Likely a bot, silently fail
			showMessage( messagesContainer, successMessage, 'success' );
			return;
		}

		// Validate required fields
		if ( ! form.checkValidity() ) {
			form.reportValidity();
			return;
		}

		// Disable button and show loading state
		submitButton.disabled = true;
		buttonText.style.display = 'none';
		buttonLoading.style.display = 'inline-flex';

		try {
			const formData = new FormData( form );
			const data = {
				formId,
				formType,
				nonce: formData.get( 'swish_form_nonce' ),
				fields: {},
			};

			// Collect field values
			for ( const [ key, value ] of formData.entries() ) {
				if ( key.startsWith( 'fields[' ) ) {
					const fieldId = key.match( /fields\[(.+?)\]/ )[ 1 ];
					data.fields[ fieldId ] = value;
				}
			}

			const response = await fetch( swishFormData.restUrl + 'swishfolio/v1/forms/submit', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': swishFormData.restNonce,
				},
				body: JSON.stringify( data ),
			} );

			const result = await response.json();

			if ( result.success ) {
				showMessage( messagesContainer, successMessage, 'success' );
				form.reset();
			} else {
				// Handle validation errors
				if ( result.errors ) {
					displayFieldErrors( form, result.errors );
				}
				showMessage( messagesContainer, result.message || errorMessage, 'error' );
			}
		} catch ( error ) {
			console.error( 'Form submission error:', error );
			showMessage( messagesContainer, errorMessage, 'error' );
		} finally {
			// Re-enable button
			submitButton.disabled = false;
			buttonText.style.display = 'inline';
			buttonLoading.style.display = 'none';
		}
	}

	/**
	 * Display a message
	 *
	 * @param {Element} container Message container
	 * @param {string}  message   Message text
	 * @param {string}  type      Message type (success/error)
	 */
	function showMessage( container, message, type ) {
		container.textContent = message;
		container.className = `sfcore-swish-form__messages sfcore-swish-form__messages--${ type }`;

		// Scroll to message
		container.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
	}

	/**
	 * Display field-level errors
	 *
	 * @param {Element} form   Form element
	 * @param {Object}  errors Errors object { fieldId: message }
	 */
	function displayFieldErrors( form, errors ) {
		for ( const [ fieldId, message ] of Object.entries( errors ) ) {
			const field = form.querySelector( `[name="fields[${ fieldId }]"]` );
			if ( field ) {
				const fieldContainer = field.closest( '.sfcore-swish-form__field' );
				const errorContainer = fieldContainer?.querySelector( '.sfcore-swish-form__field-error' );
				if ( errorContainer ) {
					errorContainer.textContent = message;
				}
				field.classList.add( 'has-error' );
			}
		}
	}

	/**
	 * Clear all field errors
	 *
	 * @param {Element} form Form element
	 */
	function clearFieldErrors( form ) {
		const errorContainers = form.querySelectorAll( '.sfcore-swish-form__field-error' );
		errorContainers.forEach( ( container ) => {
			container.textContent = '';
		} );

		const errorFields = form.querySelectorAll( '.has-error' );
		errorFields.forEach( ( field ) => {
			field.classList.remove( 'has-error' );
		} );
	}

	// Initialize when DOM is ready
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
