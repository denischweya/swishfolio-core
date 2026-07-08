/**
 * Swish Slide - Save Component.
 *
 * Static save. Image fills the slide, gradient scrim above it, text +
 * CTAs bottom-anchored and hidden until hover/focus (opacity only, so the
 * text stays in the accessibility tree). Every piece renders only when it
 * has content — no placeholders.
 */

import { useBlockProps, RichText } from '@wordpress/block-editor';

function Cta( { text, url, newTab, variant } ) {
	if ( ! text || ! url ) {
		return null;
	}

	return (
		<a
			href={ url }
			className={ `sfcore-swish-slide__cta sfcore-swish-slide__cta--${ variant }` }
			target={ newTab ? '_blank' : undefined }
			rel={ newTab ? 'noreferrer noopener' : undefined }
		>
			{ text }
		</a>
	);
}

export default function save( { attributes } ) {
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

	const blockProps = useBlockProps.save( {
		className: 'sfcore-swish-slide',
	} );

	const hasCta1 = cta1Text && cta1Url;
	const hasCta2 = cta2Text && cta2Url;
	const hasContent = title || subtitle || hasCta1 || hasCta2;

	return (
		<div { ...blockProps }>
			{ image?.url && (
				<img
					src={ image.url }
					alt={ image.alt || '' }
					className="sfcore-swish-slide__image"
				/>
			) }
			<div className="sfcore-swish-slide__scrim" />
			{ hasContent && (
				<div className="sfcore-swish-slide__content">
					{ title && (
						<RichText.Content
							tagName="h3"
							className="sfcore-swish-slide__title"
							value={ title }
						/>
					) }
					{ subtitle && (
						<RichText.Content
							tagName="p"
							className="sfcore-swish-slide__subtitle"
							value={ subtitle }
						/>
					) }
					{ ( hasCta1 || hasCta2 ) && (
						<div className="sfcore-swish-slide__ctas">
							<Cta
								text={ cta1Text }
								url={ cta1Url }
								newTab={ cta1NewTab }
								variant="primary"
							/>
							<Cta
								text={ cta2Text }
								url={ cta2Url }
								newTab={ cta2NewTab }
								variant="secondary"
							/>
						</div>
					) }
				</div>
			) }
		</div>
	);
}
