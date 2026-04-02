import { useBlockProps, RichText } from '@wordpress/block-editor';

export default function save( { attributes } ) {
	const {
		columns,
		showToggle,
		defaultBilling,
		toggleMonthlyLabel,
		toggleYearlyLabel,
		yearlyDiscount,
		plans,
		highlightedBadgeText,
		borderColor,
		highlightedBackgroundColor,
		highlightedTextColor,
		textColor,
		toggleBackgroundColor,
	} = attributes;

	const blockProps = useBlockProps.save( {
		className: `sfcore-pricing sfcore-pricing--cols-${ Math.min( plans.length, columns ) }`,
		style: {
			'--pricing-border-color': borderColor || undefined,
			'--pricing-highlight-bg': highlightedBackgroundColor || undefined,
			'--pricing-highlight-text': highlightedTextColor || undefined,
			'--pricing-text-color': textColor || undefined,
			'--pricing-toggle-bg': toggleBackgroundColor || undefined,
		},
		'data-default-billing': defaultBilling,
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
				{ plans.slice( 0, columns ).map( ( plan, index ) => (
					<div
						key={ index }
						className={ `sfcore-pricing__plan ${ plan.highlighted ? 'sfcore-pricing__plan--highlighted' : '' }` }
					>
						{ plan.highlighted && highlightedBadgeText && (
							<div className="sfcore-pricing__badge">{ highlightedBadgeText }</div>
						) }
						<div className="sfcore-pricing__header">
							<RichText.Content
								tagName="h3"
								className="sfcore-pricing__title"
								value={ plan.title }
							/>
							<RichText.Content
								tagName="p"
								className="sfcore-pricing__subtitle"
								value={ plan.subtitle }
							/>
						</div>
						<div className="sfcore-pricing__price">
							<span className="sfcore-pricing__price-prefix">{ plan.pricePrefix }</span>
							<span
								className="sfcore-pricing__price-amount"
								data-monthly={ plan.monthlyPrice }
								data-yearly={ plan.yearlyPrice }
							>
								{ defaultBilling === 'monthly' ? plan.monthlyPrice : plan.yearlyPrice }
							</span>
							<span className="sfcore-pricing__price-period">
								<span className="sfcore-pricing__period-monthly" style={ defaultBilling === 'yearly' ? { display: 'none' } : undefined }>
									/ month
								</span>
								<span className="sfcore-pricing__period-yearly" style={ defaultBilling === 'monthly' ? { display: 'none' } : undefined }>
									/ year
								</span>
							</span>
						</div>
						<ul className="sfcore-pricing__features">
							{ plan.features.map( ( feature, featureIndex ) => (
								<li key={ featureIndex } className="sfcore-pricing__feature">
									<span className="sfcore-pricing__feature-icon">
										<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
											<path d="M16.667 5L7.5 14.167 3.333 10" stroke="currentColor" strokeWidth="2" strokeLinecap="square" strokeLinejoin="miter"/>
										</svg>
									</span>
									<span className="sfcore-pricing__feature-text">{ feature }</span>
								</li>
							) ) }
						</ul>
						<div className="sfcore-pricing__cta">
							<a href={ plan.buttonUrl } className="sfcore-pricing__button">
								<RichText.Content value={ plan.buttonText } />
							</a>
						</div>
					</div>
				) ) }
			</div>
		</div>
	);
}
