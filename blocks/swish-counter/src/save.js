/**
 * Swish Counter Block - Save Component.
 *
 * Static save. CSS custom properties on the wrapper are inherited by
 * swish-counter-item children. Animation is driven by view.js, which reads
 * `data-target` and `data-decimals` from each child.
 */

import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';

export default function save( { attributes } ) {
	const {
		columns,
		align,
		counterFontSize,
		counterFontWeight,
		counterColor,
		titleFontSize,
		titleColor,
		counterTitleGap,
	} = attributes;

	const blockProps = useBlockProps.save( {
		className: `sfcore-swish-counter sfcore-swish-counter--align-${ align }`,
		style: {
			'--sf-counter-columns': columns,
			'--sf-counter-size': counterFontSize || undefined,
			'--sf-counter-weight': counterFontWeight,
			'--sf-counter-color': counterColor || undefined,
			'--sf-counter-title-size': titleFontSize || undefined,
			'--sf-counter-title-color': titleColor || undefined,
			'--sf-counter-title-gap': `${ counterTitleGap }px`,
		},
	} );

	return (
		<div { ...blockProps }>
			<div className="sfcore-swish-counter__grid">
				<InnerBlocks.Content />
			</div>
		</div>
	);
}
