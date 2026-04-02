import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
	RichText,
	useSetting,
} from '@wordpress/block-editor';
import {
	PanelBody,
	ToggleControl,
	TextControl,
	RangeControl,
	Button,
	ColorPalette,
	__experimentalHStack as HStack,
} from '@wordpress/components';
import { useState } from '@wordpress/element';

export default function Edit( { attributes, setAttributes } ) {
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

	const themeColors = useSetting( 'color.palette' ) || [];

	const [ billingPeriod, setBillingPeriod ] = useState( defaultBilling );
	const [ expandedPlan, setExpandedPlan ] = useState( 0 );

	const updatePlan = ( index, field, value ) => {
		const newPlans = [ ...plans ];
		newPlans[ index ] = { ...newPlans[ index ], [ field ]: value };
		setAttributes( { plans: newPlans } );
	};

	const updatePlanFeature = ( planIndex, featureIndex, value ) => {
		const newPlans = [ ...plans ];
		const newFeatures = [ ...newPlans[ planIndex ].features ];
		newFeatures[ featureIndex ] = value;
		newPlans[ planIndex ] = { ...newPlans[ planIndex ], features: newFeatures };
		setAttributes( { plans: newPlans } );
	};

	const addFeature = ( planIndex ) => {
		const newPlans = [ ...plans ];
		newPlans[ planIndex ] = {
			...newPlans[ planIndex ],
			features: [ ...newPlans[ planIndex ].features, 'New feature' ],
		};
		setAttributes( { plans: newPlans } );
	};

	const removeFeature = ( planIndex, featureIndex ) => {
		const newPlans = [ ...plans ];
		const newFeatures = newPlans[ planIndex ].features.filter(
			( _, i ) => i !== featureIndex
		);
		newPlans[ planIndex ] = { ...newPlans[ planIndex ], features: newFeatures };
		setAttributes( { plans: newPlans } );
	};

	const addPlan = () => {
		if ( plans.length >= 3 ) return;
		const newPlan = {
			title: 'New Plan',
			subtitle: 'Plan description',
			monthlyPrice: '0',
			yearlyPrice: '0',
			pricePrefix: '$',
			priceSuffix: '',
			features: [ 'Feature 1', 'Feature 2', 'Feature 3' ],
			buttonText: 'Get Started',
			buttonUrl: '#',
			highlighted: false,
		};
		setAttributes( { plans: [ ...plans, newPlan ] } );
		setExpandedPlan( plans.length );
	};

	const removePlan = ( index ) => {
		if ( plans.length <= 1 ) return;
		const newPlans = plans.filter( ( _, i ) => i !== index );
		setAttributes( { plans: newPlans } );
		if ( expandedPlan >= newPlans.length ) {
			setExpandedPlan( newPlans.length - 1 );
		}
	};

	const blockProps = useBlockProps( {
		className: `sfcore-pricing sfcore-pricing--cols-${ Math.min( plans.length, columns ) }`,
		style: {
			'--pricing-border-color': borderColor || undefined,
			'--pricing-highlight-bg': highlightedBackgroundColor || undefined,
			'--pricing-highlight-text': highlightedTextColor || undefined,
			'--pricing-text-color': textColor || undefined,
			'--pricing-toggle-bg': toggleBackgroundColor || undefined,
		},
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Layout Settings', 'swishfolio-core' ) }>
					<RangeControl
						label={ __( 'Max Columns', 'swishfolio-core' ) }
						value={ columns }
						onChange={ ( value ) => setAttributes( { columns: value } ) }
						min={ 1 }
						max={ 3 }
					/>
					<ToggleControl
						label={ __( 'Show Billing Toggle', 'swishfolio-core' ) }
						checked={ showToggle }
						onChange={ ( value ) => setAttributes( { showToggle: value } ) }
					/>
				</PanelBody>

				{ showToggle && (
					<PanelBody title={ __( 'Toggle Settings', 'swishfolio-core' ) } initialOpen={ false }>
						<TextControl
							label={ __( 'Monthly Label', 'swishfolio-core' ) }
							value={ toggleMonthlyLabel }
							onChange={ ( value ) => setAttributes( { toggleMonthlyLabel: value } ) }
						/>
						<TextControl
							label={ __( 'Yearly Label', 'swishfolio-core' ) }
							value={ toggleYearlyLabel }
							onChange={ ( value ) => setAttributes( { toggleYearlyLabel: value } ) }
						/>
						<TextControl
							label={ __( 'Yearly Discount Badge', 'swishfolio-core' ) }
							value={ yearlyDiscount }
							onChange={ ( value ) => setAttributes( { yearlyDiscount: value } ) }
						/>
						<ToggleControl
							label={ __( 'Default to Yearly', 'swishfolio-core' ) }
							checked={ defaultBilling === 'yearly' }
							onChange={ ( checked ) =>
								setAttributes( { defaultBilling: checked ? 'yearly' : 'monthly' } )
							}
						/>
					</PanelBody>
				) }

				<PanelBody title={ __( 'Colors', 'swishfolio-core' ) } initialOpen={ false }>
					<p className="components-base-control__label">
						{ __( 'Toggle Background', 'swishfolio-core' ) }
					</p>
					<ColorPalette
						colors={ themeColors }
						value={ toggleBackgroundColor }
						onChange={ ( color ) => setAttributes( { toggleBackgroundColor: color } ) }
						clearable
					/>
					<p className="components-base-control__label">
						{ __( 'Border Color', 'swishfolio-core' ) }
					</p>
					<ColorPalette
						colors={ themeColors }
						value={ borderColor }
						onChange={ ( color ) => setAttributes( { borderColor: color } ) }
						clearable
					/>
					<p className="components-base-control__label">
						{ __( 'Text Color', 'swishfolio-core' ) }
					</p>
					<ColorPalette
						colors={ themeColors }
						value={ textColor }
						onChange={ ( color ) => setAttributes( { textColor: color } ) }
						clearable
					/>
					<p className="components-base-control__label">
						{ __( 'Highlighted Background', 'swishfolio-core' ) }
					</p>
					<ColorPalette
						colors={ themeColors }
						value={ highlightedBackgroundColor }
						onChange={ ( color ) => setAttributes( { highlightedBackgroundColor: color } ) }
						clearable
					/>
					<p className="components-base-control__label">
						{ __( 'Highlighted Text Color', 'swishfolio-core' ) }
					</p>
					<ColorPalette
						colors={ themeColors }
						value={ highlightedTextColor }
						onChange={ ( color ) => setAttributes( { highlightedTextColor: color } ) }
						clearable
					/>
				</PanelBody>

				<PanelBody title={ __( 'Badge Settings', 'swishfolio-core' ) } initialOpen={ false }>
					<TextControl
						label={ __( 'Highlighted Badge Text', 'swishfolio-core' ) }
						value={ highlightedBadgeText }
						onChange={ ( value ) => setAttributes( { highlightedBadgeText: value } ) }
					/>
				</PanelBody>

				<PanelBody title={ __( 'Pricing Plans', 'swishfolio-core' ) } initialOpen={ true }>
					{ plans.map( ( plan, index ) => (
						<div key={ index } className="sfcore-pricing-plan-panel">
							<HStack
								className="sfcore-pricing-plan-header"
								onClick={ () => setExpandedPlan( expandedPlan === index ? -1 : index ) }
							>
								<span>{ plan.title || `Plan ${ index + 1 }` }</span>
								<Button
									icon={ expandedPlan === index ? 'arrow-up' : 'arrow-down' }
									label={ __( 'Toggle', 'swishfolio-core' ) }
									isSmall
								/>
							</HStack>
							{ expandedPlan === index && (
								<div className="sfcore-pricing-plan-content">
									<TextControl
										label={ __( 'Title', 'swishfolio-core' ) }
										value={ plan.title }
										onChange={ ( value ) => updatePlan( index, 'title', value ) }
									/>
									<TextControl
										label={ __( 'Subtitle', 'swishfolio-core' ) }
										value={ plan.subtitle }
										onChange={ ( value ) => updatePlan( index, 'subtitle', value ) }
									/>
									<HStack>
										<TextControl
											label={ __( 'Price Prefix', 'swishfolio-core' ) }
											value={ plan.pricePrefix }
											onChange={ ( value ) => updatePlan( index, 'pricePrefix', value ) }
										/>
									</HStack>
									<TextControl
										label={ __( 'Monthly Price', 'swishfolio-core' ) }
										value={ plan.monthlyPrice }
										onChange={ ( value ) => updatePlan( index, 'monthlyPrice', value ) }
									/>
									<TextControl
										label={ __( 'Yearly Price', 'swishfolio-core' ) }
										value={ plan.yearlyPrice }
										onChange={ ( value ) => updatePlan( index, 'yearlyPrice', value ) }
									/>
									<ToggleControl
										label={ __( 'Highlighted (Featured)', 'swishfolio-core' ) }
										checked={ plan.highlighted }
										onChange={ ( value ) => updatePlan( index, 'highlighted', value ) }
									/>
									<TextControl
										label={ __( 'Button Text', 'swishfolio-core' ) }
										value={ plan.buttonText }
										onChange={ ( value ) => updatePlan( index, 'buttonText', value ) }
									/>
									<TextControl
										label={ __( 'Button URL', 'swishfolio-core' ) }
										value={ plan.buttonUrl }
										onChange={ ( value ) => updatePlan( index, 'buttonUrl', value ) }
									/>
									<p className="components-base-control__label">
										{ __( 'Features', 'swishfolio-core' ) }
									</p>
									{ plan.features.map( ( feature, featureIndex ) => (
										<HStack key={ featureIndex } className="sfcore-pricing-feature-row">
											<TextControl
												value={ feature }
												onChange={ ( value ) =>
													updatePlanFeature( index, featureIndex, value )
												}
											/>
											<Button
												icon="trash"
												label={ __( 'Remove', 'swishfolio-core' ) }
												isDestructive
												isSmall
												onClick={ () => removeFeature( index, featureIndex ) }
											/>
										</HStack>
									) ) }
									<Button
										variant="secondary"
										isSmall
										onClick={ () => addFeature( index ) }
									>
										{ __( 'Add Feature', 'swishfolio-core' ) }
									</Button>
									{ plans.length > 1 && (
										<Button
											variant="secondary"
											isDestructive
											isSmall
											onClick={ () => removePlan( index ) }
											style={ { marginTop: '16px', display: 'block' } }
										>
											{ __( 'Remove This Plan', 'swishfolio-core' ) }
										</Button>
									) }
								</div>
							) }
						</div>
					) ) }
					{ plans.length < 3 && (
						<Button variant="primary" onClick={ addPlan } style={ { marginTop: '16px' } }>
							{ __( 'Add Plan', 'swishfolio-core' ) }
						</Button>
					) }
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				{ showToggle && (
					<div className="sfcore-pricing__toggle-wrapper">
						<div className="sfcore-pricing__toggle">
							<button
								type="button"
								className={ `sfcore-pricing__toggle-btn ${ billingPeriod === 'monthly' ? 'is-active' : '' }` }
								onClick={ () => setBillingPeriod( 'monthly' ) }
							>
								{ toggleMonthlyLabel }
							</button>
							<button
								type="button"
								className={ `sfcore-pricing__toggle-btn ${ billingPeriod === 'yearly' ? 'is-active' : '' }` }
								onClick={ () => setBillingPeriod( 'yearly' ) }
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
								<RichText
									tagName="h3"
									className="sfcore-pricing__title"
									value={ plan.title }
									onChange={ ( value ) => updatePlan( index, 'title', value ) }
									placeholder={ __( 'Plan Title', 'swishfolio-core' ) }
								/>
								<RichText
									tagName="p"
									className="sfcore-pricing__subtitle"
									value={ plan.subtitle }
									onChange={ ( value ) => updatePlan( index, 'subtitle', value ) }
									placeholder={ __( 'Plan description', 'swishfolio-core' ) }
								/>
							</div>
							<div className="sfcore-pricing__price">
								<span className="sfcore-pricing__price-prefix">{ plan.pricePrefix }</span>
								<span className="sfcore-pricing__price-amount">
									{ billingPeriod === 'monthly' ? plan.monthlyPrice : plan.yearlyPrice }
								</span>
								<span className="sfcore-pricing__price-period">
									/ { billingPeriod === 'monthly' ? __( 'month', 'swishfolio-core' ) : __( 'year', 'swishfolio-core' ) }
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
								<RichText
									tagName="span"
									className="sfcore-pricing__button"
									value={ plan.buttonText }
									onChange={ ( value ) => updatePlan( index, 'buttonText', value ) }
									placeholder={ __( 'Button Text', 'swishfolio-core' ) }
								/>
							</div>
						</div>
					) ) }
				</div>
			</div>
		</>
	);
}
