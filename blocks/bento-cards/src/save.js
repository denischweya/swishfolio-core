/**
 * Bento Cards - Save Component.
 *
 * Static save. Emits the wrapper + grid container with `<InnerBlocks.Content />`
 * for cards, plus the optional CTA. CSS custom properties are set on the
 * wrapper and inherited by all children.
 */

import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';

export default function save( { attributes } ) {
	const {
		gridGap,
		cardOverlayColor,
		cardOverlayOpacity,
		accentColor,
		ctaType,
		ctaText,
		ctaUrl,
		ctaTextColor,
		ctaBgColor,
	} = attributes;

	const blockProps = useBlockProps.save( {
		className: 'sfcore-bento',
		style: {
			'--grid-gap': gridGap,
			'--overlay-color': cardOverlayColor || 'rgba(6, 26, 20, 0.85)',
			'--overlay-opacity': cardOverlayOpacity / 100,
			'--accent-color': accentColor || '#FFE500',
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
