/**
 * Bento Cards - Save Component.
 *
 * Static save. Emits the wrapper (with grid-wide style + layered classes and
 * CSS custom properties) and the grid container with `<InnerBlocks.Content />`
 * for cards, plus the optional section CTA.
 */

import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';

export default function save( { attributes } ) {
	const {
		gridGap,
		cardStyle,
		cardOverlayColor,
		cardOverlayOpacity,
		accentColor,
		cardSubtitleColor,
		cardTitleColor,
		cardDescriptionColor,
		cardTextBg,
		cardTitleFontSize,
		layeredImages,
		layeredBgColor,
		layeredPaddingTop,
		layeredTallImage,
		layeredTallHeight,
		layeredTallSpeed,
		ctaType,
		ctaText,
		ctaUrl,
		ctaTextColor,
		ctaBgColor,
	} = attributes;

	const wrapperClasses = [
		'sfcore-bento',
		`sfcore-bento--style-${ cardStyle || 'overlay' }`,
	];
	if ( layeredImages ) {
		wrapperClasses.push( 'sfcore-bento--layered' );
		if ( layeredTallImage ) {
			wrapperClasses.push( 'sfcore-bento--tall-image' );
		}
	}

	const blockProps = useBlockProps.save( {
		className: wrapperClasses.join( ' ' ),
		style: {
			'--grid-gap': gridGap,
			'--overlay-color': cardOverlayColor || 'rgba(6, 26, 20, 0.85)',
			'--overlay-opacity': cardOverlayOpacity / 100,
			'--accent-color': accentColor || '#FFE500',
			'--card-subtitle-color': cardSubtitleColor || undefined,
			'--card-title-color': cardTitleColor || undefined,
			'--card-description-color': cardDescriptionColor || undefined,
			'--card-text-bg': cardTextBg || undefined,
			'--card-title-base-size': cardTitleFontSize || undefined,
			'--layered-bg-color': layeredBgColor || undefined,
			'--layered-padding-top': `${ layeredPaddingTop ?? 10 }%`,
			'--layered-tall-height': `${ layeredTallHeight ?? 395 }px`,
			'--layered-tall-speed': `${ layeredTallSpeed ?? 4.6 }s`,
		},
	} );

	return (
		<div { ...blockProps }>
			<div className="sfcore-bento__grid">
				<InnerBlocks.Content />
			</div>

			{ ctaType !== 'none' && ctaText && ctaUrl && (
				<div className="sfcore-bento__cta-wrapper">
					<a
						href={ ctaUrl }
						className={ `sfcore-bento__cta sfcore-bento__cta--${ ctaType }` }
						style={ {
							color: ctaTextColor || undefined,
							backgroundColor:
								ctaType === 'button' ? ctaBgColor || undefined : undefined,
						} }
					>
						{ ctaText }
					</a>
				</div>
			) }
		</div>
	);
}
