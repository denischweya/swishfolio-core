/**
 * Bento Card - Edit Component.
 *
 * Inline-editable subtitle/title/description, media picker for image, size
 * selector, per-card title color. Locked to the bento-cards parent via
 * block.json's `parent` field.
 */

import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	RichText,
	InspectorControls,
	MediaUpload,
	MediaUploadCheck,
	useSetting,
} from '@wordpress/block-editor';
import {
	PanelBody,
	Button,
	ButtonGroup,
	ColorPalette,
	SelectControl,
} from '@wordpress/components';
import InspectorTabs, {
	useInspectorTabs,
} from '../../shared/components/inspector-tabs';

const SIZE_OPTIONS = [
	{ label: __( 'Full Width (3/3)', 'swishfolio-core' ), value: 'full' },
	{ label: __( 'Two Thirds (2/3)', 'swishfolio-core' ), value: 'two-thirds' },
	{ label: __( 'Half (1/2)', 'swishfolio-core' ), value: 'half' },
	{ label: __( 'One Third (1/3)', 'swishfolio-core' ), value: 'one-third' },
];

export default function Edit( { attributes, setAttributes } ) {
	const [ activeTab, setActiveTab ] = useInspectorTabs();
	const themeColors = useSetting( 'color.palette' ) || [];

	const { image, subtitle, title, description, titleColor, cardSize } =
		attributes;

	const classes = [
		'sfcore-bento__card',
		`sfcore-bento__card--${ cardSize || 'one-third' }`,
	];
	if ( image?.url ) {
		classes.push( 'sfcore-bento__card--has-image' );
	}

	const blockProps = useBlockProps( { className: classes.join( ' ' ) } );

	const titleStyle = titleColor ? { color: titleColor } : undefined;

	return (
		<>
			<InspectorControls>
				<InspectorTabs
					activeTab={ activeTab }
					setActiveTab={ setActiveTab }
				/>

				{ activeTab === 'general' && (
					<PanelBody title={ __( 'Card', 'swishfolio-core' ) }>
						<SelectControl
							label={ __( 'Card Size', 'swishfolio-core' ) }
							value={ cardSize }
							options={ SIZE_OPTIONS }
							onChange={ ( value ) =>
								setAttributes( { cardSize: value } )
							}
						/>

						<p className="components-base-control__label" style={ { marginTop: '12px' } }>
							{ __( 'Card Image', 'swishfolio-core' ) }
						</p>
						<MediaUploadCheck>
							<MediaUpload
								onSelect={ ( media ) =>
									setAttributes( {
										image: {
											url: media.url,
											alt: media.alt,
											id: media.id,
										},
									} )
								}
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
				) }

				{ activeTab === 'style' && (
					<PanelBody title={ __( 'Text Color', 'swishfolio-core' ) }>
						<p className="components-base-control__label">
							{ __( 'Title / Subtitle / Description', 'swishfolio-core' ) }
						</p>
						<ColorPalette
							colors={ themeColors }
							value={ titleColor }
							onChange={ ( color ) =>
								setAttributes( { titleColor: color || '' } )
							}
							clearable
						/>
					</PanelBody>
				) }
			</InspectorControls>

			<div { ...blockProps }>
				{ image?.url && (
					<img
						src={ image.url }
						alt={ image.alt || '' }
						className="sfcore-bento__card-image"
					/>
				) }
				<div className="sfcore-bento__card-overlay" />
				<div className="sfcore-bento__card-content">
					<RichText
						tagName="span"
						className="sfcore-bento__card-subtitle"
						value={ subtitle }
						onChange={ ( value ) => setAttributes( { subtitle: value } ) }
						placeholder={ __( 'Subtitle…', 'swishfolio-core' ) }
						style={ titleStyle }
					/>
					<div className="sfcore-bento__card-info">
						<RichText
							tagName="h3"
							className="sfcore-bento__card-title"
							value={ title }
							onChange={ ( value ) => setAttributes( { title: value } ) }
							placeholder={ __( 'Card title…', 'swishfolio-core' ) }
							style={ titleStyle }
						/>
						<RichText
							tagName="p"
							className="sfcore-bento__card-description"
							value={ description }
							onChange={ ( value ) =>
								setAttributes( { description: value } )
							}
							placeholder={ __( 'Description…', 'swishfolio-core' ) }
							style={ titleStyle }
						/>
					</div>
				</div>
			</div>
		</>
	);
}
