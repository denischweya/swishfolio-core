/**
 * Testimonials Block - Save Component.
 *
 * Static save. CSS custom properties on the wrapper are inherited by
 * testimonial-card children. Card defaults fall back to theme tokens.
 */

import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';

const SHADOWS = {
	none: 'none',
	small: '0 1px 2px rgba(0,0,0,0.06), 0 1px 3px rgba(0,0,0,0.08)',
	medium: '0 4px 6px -1px rgba(0,0,0,0.08), 0 2px 4px -1px rgba(0,0,0,0.06)',
	large: '0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04)',
};

export default function save( { attributes } ) {
	const {
		columns,
		cardBackgroundColor,
		cardBorderColor,
		cardBorderWidth,
		cardBorderRadius,
		cardShadow,
		textColor,
		roleColor,
	} = attributes;

	const blockProps = useBlockProps.save( {
		className: 'sfcore-testimonials',
		style: {
			'--sf-columns': columns,
			'--sf-card-bg':
				cardBackgroundColor || 'var(--wp--preset--color--accent-2)',
			'--sf-card-border-color': cardBorderColor || 'transparent',
			'--sf-card-border-width': `${ cardBorderWidth }px`,
			'--sf-card-radius': `${ cardBorderRadius }px`,
			'--sf-card-shadow': SHADOWS[ cardShadow ] || 'none',
			'--sf-card-text':
				textColor || 'var(--wp--preset--color--contrast)',
			'--sf-role-color':
				roleColor || 'var(--wp--preset--color--accent-3)',
		},
	} );

	return (
		<div { ...blockProps }>
			<div className="sfcore-testimonials__grid">
				<InnerBlocks.Content />
			</div>
		</div>
	);
}
