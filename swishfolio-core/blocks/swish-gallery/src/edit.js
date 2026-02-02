/**
 * Swish Gallery Block - Edit Component
 */

import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
	MediaUpload,
	MediaUploadCheck,
} from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	RangeControl,
	ToggleControl,
	Button,
	ButtonGroup,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
} from '@wordpress/components';

export default function Edit( { attributes, setAttributes } ) {
	const {
		images,
		layout,
		columns,
		gap,
		imageSize,
		aspectRatio,
		hoverEffect,
		enableLightbox,
		lightboxShowCaption,
		autoplay,
		autoplaySpeed,
		showArrows,
		showDots,
		imageBorderRadius,
		imageShadow,
	} = attributes;

	const onSelectImages = ( selectedImages ) => {
		const newImages = selectedImages.map( ( image ) => ( {
			id: image.id,
			url: image.url,
			alt: image.alt || '',
			caption: image.caption || '',
			sizes: image.sizes || {},
		} ) );
		setAttributes( { images: newImages } );
	};

	const removeImage = ( indexToRemove ) => {
		const newImages = images.filter( ( _, index ) => index !== indexToRemove );
		setAttributes( { images: newImages } );
	};

	const moveImage = ( currentIndex, newIndex ) => {
		if ( newIndex < 0 || newIndex >= images.length ) {
			return;
		}
		const newImages = [ ...images ];
		const [ movedImage ] = newImages.splice( currentIndex, 1 );
		newImages.splice( newIndex, 0, movedImage );
		setAttributes( { images: newImages } );
	};

	const getImageUrl = ( image ) => {
		if ( image.sizes && image.sizes[ imageSize ] ) {
			return image.sizes[ imageSize ].url;
		}
		return image.url;
	};

	const getAspectRatioClass = () => {
		if ( aspectRatio === 'original' ) {
			return '';
		}
		return `sfcore-swish-gallery--aspect-${ aspectRatio }`;
	};

	const getShadowClass = () => {
		if ( imageShadow === 'none' ) {
			return '';
		}
		return `sfcore-swish-gallery--shadow-${ imageShadow }`;
	};

	const blockProps = useBlockProps( {
		className: `sfcore-swish-gallery sfcore-swish-gallery--layout-${ layout } sfcore-swish-gallery--cols-${ columns } sfcore-swish-gallery--hover-${ hoverEffect } ${ getAspectRatioClass() } ${ getShadowClass() }`.trim(),
		style: {
			'--gallery-gap': `${ gap }px`,
			'--gallery-columns': columns,
			'--image-border-radius': `${ imageBorderRadius }px`,
		},
	} );

	const isCarousel = layout === 'carousel';

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Layout', 'swishfolio-core' ) }>
					<ToggleGroupControl
						label={ __( 'Layout Style', 'swishfolio-core' ) }
						value={ layout }
						onChange={ ( value ) => setAttributes( { layout: value } ) }
						isBlock
					>
						<ToggleGroupControlOption value="grid" label={ __( 'Grid', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="masonry" label={ __( 'Masonry', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="carousel" label={ __( 'Carousel', 'swishfolio-core' ) } />
					</ToggleGroupControl>

					{ ! isCarousel && (
						<RangeControl
							label={ __( 'Columns', 'swishfolio-core' ) }
							value={ columns }
							onChange={ ( value ) => setAttributes( { columns: value } ) }
							min={ 1 }
							max={ 6 }
						/>
					) }

					<RangeControl
						label={ __( 'Gap (px)', 'swishfolio-core' ) }
						value={ gap }
						onChange={ ( value ) => setAttributes( { gap: value } ) }
						min={ 0 }
						max={ 48 }
					/>
				</PanelBody>

				<PanelBody title={ __( 'Image Settings', 'swishfolio-core' ) } initialOpen={ false }>
					<SelectControl
						label={ __( 'Image Size', 'swishfolio-core' ) }
						value={ imageSize }
						options={ [
							{ label: __( 'Thumbnail', 'swishfolio-core' ), value: 'thumbnail' },
							{ label: __( 'Medium', 'swishfolio-core' ), value: 'medium' },
							{ label: __( 'Large', 'swishfolio-core' ), value: 'large' },
							{ label: __( 'Full', 'swishfolio-core' ), value: 'full' },
						] }
						onChange={ ( value ) => setAttributes( { imageSize: value } ) }
					/>

					<ToggleGroupControl
						label={ __( 'Aspect Ratio', 'swishfolio-core' ) }
						value={ aspectRatio }
						onChange={ ( value ) => setAttributes( { aspectRatio: value } ) }
						isBlock
					>
						<ToggleGroupControlOption value="original" label={ __( 'Auto', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="square" label={ __( '1:1', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="landscape" label={ __( '4:3', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="portrait" label={ __( '3:4', 'swishfolio-core' ) } />
					</ToggleGroupControl>

					<ToggleGroupControl
						label={ __( 'Hover Effect', 'swishfolio-core' ) }
						value={ hoverEffect }
						onChange={ ( value ) => setAttributes( { hoverEffect: value } ) }
						isBlock
					>
						<ToggleGroupControlOption value="none" label={ __( 'None', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="zoom" label={ __( 'Zoom', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="fade" label={ __( 'Fade', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="lift" label={ __( 'Lift', 'swishfolio-core' ) } />
					</ToggleGroupControl>

					<RangeControl
						label={ __( 'Border Radius (px)', 'swishfolio-core' ) }
						value={ imageBorderRadius }
						onChange={ ( value ) => setAttributes( { imageBorderRadius: value } ) }
						min={ 0 }
						max={ 32 }
					/>

					<ToggleGroupControl
						label={ __( 'Shadow', 'swishfolio-core' ) }
						value={ imageShadow }
						onChange={ ( value ) => setAttributes( { imageShadow: value } ) }
						isBlock
					>
						<ToggleGroupControlOption value="none" label={ __( 'None', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="small" label={ __( 'S', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="medium" label={ __( 'M', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="large" label={ __( 'L', 'swishfolio-core' ) } />
					</ToggleGroupControl>
				</PanelBody>

				<PanelBody title={ __( 'Lightbox', 'swishfolio-core' ) } initialOpen={ false }>
					<ToggleControl
						label={ __( 'Enable Lightbox', 'swishfolio-core' ) }
						checked={ enableLightbox }
						onChange={ ( value ) => setAttributes( { enableLightbox: value } ) }
					/>

					{ enableLightbox && (
						<ToggleControl
							label={ __( 'Show Captions', 'swishfolio-core' ) }
							checked={ lightboxShowCaption }
							onChange={ ( value ) => setAttributes( { lightboxShowCaption: value } ) }
						/>
					) }
				</PanelBody>

				{ isCarousel && (
					<PanelBody title={ __( 'Carousel Settings', 'swishfolio-core' ) } initialOpen={ false }>
						<ToggleControl
							label={ __( 'Autoplay', 'swishfolio-core' ) }
							checked={ autoplay }
							onChange={ ( value ) => setAttributes( { autoplay: value } ) }
						/>

						{ autoplay && (
							<RangeControl
								label={ __( 'Autoplay Speed (ms)', 'swishfolio-core' ) }
								value={ autoplaySpeed }
								onChange={ ( value ) => setAttributes( { autoplaySpeed: value } ) }
								min={ 1000 }
								max={ 10000 }
								step={ 500 }
							/>
						) }

						<ToggleControl
							label={ __( 'Show Arrows', 'swishfolio-core' ) }
							checked={ showArrows }
							onChange={ ( value ) => setAttributes( { showArrows: value } ) }
						/>

						<ToggleControl
							label={ __( 'Show Dots', 'swishfolio-core' ) }
							checked={ showDots }
							onChange={ ( value ) => setAttributes( { showDots: value } ) }
						/>
					</PanelBody>
				) }
			</InspectorControls>

			<div { ...blockProps }>
				{ images.length === 0 ? (
					<MediaUploadCheck>
						<MediaUpload
							onSelect={ onSelectImages }
							allowedTypes={ [ 'image' ] }
							multiple
							gallery
							render={ ( { open } ) => (
								<div className="sfcore-swish-gallery__placeholder" onClick={ open } onKeyDown={ ( e ) => e.key === 'Enter' && open() } role="button" tabIndex={ 0 }>
									<div className="sfcore-swish-gallery__placeholder-content">
										<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48" aria-hidden="true" focusable="false">
											<path d="M18.7 3H5.3C4 3 3 4 3 5.3v13.4C3 20 4 21 5.3 21h13.4c1.3 0 2.3-1 2.3-2.3V5.3C21 4 20 3 18.7 3zm.8 15.7c0 .4-.4.8-.8.8H5.3c-.4 0-.8-.4-.8-.8V5.3c0-.4.4-.8.8-.8h13.4c.4 0 .8.4.8.8v13.4zM10 15l5-3-5-3v6z" />
										</svg>
										<span>{ __( 'Click to select images', 'swishfolio-core' ) }</span>
									</div>
								</div>
							) }
						/>
					</MediaUploadCheck>
				) : (
					<>
						<div className="sfcore-swish-gallery__container">
							{ images.map( ( image, index ) => (
								<div key={ image.id || index } className="sfcore-swish-gallery__item">
									<div className="sfcore-swish-gallery__image-wrapper">
										<img
											src={ getImageUrl( image ) }
											alt={ image.alt }
											className="sfcore-swish-gallery__image"
										/>
										<div className="sfcore-swish-gallery__item-actions">
											<ButtonGroup>
												<Button
													icon="arrow-left-alt2"
													label={ __( 'Move left', 'swishfolio-core' ) }
													onClick={ () => moveImage( index, index - 1 ) }
													disabled={ index === 0 }
													size="small"
												/>
												<Button
													icon="arrow-right-alt2"
													label={ __( 'Move right', 'swishfolio-core' ) }
													onClick={ () => moveImage( index, index + 1 ) }
													disabled={ index === images.length - 1 }
													size="small"
												/>
												<Button
													icon="no-alt"
													label={ __( 'Remove', 'swishfolio-core' ) }
													onClick={ () => removeImage( index ) }
													isDestructive
													size="small"
												/>
											</ButtonGroup>
										</div>
									</div>
								</div>
							) ) }
						</div>

						<div className="sfcore-swish-gallery__toolbar">
							<MediaUploadCheck>
								<MediaUpload
									onSelect={ onSelectImages }
									allowedTypes={ [ 'image' ] }
									multiple
									gallery
									value={ images.map( ( img ) => img.id ) }
									render={ ( { open } ) => (
										<Button variant="secondary" onClick={ open }>
											{ __( 'Edit Gallery', 'swishfolio-core' ) }
										</Button>
									) }
								/>
							</MediaUploadCheck>
						</div>
					</>
				) }
			</div>
		</>
	);
}
