/**
 * Pricing Plan - Save Component.
 *
 * Static save. Both billing price rows are rendered; the parent's
 * `data-billing` attribute + CSS decide which is visible. Period labels
 * render via CSS `content: var(--period-*)` from the parent (ADR-0002).
 * A set price link replaces the numeric price in both modes. The features
 * wrapper receives the constrained core/list via InnerBlocks.
 */

import { useBlockProps, InnerBlocks, RichText } from '@wordpress/block-editor';

export default function save( { attributes } ) {
	const {
		title,
		subtitle,
		monthlyPrice,
		yearlyPrice,
		pricePrefix,
		priceLinkText,
		priceLinkUrl,
		highlighted,
		badgeText,
		buttonText,
		buttonUrl,
	} = attributes;

	const blockProps = useBlockProps.save( {
		className: `sfcore-pricing__plan ${ highlighted ? 'sfcore-pricing__plan--highlighted' : '' }`,
	} );

	const hasPriceLink = priceLinkText && priceLinkUrl;

	const priceRow = ( mode, amount ) => (
		<span className={ `sfcore-pricing__price-row sfcore-pricing__price-row--${ mode }` }>
			{ amount && (
				<>
					<span className="sfcore-pricing__price-prefix">{ pricePrefix }</span>
					<span className="sfcore-pricing__price-amount">{ amount }</span>
					<span className={ `sfcore-pricing__price-period sfcore-pricing__price-period--${ mode }` } />
				</>
			) }
		</span>
	);

	return (
		<div { ...blockProps }>
			{ highlighted && badgeText && (
				<div className="sfcore-pricing__badge">{ badgeText }</div>
			) }

			<div className="sfcore-pricing__header">
				<RichText.Content
					tagName="h3"
					className="sfcore-pricing__title"
					value={ title }
				/>
				<RichText.Content
					tagName="p"
					className="sfcore-pricing__subtitle"
					value={ subtitle }
				/>
			</div>

			<div className="sfcore-pricing__price">
				{ hasPriceLink ? (
					<a href={ priceLinkUrl } className="sfcore-pricing__price-link">
						{ priceLinkText }
					</a>
				) : (
					<>
						{ priceRow( 'monthly', monthlyPrice ) }
						{ priceRow( 'yearly', yearlyPrice ) }
					</>
				) }
			</div>

			<div className="sfcore-pricing__features">
				<InnerBlocks.Content />
			</div>

			<div className="sfcore-pricing__cta">
				<a href={ buttonUrl } className="sfcore-pricing__button">
					<RichText.Content value={ buttonText } />
				</a>
			</div>
		</div>
	);
}
