/**
 * Swish Counter Block - Edit Component.
 *
 * Parent layout block. Renders a grid of swish-counter-item children and
 * emits CSS custom properties (--sf-counter-*) on the wrapper.
 */

import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
	useSetting,
} from '@wordpress/block-editor';
import {
	PanelBody,
	RangeControl,
	ColorPalette,
	FontSizePicker,
	SelectControl,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
} from '@wordpress/components';
import InspectorTabs, {
	useInspectorTabs,
} from '../../shared/components/inspector-tabs';

const ALLOWED_BLOCKS = [ 'swishfolio-core/swish-counter-item' ];

const TEMPLATE = [
	[
		'swishfolio-core/swish-counter-item',
		{ value: '100', title: 'Projects' },
	],
	[
		'swishfolio-core/swish-counter-item',
		{ value: '50', title: 'Clients' },
	],
	[
		'swishfolio-core/swish-counter-item',
		{ value: '10', title: 'Years' },
	],
	[
		'swishfolio-core/swish-counter-item',
		{ value: '5', title: 'Awards' },
	],
];

const FONT_WEIGHTS = [
	{ label: '400', value: 400 },
	{ label: '500', value: 500 },
	{ label: '600', value: 600 },
	{ label: '700', value: 700 },
	{ label: '800', value: 800 },
	{ label: '900', value: 900 },
];

export default function Edit( { attributes, setAttributes } ) {
	const [ activeTab, setActiveTab ] = useInspectorTabs();
	const themeColors = useSetting( 'color.palette' ) || [];
	const themeFontSizes = useSetting( 'typography.fontSizes' ) || [];

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

	const blockProps = useBlockProps( {
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

	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'sfcore-swish-counter__grid' },
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
						<RangeControl
							label={ __( 'Columns', 'swishfolio-core' ) }
							value={ columns }
							onChange={ ( value ) =>
								setAttributes( { columns: value } )
							}
							min={ 1 }
							max={ 6 }
						/>

						<ToggleGroupControl
							label={ __( 'Alignment', 'swishfolio-core' ) }
							value={ align }
							onChange={ ( value ) =>
								setAttributes( { align: value } )
							}
							isBlock
						>
							<ToggleGroupControlOption
								value="left"
								label={ __( 'Left', 'swishfolio-core' ) }
							/>
							<ToggleGroupControlOption
								value="center"
								label={ __( 'Center', 'swishfolio-core' ) }
							/>
							<ToggleGroupControlOption
								value="right"
								label={ __( 'Right', 'swishfolio-core' ) }
							/>
						</ToggleGroupControl>
					</PanelBody>
				) }

				{ activeTab === 'style' && (
					<>
						<PanelBody title={ __( 'Counter', 'swishfolio-core' ) }>
							<FontSizePicker
								fontSizes={ themeFontSizes }
								value={ counterFontSize }
								onChange={ ( size ) =>
									setAttributes( {
										counterFontSize: size || '',
									} )
								}
							/>

							<SelectControl
								label={ __( 'Font Weight', 'swishfolio-core' ) }
								value={ counterFontWeight }
								options={ FONT_WEIGHTS }
								onChange={ ( value ) =>
									setAttributes( {
										counterFontWeight: parseInt( value, 10 ),
									} )
								}
							/>

							<p className="components-base-control__label">
								{ __( 'Color', 'swishfolio-core' ) }
							</p>
							<ColorPalette
								colors={ themeColors }
								value={ counterColor }
								onChange={ ( color ) =>
									setAttributes( {
										counterColor: color || '',
									} )
								}
								clearable
							/>
						</PanelBody>

						<PanelBody
							title={ __( 'Title', 'swishfolio-core' ) }
							initialOpen={ false }
						>
							<FontSizePicker
								fontSizes={ themeFontSizes }
								value={ titleFontSize }
								onChange={ ( size ) =>
									setAttributes( {
										titleFontSize: size || '',
									} )
								}
							/>

							<p className="components-base-control__label">
								{ __( 'Color', 'swishfolio-core' ) }
							</p>
							<ColorPalette
								colors={ themeColors }
								value={ titleColor }
								onChange={ ( color ) =>
									setAttributes( {
										titleColor: color || '',
									} )
								}
								clearable
							/>
						</PanelBody>

						<PanelBody
							title={ __( 'Spacing', 'swishfolio-core' ) }
							initialOpen={ false }
						>
							<RangeControl
								label={ __(
									'Counter ↔ Title Gap (px)',
									'swishfolio-core'
								) }
								value={ counterTitleGap }
								onChange={ ( value ) =>
									setAttributes( {
										counterTitleGap: value,
									} )
								}
								min={ 0 }
								max={ 40 }
							/>
						</PanelBody>
					</>
				) }
			</InspectorControls>

			<div { ...blockProps }>
				<div { ...innerBlocksProps } />
			</div>
		</>
	);
}
