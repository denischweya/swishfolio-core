/**
 * Swish Slider - Save Component.
 *
 * Static save. Emits a labelled <section> wrapper with the CSS custom
 * properties and a single track of slides. The frontend view.js clones the
 * slide set (aria-hidden) to build the seamless loop and starts the
 * animation — without JS the strip renders as a static row.
 */

import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';

export default function save( { attributes } ) {
	const {
		slideWidth,
		slideHeight,
		gap,
		direction,
		secondsPerSlide,
		pauseOnHover,
		revealOnHover,
		textColor,
		scrimColor,
		scrimOpacity,
		ctaColor,
		accentColor,
		ariaLabel,
	} = attributes;

	const wrapperClasses = [ 'sfcore-swish-slider' ];
	if ( direction === 'right' ) {
		wrapperClasses.push( 'sfcore-swish-slider--right' );
	}
	if ( pauseOnHover ) {
		wrapperClasses.push( 'sfcore-swish-slider--pause-hover' );
	}
	if ( revealOnHover ) {
		wrapperClasses.push( 'sfcore-swish-slider--reveal-hover' );
	}

	const blockProps = useBlockProps.save( {
		className: wrapperClasses.join( ' ' ),
		style: {
			'--slide-w': `${ slideWidth ?? 426 }px`,
			'--slide-h': `${ slideHeight ?? 262 }px`,
			'--slide-ratio': `${ slideWidth ?? 426 } / ${ slideHeight ?? 262 }`,
			'--slide-gap': `${ gap ?? 1 }rem`,
			'--seconds-per-slide': secondsPerSlide ?? 6,
			'--scrim-color': scrimColor || undefined,
			'--scrim-opacity': ( scrimOpacity ?? 60 ) / 100,
			'--slider-text-color': textColor || undefined,
			'--cta-color': ctaColor || undefined,
			'--accent-color': accentColor || undefined,
		},
	} );

	return (
		<section { ...blockProps } aria-label={ ariaLabel || undefined }>
			<div className="sfcore-swish-slider__track">
				<InnerBlocks.Content />
			</div>
		</section>
	);
}
