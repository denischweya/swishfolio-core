/**
 * Features Block - Edit Component.
 *
 * Parent block. Holds global styles inherited by its child feature-card
 * blocks via CSS custom properties (--features-*). Cards are added/edited
 * individually as their own selectable blocks.
 */

import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
	useSetting,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	ColorPalette,
	FontSizePicker,
	Button,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
} from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { parse } from '@wordpress/blocks';
import InspectorTabs, {
	useInspectorTabs,
} from '../../shared/components/inspector-tabs';

// Screenshot previews keyed by registered pattern name, provided from PHP
// via wp_localize_script (see Plugin::initBlockPatterns).
const PATTERN_PREVIEWS =
	( typeof window !== 'undefined' &&
		window.sfcoreFeaturesData &&
		window.sfcoreFeaturesData.patternPreviews ) ||
	{};

const ALLOWED_BLOCKS = [ 'swishfolio-core/feature-card' ];

const TEMPLATE = [
	[
		'swishfolio-core/feature-card',
		{ subheading: 'MODULE 01', title: 'Feature One', cardSize: 'medium' },
	],
	[
		'swishfolio-core/feature-card',
		{ subheading: 'MODULE 02', title: 'Feature Two', cardSize: 'medium' },
	],
	[
		'swishfolio-core/feature-card',
		{ subheading: 'MODULE 03', title: 'Feature Three', cardSize: 'medium' },
	],
];

export default function Edit( { attributes, setAttributes, clientId } ) {
	const [ activeTab, setActiveTab ] = useInspectorTabs();
	const themeColors = useSetting( 'color.palette' ) || [];
	const themeFontSizes = useSetting( 'typography.fontSizes' ) || [];

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

	// Patterns registered for this block (shown in the Advanced tab).
	const blockPatterns = useSelect(
		( select ) => {
			const store = select( blockEditorStore );
			const getByTypes =
				store.getPatternsByBlockTypes ||
				store.__experimentalGetPatternsByBlockTypes;
			if ( ! getByTypes ) {
				return [];
			}
			const rootClientId = select( blockEditorStore ).getBlockRootClientId(
				clientId
			);
			return getByTypes( 'swishfolio-core/features', rootClientId );
		},
		[ clientId ]
	);
	const { replaceBlocks } = useDispatch( blockEditorStore );

	const applyPattern = ( pattern ) => {
		const blocks = pattern.blocks || parse( pattern.content );
		if ( blocks && blocks.length ) {
			replaceBlocks( clientId, blocks );
		}
	};

	const blockProps = useBlockProps( {
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

	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'sfcore-features__grid' },
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
					<PanelBody title={ __( 'Layout', 'swishfolio-core' ) }>
						<SelectControl
							label={ __( 'Grid Gap', 'swishfolio-core' ) }
							value={ gridGap }
							options={ [
								{ label: __( 'Small (1rem)', 'swishfolio-core' ), value: '1rem' },
								{ label: __( 'Medium (2rem)', 'swishfolio-core' ), value: '2rem' },
								{ label: __( 'Large (3rem)', 'swishfolio-core' ), value: '3rem' },
							] }
							onChange={ ( value ) => setAttributes( { gridGap: value } ) }
						/>
					</PanelBody>
				) }

				{ activeTab === 'style' && (
					<>
						<PanelBody title={ __( 'Card Colors', 'swishfolio-core' ) }>
							<p className="components-base-control__label">
								{ __( 'Card Background', 'swishfolio-core' ) }
							</p>
							<ColorPalette
								colors={ themeColors }
								value={ cardBackgroundColor }
								onChange={ ( color ) => setAttributes( { cardBackgroundColor: color } ) }
								clearable
							/>

							<p className="components-base-control__label">
								{ __( 'Card Accent Color', 'swishfolio-core' ) }
							</p>
							<ColorPalette
								colors={ themeColors }
								value={ cardAccentColor }
								onChange={ ( color ) => setAttributes( { cardAccentColor: color } ) }
								clearable
							/>

							<ToggleGroupControl
								label={ __( 'Card Border Style', 'swishfolio-core' ) }
								value={ cardBorderStyle }
								onChange={ ( value ) => setAttributes( { cardBorderStyle: value } ) }
								isBlock
							>
								<ToggleGroupControlOption value="solid" label={ __( 'Solid', 'swishfolio-core' ) } />
								<ToggleGroupControlOption value="dashed" label={ __( 'Dashed', 'swishfolio-core' ) } />
								<ToggleGroupControlOption value="none" label={ __( 'None', 'swishfolio-core' ) } />
							</ToggleGroupControl>

							<p className="components-base-control__label">
								{ __( 'Card Border Color', 'swishfolio-core' ) }
							</p>
							<ColorPalette
								colors={ themeColors }
								value={ cardBorderColor }
								onChange={ ( color ) => setAttributes( { cardBorderColor: color } ) }
								clearable
							/>

							<p className="components-base-control__label">
								{ __( 'Card Shadow Color', 'swishfolio-core' ) }
							</p>
							<ColorPalette
								colors={ themeColors }
								value={ cardShadowColor }
								onChange={ ( color ) => setAttributes( { cardShadowColor: color } ) }
								clearable
							/>
						</PanelBody>

						<PanelBody
							title={ __( 'Typography', 'swishfolio-core' ) }
							initialOpen={ false }
						>
							<p className="components-base-control__label">
								{ __( 'Title Font Size', 'swishfolio-core' ) }
							</p>
							<FontSizePicker
								fontSizes={ themeFontSizes }
								value={ titleFontSize }
								onChange={ ( value ) => setAttributes( { titleFontSize: value } ) }
								withSlider
							/>

							<p className="components-base-control__label">
								{ __( 'Subheading Font Size', 'swishfolio-core' ) }
							</p>
							<FontSizePicker
								fontSizes={ themeFontSizes }
								value={ subheadingFontSize }
								onChange={ ( value ) => setAttributes( { subheadingFontSize: value } ) }
								withSlider
							/>

							<p className="components-base-control__label">
								{ __( 'Text Color', 'swishfolio-core' ) }
							</p>
							<ColorPalette
								colors={ themeColors }
								value={ titleColor }
								onChange={ ( color ) => setAttributes( { titleColor: color } ) }
								clearable
							/>
						</PanelBody>

						<PanelBody
							title={ __( 'Icon Colors', 'swishfolio-core' ) }
							initialOpen={ false }
						>
							<p className="components-base-control__label">
								{ __( 'Icon Color', 'swishfolio-core' ) }
							</p>
							<ColorPalette
								colors={ themeColors }
								value={ iconColor }
								onChange={ ( color ) => setAttributes( { iconColor: color } ) }
								clearable
							/>

							<p className="components-base-control__label">
								{ __( 'Icon Background', 'swishfolio-core' ) }
							</p>
							<ColorPalette
								colors={ themeColors }
								value={ iconBackgroundColor }
								onChange={ ( color ) => setAttributes( { iconBackgroundColor: color } ) }
								clearable
							/>
						</PanelBody>

						<PanelBody
							title={ __( 'CTA Colors', 'swishfolio-core' ) }
							initialOpen={ false }
						>
							<p className="components-base-control__label">
								{ __( 'CTA Text Color', 'swishfolio-core' ) }
							</p>
							<ColorPalette
								colors={ themeColors }
								value={ ctaTextColor }
								onChange={ ( color ) => setAttributes( { ctaTextColor: color } ) }
								clearable
							/>

							<p className="components-base-control__label">
								{ __( 'CTA Background Color', 'swishfolio-core' ) }
							</p>
							<ColorPalette
								colors={ themeColors }
								value={ ctaBackgroundColor }
								onChange={ ( color ) => setAttributes( { ctaBackgroundColor: color } ) }
								clearable
							/>
						</PanelBody>
					</>
				) }

				{ activeTab === 'advanced' && (
					<PanelBody title={ __( 'Block Patterns', 'swishfolio-core' ) }>
						{ blockPatterns.length === 0 && (
							<p className="components-base-control__help">
								{ __(
									'No patterns are registered for this block yet.',
									'swishfolio-core'
								) }
							</p>
						) }
						{ blockPatterns.map( ( pattern ) => {
							const preview = PATTERN_PREVIEWS[ pattern.name ];
							return (
								<Button
									key={ pattern.name }
									className="sfcore-pattern-preview"
									onClick={ () => applyPattern( pattern ) }
									label={ pattern.title || pattern.name }
									showTooltip
									style={ {
										display: 'block',
										width: '100%',
										height: 'auto',
										padding: 0,
										marginBottom: '12px',
									} }
								>
									{ preview ? (
										<img
											src={ preview }
											alt={ pattern.title || pattern.name }
											style={ {
												display: 'block',
												width: '100%',
												height: 'auto',
												borderRadius: '4px',
											} }
										/>
									) : (
										pattern.title || pattern.name
									) }
								</Button>
							);
						} ) }
					</PanelBody>
				) }
			</InspectorControls>

			<div { ...blockProps }>
				<div { ...innerBlocksProps } />
			</div>
		</>
	);
}
