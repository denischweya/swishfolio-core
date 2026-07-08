/**
 * Pricing Plan - Edit Component.
 *
 * Inline-editable title/subtitle/button text; features edited via the
 * locked core/list inner block. Prices, price link, highlight, and button
 * URL live in the inspector. The price preview shows the billing mode and
 * period label read from the parent via block context.
 */

import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	RichText,
	InspectorControls,
	URLInput,
} from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import InspectorTabs, {
	useInspectorTabs,
} from '../../shared/components/inspector-tabs';

const FEATURES_TEMPLATE = [ [ 'core/list', {}, [ [ 'core/list-item' ] ] ] ];

export default function Edit( { attributes, setAttributes, context } ) {
	const [ activeTab, setActiveTab ] = useInspectorTabs();

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

	const billing =
		context[ 'swishfolio-core/pricing/defaultBilling' ] || 'monthly';
	const periodLabel =
		billing === 'monthly'
			? context[ 'swishfolio-core/pricing/monthlyPeriodLabel' ] || 'month'
			: context[ 'swishfolio-core/pricing/yearlyPeriodLabel' ] || 'year';
	const price = billing === 'monthly' ? monthlyPrice : yearlyPrice;

	const blockProps = useBlockProps( {
		className: `sfcore-pricing__plan ${ highlighted ? 'sfcore-pricing__plan--highlighted' : '' }`,
	} );

	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'sfcore-pricing__features' },
		{
			allowedBlocks: [ 'core/list' ],
			template: FEATURES_TEMPLATE,
			templateLock: 'all',
		}
	);

	const hasPriceLink = priceLinkText && priceLinkUrl;

	return (
		<>
			<InspectorControls>
				<InspectorTabs
					activeTab={ activeTab }
					setActiveTab={ setActiveTab }
					allowedTabs={ [ 'general' ] }
				/>

				{ activeTab === 'general' && (
					<>
						<PanelBody title={ __( 'Price', 'swishfolio-core' ) }>
							<TextControl
								label={ __( 'Price Prefix', 'swishfolio-core' ) }
								value={ pricePrefix }
								onChange={ ( value ) =>
									setAttributes( { pricePrefix: value } )
								}
							/>
							<TextControl
								label={ __( 'Monthly Price', 'swishfolio-core' ) }
								value={ monthlyPrice }
								onChange={ ( value ) =>
									setAttributes( { monthlyPrice: value } )
								}
							/>
							<TextControl
								label={ __( 'Yearly Price', 'swishfolio-core' ) }
								value={ yearlyPrice }
								onChange={ ( value ) =>
									setAttributes( { yearlyPrice: value } )
								}
							/>
						</PanelBody>

						<PanelBody
							title={ __( 'Price Link', 'swishfolio-core' ) }
							initialOpen={ false }
						>
							<TextControl
								label={ __( 'Text', 'swishfolio-core' ) }
								help={ __(
									'Replaces the price in both billing modes, e.g. "Contact Sales".',
									'swishfolio-core'
								) }
								value={ priceLinkText }
								onChange={ ( value ) =>
									setAttributes( { priceLinkText: value } )
								}
							/>
							<URLInput
								label={ __( 'URL', 'swishfolio-core' ) }
								value={ priceLinkUrl }
								onChange={ ( url ) =>
									setAttributes( { priceLinkUrl: url } )
								}
							/>
						</PanelBody>

						<PanelBody
							title={ __( 'Highlight', 'swishfolio-core' ) }
							initialOpen={ false }
						>
							<ToggleControl
								label={ __( 'Highlighted (Featured)', 'swishfolio-core' ) }
								checked={ !! highlighted }
								onChange={ ( value ) =>
									setAttributes( { highlighted: value } )
								}
							/>
							{ highlighted && (
								<TextControl
									label={ __( 'Badge Text', 'swishfolio-core' ) }
									value={ badgeText }
									onChange={ ( value ) =>
										setAttributes( { badgeText: value } )
									}
								/>
							) }
						</PanelBody>

						<PanelBody
							title={ __( 'CTA', 'swishfolio-core' ) }
							initialOpen={ false }
						>
							<URLInput
								label={ __( 'Button URL', 'swishfolio-core' ) }
								value={ buttonUrl }
								onChange={ ( url ) =>
									setAttributes( { buttonUrl: url } )
								}
							/>
						</PanelBody>
					</>
				) }
			</InspectorControls>

			<div { ...blockProps }>
				{ highlighted && badgeText && (
					<div className="sfcore-pricing__badge">{ badgeText }</div>
				) }

				<div className="sfcore-pricing__header">
					<RichText
						tagName="h3"
						className="sfcore-pricing__title"
						value={ title }
						onChange={ ( value ) => setAttributes( { title: value } ) }
						placeholder={ __( 'Plan Title', 'swishfolio-core' ) }
					/>
					<RichText
						tagName="p"
						className="sfcore-pricing__subtitle"
						value={ subtitle }
						onChange={ ( value ) => setAttributes( { subtitle: value } ) }
						placeholder={ __( 'Plan description', 'swishfolio-core' ) }
					/>
				</div>

				<div className="sfcore-pricing__price">
					{ hasPriceLink ? (
						<span className="sfcore-pricing__price-link">
							{ priceLinkText }
						</span>
					) : (
						<span className="sfcore-pricing__price-row">
							{ price && (
								<>
									<span className="sfcore-pricing__price-prefix">
										{ pricePrefix }
									</span>
									<span className="sfcore-pricing__price-amount">
										{ price }
									</span>
									<span className="sfcore-pricing__price-period">
										/{ periodLabel }
									</span>
								</>
							) }
						</span>
					) }
				</div>

				<div { ...innerBlocksProps } />

				<div className="sfcore-pricing__cta">
					<RichText
						tagName="span"
						className="sfcore-pricing__button"
						value={ buttonText }
						onChange={ ( value ) => setAttributes( { buttonText: value } ) }
						placeholder={ __( 'Button Text', 'swishfolio-core' ) }
					/>
				</div>
			</div>
		</>
	);
}
