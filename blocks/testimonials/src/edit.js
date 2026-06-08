/**
 * Testimonials Block - Edit Component.
 *
 * Parent layout block. Renders a grid of testimonial-card children and emits
 * CSS custom properties (--sf-*) on the wrapper that the cards consume.
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
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
} from '@wordpress/components';
import InspectorTabs, {
	useInspectorTabs,
} from '../../shared/components/inspector-tabs';

const ALLOWED_BLOCKS = [ 'swishfolio-core/testimonial-card' ];

const TEMPLATE = [
	[ 'swishfolio-core/testimonial-card' ],
	[ 'swishfolio-core/testimonial-card' ],
	[ 'swishfolio-core/testimonial-card' ],
];

const SHADOWS = {
	none: 'none',
	small: '0 1px 2px rgba(0,0,0,0.06), 0 1px 3px rgba(0,0,0,0.08)',
	medium: '0 4px 6px -1px rgba(0,0,0,0.08), 0 2px 4px -1px rgba(0,0,0,0.06)',
	large: '0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04)',
};

export default function Edit( { attributes, setAttributes } ) {
	const [ activeTab, setActiveTab ] = useInspectorTabs();
	const themeColors = useSetting( 'color.palette' ) || [];

	const {
		columns,
		cardBackgroundColor,
		cardBorderColor,
		cardBorderWidth,
		cardBorderRadius,
		cardShadow,
		textColor,
		roleColor,
	} = attributes;

	const blockProps = useBlockProps( {
		className: 'sfcore-testimonials',
		style: {
			'--sf-columns': columns,
			'--sf-card-bg':
				cardBackgroundColor ||
				'var(--wp--preset--color--accent-2)',
			'--sf-card-border-color': cardBorderColor || 'transparent',
			'--sf-card-border-width': `${ cardBorderWidth }px`,
			'--sf-card-radius': `${ cardBorderRadius }px`,
			'--sf-card-shadow': SHADOWS[ cardShadow ] || 'none',
			'--sf-card-text':
				textColor || 'var(--wp--preset--color--contrast)',
			'--sf-role-color':
				roleColor || 'var(--wp--preset--color--accent-3)',
		},
	} );

	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'sfcore-testimonials__grid' },
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
							max={ 4 }
						/>
					</PanelBody>
				) }

				{ activeTab === 'style' && (
					<PanelBody title={ __( 'Card', 'swishfolio-core' ) }>
						<p className="components-base-control__label">
							{ __( 'Background', 'swishfolio-core' ) }
						</p>
						<ColorPalette
							colors={ themeColors }
							value={ cardBackgroundColor }
							onChange={ ( color ) =>
								setAttributes( {
									cardBackgroundColor: color || '',
								} )
							}
							clearable
						/>

						<p className="components-base-control__label">
							{ __( 'Border Color', 'swishfolio-core' ) }
						</p>
						<ColorPalette
							colors={ themeColors }
							value={ cardBorderColor }
							onChange={ ( color ) =>
								setAttributes( {
									cardBorderColor: color || '',
								} )
							}
							clearable
						/>

						<RangeControl
							label={ __( 'Border Width (px)', 'swishfolio-core' ) }
							value={ cardBorderWidth }
							onChange={ ( value ) =>
								setAttributes( { cardBorderWidth: value } )
							}
							min={ 0 }
							max={ 8 }
						/>

						<RangeControl
							label={ __( 'Border Radius (px)', 'swishfolio-core' ) }
							value={ cardBorderRadius }
							onChange={ ( value ) =>
								setAttributes( { cardBorderRadius: value } )
							}
							min={ 0 }
							max={ 40 }
						/>

						<ToggleGroupControl
							label={ __( 'Shadow', 'swishfolio-core' ) }
							value={ cardShadow }
							onChange={ ( value ) =>
								setAttributes( { cardShadow: value } )
							}
							isBlock
						>
							<ToggleGroupControlOption
								value="none"
								label={ __( 'None', 'swishfolio-core' ) }
							/>
							<ToggleGroupControlOption
								value="small"
								label={ __( 'S', 'swishfolio-core' ) }
							/>
							<ToggleGroupControlOption
								value="medium"
								label={ __( 'M', 'swishfolio-core' ) }
							/>
							<ToggleGroupControlOption
								value="large"
								label={ __( 'L', 'swishfolio-core' ) }
							/>
						</ToggleGroupControl>

						<p className="components-base-control__label">
							{ __( 'Text Color', 'swishfolio-core' ) }
						</p>
						<ColorPalette
							colors={ themeColors }
							value={ textColor }
							onChange={ ( color ) =>
								setAttributes( { textColor: color || '' } )
							}
							clearable
						/>

						<p className="components-base-control__label">
							{ __( 'Role Color', 'swishfolio-core' ) }
						</p>
						<ColorPalette
							colors={ themeColors }
							value={ roleColor }
							onChange={ ( color ) =>
								setAttributes( { roleColor: color || '' } )
							}
							clearable
						/>
					</PanelBody>
				) }
			</InspectorControls>

			<div { ...blockProps }>
				<div { ...innerBlocksProps } />
			</div>
		</>
	);
}
