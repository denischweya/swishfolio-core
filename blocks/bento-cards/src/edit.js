/**
 * Bento Cards - Edit Component.
 *
 * Parent layout block. Holds a grid of bento-card inner blocks and emits
 * CSS custom properties (--grid-gap, --overlay-*, --accent-color) used by
 * both parent and card styles.
 */

import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
	URLInput,
	useSetting,
} from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	ColorPalette,
	RangeControl,
	TextControl,
} from '@wordpress/components';
import InspectorTabs, {
	useInspectorTabs,
} from '../../shared/components/inspector-tabs';

const ALLOWED_BLOCKS = [ 'swishfolio-core/bento-card' ];

const TEMPLATE = [
	[ 'swishfolio-core/bento-card', { cardSize: 'full' } ],
	[ 'swishfolio-core/bento-card', { cardSize: 'two-thirds' } ],
	[ 'swishfolio-core/bento-card', { cardSize: 'one-third' } ],
];

export default function Edit( { attributes, setAttributes } ) {
	const [ activeTab, setActiveTab ] = useInspectorTabs();
	const themeColors = useSetting( 'color.palette' ) || [];

	const {
		gridGap,
		cardOverlayColor,
		cardOverlayOpacity,
		accentColor,
		ctaType,
		ctaText,
		ctaUrl,
		ctaTextColor,
		ctaBgColor,
	} = attributes;

	const blockProps = useBlockProps( {
		className: 'sfcore-bento',
		style: {
			'--grid-gap': gridGap,
			'--overlay-color': cardOverlayColor || 'rgba(6, 26, 20, 0.85)',
			'--overlay-opacity': cardOverlayOpacity / 100,
			'--accent-color': accentColor || '#FFE500',
		},
	} );

	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'sfcore-bento__grid' },
		{
			allowedBlocks: ALLOWED_BLOCKS,
			template: TEMPLATE,
			orientation: 'horizontal',
		}
	);

	return (
		<>
			<InspectorControls>
				<InspectorTabs
					activeTab={ activeTab }
					setActiveTab={ setActiveTab }
				/>

				{ activeTab === 'general' && (
					<>
						<PanelBody title={ __( 'Layout', 'swishfolio-core' ) }>
							<SelectControl
								label={ __( 'Grid Gap', 'swishfolio-core' ) }
								value={ gridGap }
								options={ [
									{ label: __( 'Small (1rem)', 'swishfolio-core' ), value: '1rem' },
									{ label: __( 'Medium (1.5rem)', 'swishfolio-core' ), value: '1.5rem' },
									{ label: __( 'Large (2rem)', 'swishfolio-core' ), value: '2rem' },
									{ label: __( 'Extra Large (3rem)', 'swishfolio-core' ), value: '3rem' },
								] }
								onChange={ ( value ) => setAttributes( { gridGap: value } ) }
							/>
						</PanelBody>

						<PanelBody
							title={ __( 'Call to Action', 'swishfolio-core' ) }
							initialOpen={ false }
						>
							<SelectControl
								label={ __( 'CTA Type', 'swishfolio-core' ) }
								value={ ctaType }
								options={ [
									{ label: __( 'None', 'swishfolio-core' ), value: 'none' },
									{ label: __( 'Link', 'swishfolio-core' ), value: 'link' },
									{ label: __( 'Button', 'swishfolio-core' ), value: 'button' },
								] }
								onChange={ ( value ) => setAttributes( { ctaType: value } ) }
							/>

							{ ctaType !== 'none' && (
								<>
									<TextControl
										label={ __( 'CTA Text', 'swishfolio-core' ) }
										value={ ctaText }
										onChange={ ( value ) => setAttributes( { ctaText: value } ) }
									/>
									<URLInput
										label={ __( 'CTA URL', 'swishfolio-core' ) }
										value={ ctaUrl }
										onChange={ ( url ) => setAttributes( { ctaUrl: url } ) }
									/>
								</>
							) }
						</PanelBody>
					</>
				) }

				{ activeTab === 'style' && (
					<>
						<PanelBody title={ __( 'Colors', 'swishfolio-core' ) }>
							<p className="components-base-control__label">
								{ __( 'Accent Color', 'swishfolio-core' ) }
							</p>
							<ColorPalette
								colors={ themeColors }
								value={ accentColor }
								onChange={ ( color ) => setAttributes( { accentColor: color || '' } ) }
								clearable
							/>

							<p className="components-base-control__label" style={ { marginTop: '16px' } }>
								{ __( 'Card Overlay Color', 'swishfolio-core' ) }
							</p>
							<ColorPalette
								colors={ themeColors }
								value={ cardOverlayColor }
								onChange={ ( color ) => setAttributes( { cardOverlayColor: color || '' } ) }
								clearable
							/>

							<RangeControl
								label={ __( 'Overlay Opacity', 'swishfolio-core' ) }
								value={ cardOverlayOpacity }
								onChange={ ( value ) => setAttributes( { cardOverlayOpacity: value } ) }
								min={ 0 }
								max={ 100 }
							/>
						</PanelBody>

						{ ctaType !== 'none' && (
							<PanelBody
								title={ __( 'CTA Colors', 'swishfolio-core' ) }
								initialOpen={ false }
							>
								<p className="components-base-control__label">
									{ __( 'Text Color', 'swishfolio-core' ) }
								</p>
								<ColorPalette
									colors={ themeColors }
									value={ ctaTextColor }
									onChange={ ( color ) => setAttributes( { ctaTextColor: color || '' } ) }
									clearable
								/>

								{ ctaType === 'button' && (
									<>
										<p className="components-base-control__label" style={ { marginTop: '12px' } }>
											{ __( 'Background Color', 'swishfolio-core' ) }
										</p>
										<ColorPalette
											colors={ themeColors }
											value={ ctaBgColor }
											onChange={ ( color ) => setAttributes( { ctaBgColor: color || '' } ) }
											clearable
										/>
									</>
								) }
							</PanelBody>
						) }
					</>
				) }
			</InspectorControls>

			<div { ...blockProps }>
				<div { ...innerBlocksProps } />

				{ ctaType !== 'none' && ctaText && (
					<div className="sfcore-bento__cta-wrapper">
						<span
							className={ `sfcore-bento__cta sfcore-bento__cta--${ ctaType }` }
							style={ {
								color: ctaTextColor || undefined,
								backgroundColor:
									ctaType === 'button' ? ctaBgColor || undefined : undefined,
							} }
						>
							{ ctaText }
						</span>
					</div>
				) }
			</div>
		</>
	);
}
