/**
 * Pricing Table - Save Component.
 *
 * Static save. The wrapper carries `data-billing` (which price row is
 * visible — CSS does the switching) and the period labels as CSS string
 * variables consumed by the plans' period spans via `content: var(...)`,
 * since child static saves cannot read parent attributes (ADR-0002).
 */

import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';

export default function save( { attributes } ) {
	const {
		showToggle,
		defaultBilling,
		toggleMonthlyLabel,
		toggleYearlyLabel,
		yearlyDiscount,
		monthlyPeriodLabel,
		yearlyPeriodLabel,
		borderColor,
		highlightedBackgroundColor,
		highlightedTextColor,
		textColor,
		toggleBackgroundColor,
	} = attributes;

	const cssString = ( value, fallback ) =>
		`"${ ( value || fallback ).replace( /"/g, '' ) }"`;

	const blockProps = useBlockProps.save( {
		className: 'sfcore-pricing',
		style: {
			'--pricing-border-color': borderColor || undefined,
			'--pricing-highlight-bg': highlightedBackgroundColor || undefined,
			'--pricing-highlight-text': highlightedTextColor || undefined,
			'--pricing-text-color': textColor || undefined,
			'--pricing-toggle-bg': toggleBackgroundColor || undefined,
			'--period-monthly': cssString( monthlyPeriodLabel, 'month' ),
			'--period-yearly': cssString( yearlyPeriodLabel, 'year' ),
		},
		'data-billing': defaultBilling,
	} );

	return (
		<div { ...blockProps }>
			{ showToggle && (
				<div className="sfcore-pricing__toggle-wrapper">
					<div className="sfcore-pricing__toggle" role="tablist">
						<button
							type="button"
							className={ `sfcore-pricing__toggle-btn ${ defaultBilling === 'monthly' ? 'is-active' : '' }` }
							data-billing="monthly"
							role="tab"
							aria-selected={ defaultBilling === 'monthly' ? 'true' : 'false' }
						>
							{ toggleMonthlyLabel }
						</button>
						<button
							type="button"
							className={ `sfcore-pricing__toggle-btn ${ defaultBilling === 'yearly' ? 'is-active' : '' }` }
							data-billing="yearly"
							role="tab"
							aria-selected={ defaultBilling === 'yearly' ? 'true' : 'false' }
						>
							{ toggleYearlyLabel }
							{ yearlyDiscount && (
								<span className="sfcore-pricing__discount-badge">{ yearlyDiscount }</span>
							) }
						</button>
					</div>
				</div>
			) }

			<div className="sfcore-pricing__grid">
				<InnerBlocks.Content />
			</div>
		</div>
	);
}
