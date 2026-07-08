/**
 * Pricing Table - Edit Component.
 *
 * Parent layout block (see ADR-0002). Holds a row of pricing-plan inner
 * blocks (max 4) and owns the billing toggle plus shared colours, emitted
 * as CSS custom properties on the wrapper. Prices in the editor preview the
 * default billing mode; the toggle is static here and only interactive on
 * the frontend (view.js).
 */

import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
	InnerBlocks,
	useSetting,
} from '@wordpress/block-editor';
import {
	PanelBody,
	ToggleControl,
	TextControl,
	ColorPalette,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import InspectorTabs, {
	useInspectorTabs,
} from '../../shared/components/inspector-tabs';

const ALLOWED_BLOCKS = [ 'swishfolio-core/pricing-plan' ];

const MAX_PLANS = 4;

const TEMPLATE = [
	[ 'swishfolio-core/pricing-plan' ],
	[ 'swishfolio-core/pricing-plan' ],
	[ 'swishfolio-core/pricing-plan' ],
];

export default function Edit( { attributes, setAttributes, clientId } ) {
	const [ activeTab, setActiveTab ] = useInspectorTabs();
	const themeColors = useSetting( 'color.palette' ) || [];

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

	const planCount = useSelect(
		( select ) =>
			select( 'core/block-editor' ).getBlockCount( clientId ),
		[ clientId ]
	);

	const blockProps = useBlockProps( {
		className: 'sfcore-pricing',
		style: {
			'--pricing-border-color': borderColor || undefined,
			'--pricing-highlight-bg': highlightedBackgroundColor || undefined,
			'--pricing-highlight-text': highlightedTextColor || undefined,
			'--pricing-text-color': textColor || undefined,
			'--pricing-toggle-bg': toggleBackgroundColor || undefined,
		},
	} );

	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'sfcore-pricing__grid' },
		{
			allowedBlocks: ALLOWED_BLOCKS,
			template: TEMPLATE,
			orientation: 'horizontal',
			renderAppender:
				planCount < MAX_PLANS
					? InnerBlocks.ButtonBlockAppender
					: false,
		}
	);

	return (
		<>
			<InspectorControls>
				<InspectorTabs
					activeTab={ activeTab }
					setActiveTab={ setActiveTab }
					allowedTabs={ [ 'general', 'style' ] }
				/>

				{ activeTab === 'general' && (
					<PanelBody title={ __( 'Billing', 'swishfolio-core' ) }>
						<ToggleControl
							label={ __( 'Show Billing Toggle', 'swishfolio-core' ) }
							checked={ showToggle }
							onChange={ ( value ) => setAttributes( { showToggle: value } ) }
						/>

						{ showToggle && (
							<>
								<TextControl
									label={ __( 'Monthly Label', 'swishfolio-core' ) }
									value={ toggleMonthlyLabel }
									onChange={ ( value ) =>
										setAttributes( { toggleMonthlyLabel: value } )
									}
								/>
								<TextControl
									label={ __( 'Yearly Label', 'swishfolio-core' ) }
									value={ toggleYearlyLabel }
									onChange={ ( value ) =>
										setAttributes( { toggleYearlyLabel: value } )
									}
								/>
								<TextControl
									label={ __( 'Yearly Discount Badge', 'swishfolio-core' ) }
									value={ yearlyDiscount }
									onChange={ ( value ) =>
										setAttributes( { yearlyDiscount: value } )
									}
								/>
							</>
						) }

						<ToggleControl
							label={ __( 'Default to Yearly', 'swishfolio-core' ) }
							checked={ defaultBilling === 'yearly' }
							onChange={ ( checked ) =>
								setAttributes( {
									defaultBilling: checked ? 'yearly' : 'monthly',
								} )
							}
						/>

						<TextControl
							label={ __( 'Monthly Period Label', 'swishfolio-core' ) }
							help={ __( 'Shown after the price, e.g. "$5/month".', 'swishfolio-core' ) }
							value={ monthlyPeriodLabel }
							onChange={ ( value ) =>
								setAttributes( { monthlyPeriodLabel: value } )
							}
						/>
						<TextControl
							label={ __( 'Yearly Period Label', 'swishfolio-core' ) }
							value={ yearlyPeriodLabel }
							onChange={ ( value ) =>
								setAttributes( { yearlyPeriodLabel: value } )
							}
						/>
					</PanelBody>
				) }

				{ activeTab === 'style' && (
					<PanelBody title={ __( 'Colors', 'swishfolio-core' ) }>
						<p className="components-base-control__label">
							{ __( 'Toggle Background', 'swishfolio-core' ) }
						</p>
						<ColorPalette
							colors={ themeColors }
							value={ toggleBackgroundColor }
							onChange={ ( color ) =>
								setAttributes( { toggleBackgroundColor: color } )
							}
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
							onChange={ ( color ) =>
								setAttributes( { highlightedBackgroundColor: color } )
							}
							clearable
						/>
						<p className="components-base-control__label">
							{ __( 'Highlighted Text Color', 'swishfolio-core' ) }
						</p>
						<ColorPalette
							colors={ themeColors }
							value={ highlightedTextColor }
							onChange={ ( color ) =>
								setAttributes( { highlightedTextColor: color } )
							}
							clearable
						/>
					</PanelBody>
				) }
			</InspectorControls>

			<div { ...blockProps }>
				{ showToggle && (
					<div className="sfcore-pricing__toggle-wrapper">
						<div className="sfcore-pricing__toggle">
							<button
								type="button"
								className={ `sfcore-pricing__toggle-btn ${ defaultBilling === 'monthly' ? 'is-active' : '' }` }
							>
								{ toggleMonthlyLabel }
							</button>
							<button
								type="button"
								className={ `sfcore-pricing__toggle-btn ${ defaultBilling === 'yearly' ? 'is-active' : '' }` }
							>
								{ toggleYearlyLabel }
								{ yearlyDiscount && (
									<span className="sfcore-pricing__discount-badge">
										{ yearlyDiscount }
									</span>
								) }
							</button>
						</div>
					</div>
				) }

				<div { ...innerBlocksProps } />
			</div>
		</>
	);
}
