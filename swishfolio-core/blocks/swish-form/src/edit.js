/**
 * Swish Form Block - Edit Component
 *
 * @package SwishfolioCore
 */

import { __ } from '@wordpress/i18n';
import { useEffect } from '@wordpress/element';
import {
	useBlockProps,
	InspectorControls,
} from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
	Button,
	Card,
	CardBody,
	CardHeader,
	Flex,
	FlexItem,
	FlexBlock,
	Icon,
	__experimentalHStack as HStack,
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { plus, trash, dragHandle } from '@wordpress/icons';

/**
 * Field type options
 */
const FIELD_TYPES = [
	{ label: __( 'Text', 'swishfolio-core' ), value: 'text' },
	{ label: __( 'Email', 'swishfolio-core' ), value: 'email' },
	{ label: __( 'Phone', 'swishfolio-core' ), value: 'tel' },
	{ label: __( 'Textarea', 'swishfolio-core' ), value: 'textarea' },
	{ label: __( 'Select', 'swishfolio-core' ), value: 'select' },
	{ label: __( 'Checkbox', 'swishfolio-core' ), value: 'checkbox' },
];

/**
 * Field width options
 */
const FIELD_WIDTHS = [
	{ label: __( 'Full Width', 'swishfolio-core' ), value: 'full' },
	{ label: __( 'Half Width', 'swishfolio-core' ), value: 'half' },
];

/**
 * Generate unique field ID
 */
const generateFieldId = () => {
	return 'field_' + Math.random().toString( 36 ).substr( 2, 9 );
};

/**
 * Default fields for contact form
 */
const DEFAULT_CONTACT_FIELDS = [
	{ id: generateFieldId(), type: 'text', label: __( 'Name', 'swishfolio-core' ), placeholder: '', required: true, width: 'full' },
	{ id: generateFieldId(), type: 'email', label: __( 'Email', 'swishfolio-core' ), placeholder: '', required: true, width: 'half' },
	{ id: generateFieldId(), type: 'tel', label: __( 'Phone', 'swishfolio-core' ), placeholder: '', required: false, width: 'half' },
	{ id: generateFieldId(), type: 'textarea', label: __( 'Message', 'swishfolio-core' ), placeholder: '', required: true, width: 'full' },
];

/**
 * Default fields for subscription form
 */
const DEFAULT_SUBSCRIPTION_FIELDS = [
	{ id: generateFieldId(), type: 'text', label: __( 'Name', 'swishfolio-core' ), placeholder: '', required: false, width: 'half' },
	{ id: generateFieldId(), type: 'email', label: __( 'Email', 'swishfolio-core' ), placeholder: '', required: true, width: 'half' },
];

/**
 * Edit Component
 */
export default function Edit( { attributes, setAttributes, clientId } ) {
	const {
		formId,
		formType,
		fields,
		submitButtonText,
		successMessage,
		errorMessage,
		recipientEmail,
		emailSubject,
		espProvider,
		espListId,
		enableHoneypot,
	} = attributes;

	// Generate form ID on first load.
	useEffect( () => {
		if ( ! formId ) {
			setAttributes( { formId: 'swish-form-' + clientId.substr( 0, 8 ) } );
		}
	}, [ formId, clientId, setAttributes ] );

	// Set default fields when form type changes.
	useEffect( () => {
		if ( fields.length === 0 ) {
			const defaultFields = formType === 'subscription' ? DEFAULT_SUBSCRIPTION_FIELDS : DEFAULT_CONTACT_FIELDS;
			setAttributes( { fields: defaultFields.map( f => ( { ...f, id: generateFieldId() } ) ) } );
		}
	}, [] );

	const blockProps = useBlockProps( {
		className: `sfcore-swish-form sfcore-swish-form--${ formType }`,
	} );

	/**
	 * Add a new field
	 */
	const addField = () => {
		const newField = {
			id: generateFieldId(),
			type: 'text',
			label: __( 'New Field', 'swishfolio-core' ),
			placeholder: '',
			required: false,
			width: 'full',
			options: [],
		};
		setAttributes( { fields: [ ...fields, newField ] } );
	};

	/**
	 * Update a field
	 */
	const updateField = ( index, key, value ) => {
		const updatedFields = [ ...fields ];
		updatedFields[ index ] = { ...updatedFields[ index ], [ key ]: value };
		setAttributes( { fields: updatedFields } );
	};

	/**
	 * Remove a field
	 */
	const removeField = ( index ) => {
		const updatedFields = fields.filter( ( _, i ) => i !== index );
		setAttributes( { fields: updatedFields } );
	};

	/**
	 * Move field up
	 */
	const moveFieldUp = ( index ) => {
		if ( index === 0 ) return;
		const updatedFields = [ ...fields ];
		[ updatedFields[ index - 1 ], updatedFields[ index ] ] = [ updatedFields[ index ], updatedFields[ index - 1 ] ];
		setAttributes( { fields: updatedFields } );
	};

	/**
	 * Move field down
	 */
	const moveFieldDown = ( index ) => {
		if ( index === fields.length - 1 ) return;
		const updatedFields = [ ...fields ];
		[ updatedFields[ index ], updatedFields[ index + 1 ] ] = [ updatedFields[ index + 1 ], updatedFields[ index ] ];
		setAttributes( { fields: updatedFields } );
	};

	/**
	 * Handle form type change
	 */
	const handleFormTypeChange = ( newType ) => {
		const defaultFields = newType === 'subscription' ? DEFAULT_SUBSCRIPTION_FIELDS : DEFAULT_CONTACT_FIELDS;
		setAttributes( {
			formType: newType,
			fields: defaultFields.map( f => ( { ...f, id: generateFieldId() } ) ),
		} );
	};

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Form Settings', 'swishfolio-core' ) }>
					<SelectControl
						label={ __( 'Form Type', 'swishfolio-core' ) }
						value={ formType }
						options={ [
							{ label: __( 'Contact Form', 'swishfolio-core' ), value: 'contact' },
							{ label: __( 'Subscription Form', 'swishfolio-core' ), value: 'subscription' },
						] }
						onChange={ handleFormTypeChange }
					/>

					<TextControl
						label={ __( 'Submit Button Text', 'swishfolio-core' ) }
						value={ submitButtonText }
						onChange={ ( value ) => setAttributes( { submitButtonText: value } ) }
					/>

					<TextareaControl
						label={ __( 'Success Message', 'swishfolio-core' ) }
						value={ successMessage }
						onChange={ ( value ) => setAttributes( { successMessage: value } ) }
					/>

					<TextareaControl
						label={ __( 'Error Message', 'swishfolio-core' ) }
						value={ errorMessage }
						onChange={ ( value ) => setAttributes( { errorMessage: value } ) }
					/>

					<ToggleControl
						label={ __( 'Enable Honeypot Spam Protection', 'swishfolio-core' ) }
						checked={ enableHoneypot }
						onChange={ ( value ) => setAttributes( { enableHoneypot: value } ) }
					/>
				</PanelBody>

				{ formType === 'contact' && (
					<PanelBody title={ __( 'Email Settings', 'swishfolio-core' ) }>
						<TextControl
							label={ __( 'Recipient Email', 'swishfolio-core' ) }
							help={ __( 'Leave empty to use default from settings.', 'swishfolio-core' ) }
							value={ recipientEmail }
							onChange={ ( value ) => setAttributes( { recipientEmail: value } ) }
							type="email"
						/>

						<TextControl
							label={ __( 'Email Subject', 'swishfolio-core' ) }
							value={ emailSubject }
							onChange={ ( value ) => setAttributes( { emailSubject: value } ) }
						/>
					</PanelBody>
				) }

				{ formType === 'subscription' && (
					<PanelBody title={ __( 'Email Provider', 'swishfolio-core' ) }>
						<SelectControl
							label={ __( 'Provider', 'swishfolio-core' ) }
							value={ espProvider }
							options={ [
								{ label: __( 'Use Default (from settings)', 'swishfolio-core' ), value: 'none' },
								{ label: 'Mailchimp', value: 'mailchimp' },
								{ label: 'ConvertKit', value: 'convertkit' },
								{ label: 'Klaviyo', value: 'klaviyo' },
								{ label: 'ActiveCampaign', value: 'activecampaign' },
								{ label: 'Brevo', value: 'brevo' },
							] }
							onChange={ ( value ) => setAttributes( { espProvider: value } ) }
						/>

						{ espProvider !== 'none' && (
							<TextControl
								label={ __( 'List/Audience ID', 'swishfolio-core' ) }
								help={ __( 'Leave empty to use default from settings.', 'swishfolio-core' ) }
								value={ espListId }
								onChange={ ( value ) => setAttributes( { espListId: value } ) }
							/>
						) }
					</PanelBody>
				) }

				<PanelBody title={ __( 'Form Fields', 'swishfolio-core' ) } initialOpen={ true }>
					<VStack spacing={ 4 }>
						{ fields.map( ( field, index ) => (
							<Card key={ field.id } size="small">
								<CardHeader>
									<Flex>
										<FlexBlock>
											<strong>{ field.label || __( 'Untitled Field', 'swishfolio-core' ) }</strong>
											<span style={ { marginLeft: '8px', opacity: 0.6, fontSize: '12px' } }>
												({ field.type })
											</span>
										</FlexBlock>
										<FlexItem>
											<HStack spacing={ 1 }>
												<Button
													icon="arrow-up"
													label={ __( 'Move Up', 'swishfolio-core' ) }
													onClick={ () => moveFieldUp( index ) }
													disabled={ index === 0 }
													size="small"
												/>
												<Button
													icon="arrow-down"
													label={ __( 'Move Down', 'swishfolio-core' ) }
													onClick={ () => moveFieldDown( index ) }
													disabled={ index === fields.length - 1 }
													size="small"
												/>
												<Button
													icon={ trash }
													label={ __( 'Remove', 'swishfolio-core' ) }
													onClick={ () => removeField( index ) }
													isDestructive
													size="small"
												/>
											</HStack>
										</FlexItem>
									</Flex>
								</CardHeader>
								<CardBody>
									<VStack spacing={ 3 }>
										<SelectControl
											label={ __( 'Field Type', 'swishfolio-core' ) }
											value={ field.type }
											options={ FIELD_TYPES }
											onChange={ ( value ) => updateField( index, 'type', value ) }
											__nextHasNoMarginBottom
										/>

										<TextControl
											label={ __( 'Label', 'swishfolio-core' ) }
											value={ field.label }
											onChange={ ( value ) => updateField( index, 'label', value ) }
											__nextHasNoMarginBottom
										/>

										<TextControl
											label={ __( 'Placeholder', 'swishfolio-core' ) }
											value={ field.placeholder }
											onChange={ ( value ) => updateField( index, 'placeholder', value ) }
											__nextHasNoMarginBottom
										/>

										<HStack>
											<SelectControl
												label={ __( 'Width', 'swishfolio-core' ) }
												value={ field.width }
												options={ FIELD_WIDTHS }
												onChange={ ( value ) => updateField( index, 'width', value ) }
												__nextHasNoMarginBottom
											/>
											<ToggleControl
												label={ __( 'Required', 'swishfolio-core' ) }
												checked={ field.required }
												onChange={ ( value ) => updateField( index, 'required', value ) }
												__nextHasNoMarginBottom
											/>
										</HStack>

										{ field.type === 'select' && (
											<TextareaControl
												label={ __( 'Options (one per line)', 'swishfolio-core' ) }
												value={ ( field.options || [] ).join( '\n' ) }
												onChange={ ( value ) => updateField( index, 'options', value.split( '\n' ).filter( Boolean ) ) }
												help={ __( 'Enter each option on a new line.', 'swishfolio-core' ) }
												__nextHasNoMarginBottom
											/>
										) }
									</VStack>
								</CardBody>
							</Card>
						) ) }

						<Button
							variant="secondary"
							onClick={ addField }
							icon={ plus }
							style={ { width: '100%', justifyContent: 'center' } }
						>
							{ __( 'Add Field', 'swishfolio-core' ) }
						</Button>
					</VStack>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				<div className="sfcore-swish-form__preview">
					<div className="sfcore-swish-form__preview-header">
						<span className="sfcore-swish-form__preview-type">
							{ formType === 'contact' ? __( 'Contact Form', 'swishfolio-core' ) : __( 'Subscription Form', 'swishfolio-core' ) }
						</span>
					</div>

					<div className="sfcore-swish-form__preview-fields">
						{ fields.map( ( field ) => (
							<div
								key={ field.id }
								className={ `sfcore-swish-form__preview-field sfcore-swish-form__preview-field--${ field.width }` }
							>
								<label className="sfcore-swish-form__preview-label">
									{ field.label }
									{ field.required && <span className="sfcore-swish-form__preview-required">*</span> }
								</label>
								{ field.type === 'textarea' ? (
									<textarea
										className="sfcore-swish-form__preview-textarea"
										placeholder={ field.placeholder }
										disabled
									/>
								) : field.type === 'select' ? (
									<select className="sfcore-swish-form__preview-select" disabled>
										<option>{ field.placeholder || __( 'Select...', 'swishfolio-core' ) }</option>
									</select>
								) : field.type === 'checkbox' ? (
									<label className="sfcore-swish-form__preview-checkbox-label">
										<input type="checkbox" disabled />
										<span>{ field.label }</span>
									</label>
								) : (
									<input
										type={ field.type }
										className="sfcore-swish-form__preview-input"
										placeholder={ field.placeholder }
										disabled
									/>
								) }
							</div>
						) ) }
					</div>

					<div className="sfcore-swish-form__preview-submit">
						<button className="sfcore-swish-form__preview-button" disabled>
							{ submitButtonText }
						</button>
					</div>
				</div>
			</div>
		</>
	);
}
