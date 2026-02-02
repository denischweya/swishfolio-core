/**
 * Swish Gallery Block - Save Component
 */

import { useBlockProps } from '@wordpress/block-editor';

export default function save( { attributes } ) {
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

	if ( ! images || images.length === 0 ) {
		return null;
	}

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

	const isCarousel = layout === 'carousel';

	const blockProps = useBlockProps.save( {
		className: `sfcore-swish-gallery sfcore-swish-gallery--layout-${ layout } sfcore-swish-gallery--cols-${ columns } sfcore-swish-gallery--hover-${ hoverEffect } ${ getAspectRatioClass() } ${ getShadowClass() }`.trim(),
		style: {
			'--gallery-gap': `${ gap }px`,
			'--gallery-columns': columns,
			'--image-border-radius': `${ imageBorderRadius }px`,
		},
		'data-lightbox': enableLightbox ? 'true' : 'false',
		'data-lightbox-caption': lightboxShowCaption ? 'true' : 'false',
		'data-autoplay': autoplay ? 'true' : 'false',
		'data-autoplay-speed': autoplaySpeed,
		'data-show-arrows': showArrows ? 'true' : 'false',
		'data-show-dots': showDots ? 'true' : 'false',
	} );

	const getImageUrl = ( image ) => {
		if ( image.sizes && image.sizes[ imageSize ] ) {
			return image.sizes[ imageSize ].url;
		}
		return image.url;
	};

	return (
		<div { ...blockProps }>
			<div className="sfcore-swish-gallery__container">
				{ images.map( ( image, index ) => (
					<figure key={ image.id || index } className="sfcore-swish-gallery__item">
						<div className="sfcore-swish-gallery__image-wrapper">
							<img
								src={ getImageUrl( image ) }
								alt={ image.alt || '' }
								className="sfcore-swish-gallery__image"
								data-full-url={ image.url }
								data-index={ index }
							/>
							{ enableLightbox && (
								<button
									type="button"
									className="sfcore-swish-gallery__lightbox-trigger"
									aria-label={ `View ${ image.alt || 'image' } in lightbox` }
									data-index={ index }
								>
									<span className="sfcore-swish-gallery__zoom-icon" aria-hidden="true">
										<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24">
											<path d="M15 3h6v6h-2V5h-4V3zm6 12v6h-6v-2h4v-4h2zM9 3v2H5v4H3V3h6zm0 18H3v-6h2v4h4v2z" />
										</svg>
									</span>
								</button>
							) }
						</div>
						{ lightboxShowCaption && image.caption && (
							<figcaption className="sfcore-swish-gallery__caption">
								{ image.caption }
							</figcaption>
						) }
					</figure>
				) ) }
			</div>

			{ isCarousel && showArrows && images.length > 1 && (
				<div className="sfcore-swish-gallery__nav">
					<button type="button" className="sfcore-swish-gallery__prev" aria-label="Previous">
						<span aria-hidden="true">&larr;</span>
					</button>
					<button type="button" className="sfcore-swish-gallery__next" aria-label="Next">
						<span aria-hidden="true">&rarr;</span>
					</button>
				</div>
			) }

			{ isCarousel && showDots && images.length > 1 && (
				<div className="sfcore-swish-gallery__dots">
					{ images.map( ( _, index ) => (
						<button
							key={ index }
							type="button"
							className={ `sfcore-swish-gallery__dot${ index === 0 ? ' is-active' : '' }` }
							aria-label={ `Go to slide ${ index + 1 }` }
							data-index={ index }
						/>
					) ) }
				</div>
			) }
		</div>
	);
}
