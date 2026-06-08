/**
 * Features Block - Save Component.
 *
 * Parent wrapper. Exposes global styles as --features-* CSS custom
 * properties that child feature-card blocks inherit unless they set their
 * own override.
 */

import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';

export default function save( { attributes } ) {
	const {
		gridGap,
		cardBorderStyle,
		cardBorderColor,
		cardShadowColor,
		titleFontSize,
		subheadingFontSize,
		titleColor,
		iconColor,
		iconBackgroundColor,
		ctaTextColor,
		ctaBackgroundColor,
		cardBackgroundColor,
		cardAccentColor,
	} = attributes;

	const blockProps = useBlockProps.save( {
		className: `sfcore-features sfcore-features--border-${ cardBorderStyle }`,
		style: {
			'--grid-gap': gridGap,
			'--card-border-color': cardBorderColor || undefined,
			'--card-shadow-color': cardShadowColor || undefined,
			'--features-title-color': titleColor || undefined,
			'--features-title-font-size': titleFontSize || undefined,
			'--features-subheading-font-size': subheadingFontSize || undefined,
			'--features-icon-color': iconColor || undefined,
			'--features-icon-bg': iconBackgroundColor || undefined,
			'--features-cta-text-color': ctaTextColor || undefined,
			'--features-cta-bg': ctaBackgroundColor || undefined,
			'--features-card-bg': cardBackgroundColor || undefined,
			'--features-card-accent': cardAccentColor || undefined,
		},
	} );

	return (
		<div { ...blockProps }>
			<div className="sfcore-features__grid">
				<InnerBlocks.Content />
			</div>
		</div>
	);
}
