/**
 * Swish Slide - Edit Component.
 *
 * Inline-editable title/subtitle over the slide image. Image and the two
 * CTA links are configured in the inspector (General tab). When no image is
 * set, the slide shows a grey placeholder with an "Add Image" button.
 */

import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	RichText,
	InspectorControls,
	MediaUpload,
	MediaUploadCheck,
	URLInput,
} from '@wordpress/block-editor';
import {
	PanelBody,
	Button,
	ButtonGroup,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import InspectorTabs, {
	useInspectorTabs,
} from '../../shared/components/inspector-tabs';

export default function Edit( { attributes, setAttributes } ) {
	const [ activeTab, setActiveTab ] = useInspectorTabs();

	const {
		image,
		title,
		subtitle,
		cta1Text,
		cta1Url,
		cta1NewTab,
		cta2Text,
		cta2Url,
		cta2NewTab,
	} = attributes;

	const blockProps = useBlockProps( {
		className: 'sfcore-swish-slide',
	} );

	const onSelectImage = ( media ) =>
		setAttributes( {
			image: {
				url: media.url,
				alt: media.alt,
				id: media.id,
			},
		} );

	return (
		<>
			<InspectorControls>
				<InspectorTabs
					activeTab={ activeTab }
					setActiveTab={ setActiveTab }
					allowedTabs={ [ 'general' ] }
				/>

				{ activeTab === 'general' && (
					<>
						<PanelBody title={ __( 'Slide Image', 'swishfolio-core' ) }>
							<MediaUploadCheck>
								<MediaUpload
									onSelect={ onSelectImage }
									allowedTypes={ [ 'image' ] }
									value={ image?.id }
									render={ ( { open } ) => (
										<>
											{ image?.url ? (
												<div style={ { marginBottom: '8px' } }>
													<img
														src={ image.url }
														alt=""
														style={ {
															width: '100%',
															height: '100px',
															objectFit: 'cover',
															borderRadius: '4px',
															marginBottom: '8px',
														} }
													/>
													<ButtonGroup>
														<Button
															variant="secondary"
															onClick={ open }
															size="small"
														>
															{ __( 'Replace', 'swishfolio-core' ) }
														</Button>
														<Button
															variant="secondary"
															isDestructive
															onClick={ () =>
																setAttributes( { image: undefined } )
															}
															size="small"
														>
															{ __( 'Remove', 'swishfolio-core' ) }
														</Button>
													</ButtonGroup>
												</div>
											) : (
												<Button
													variant="secondary"
													onClick={ open }
													style={ { marginBottom: '8px' } }
												>
													{ __( 'Select Image', 'swishfolio-core' ) }
												</Button>
											) }
										</>
									) }
								/>
							</MediaUploadCheck>
						</PanelBody>

						<PanelBody
							title={ __( 'CTA 1', 'swishfolio-core' ) }
							initialOpen={ false }
						>
							<TextControl
								label={ __( 'Text', 'swishfolio-core' ) }
								value={ cta1Text }
								onChange={ ( value ) =>
									setAttributes( { cta1Text: value } )
								}
							/>
							<URLInput
								label={ __( 'URL', 'swishfolio-core' ) }
								value={ cta1Url }
								onChange={ ( url ) => setAttributes( { cta1Url: url } ) }
							/>
							<ToggleControl
								label={ __( 'Open in new tab', 'swishfolio-core' ) }
								checked={ !! cta1NewTab }
								onChange={ ( value ) =>
									setAttributes( { cta1NewTab: value } )
								}
							/>
						</PanelBody>

						<PanelBody
							title={ __( 'CTA 2', 'swishfolio-core' ) }
							initialOpen={ false }
						>
							<TextControl
								label={ __( 'Text', 'swishfolio-core' ) }
								value={ cta2Text }
								onChange={ ( value ) =>
									setAttributes( { cta2Text: value } )
								}
							/>
							<URLInput
								label={ __( 'URL', 'swishfolio-core' ) }
								value={ cta2Url }
								onChange={ ( url ) => setAttributes( { cta2Url: url } ) }
							/>
							<ToggleControl
								label={ __( 'Open in new tab', 'swishfolio-core' ) }
								checked={ !! cta2NewTab }
								onChange={ ( value ) =>
									setAttributes( { cta2NewTab: value } )
								}
							/>
						</PanelBody>
					</>
				) }
			</InspectorControls>

			<div { ...blockProps }>
				{ image?.url ? (
					<img
						src={ image.url }
						alt={ image.alt || '' }
						className="sfcore-swish-slide__image"
					/>
				) : (
					<div className="sfcore-swish-slide__placeholder">
						<MediaUploadCheck>
							<MediaUpload
								onSelect={ onSelectImage }
								allowedTypes={ [ 'image' ] }
								render={ ( { open } ) => (
									<Button variant="secondary" onClick={ open }>
										{ __( 'Add Image', 'swishfolio-core' ) }
									</Button>
								) }
							/>
						</MediaUploadCheck>
					</div>
				) }
				<div className="sfcore-swish-slide__scrim" />
				<div className="sfcore-swish-slide__content">
					<RichText
						tagName="h3"
						className="sfcore-swish-slide__title"
						value={ title }
						onChange={ ( value ) => setAttributes( { title: value } ) }
						placeholder={ __( 'Title…', 'swishfolio-core' ) }
					/>
					<RichText
						tagName="p"
						className="sfcore-swish-slide__subtitle"
						value={ subtitle }
						onChange={ ( value ) => setAttributes( { subtitle: value } ) }
						placeholder={ __( 'Subtitle…', 'swishfolio-core' ) }
					/>
					{ ( ( cta1Text && cta1Url ) || ( cta2Text && cta2Url ) ) && (
						<div className="sfcore-swish-slide__ctas">
							{ cta1Text && cta1Url && (
								<span className="sfcore-swish-slide__cta sfcore-swish-slide__cta--primary">
									{ cta1Text }
								</span>
							) }
							{ cta2Text && cta2Url && (
								<span className="sfcore-swish-slide__cta sfcore-swish-slide__cta--secondary">
									{ cta2Text }
								</span>
							) }
						</div>
					) }
				</div>
			</div>
		</>
	);
}
