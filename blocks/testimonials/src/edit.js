/**
 * Testimonials Block - Edit Component
 */

import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	RangeControl,
	ToggleControl,
	Spinner,
	ColorPalette,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

const BAUHAUS_COLORS = [
	{ name: 'Primary Red', color: '#D02020' },
	{ name: 'Primary Blue', color: '#1040C0' },
	{ name: 'Primary Yellow', color: '#F0C020' },
	{ name: 'Contrast', color: '#121212' },
	{ name: 'Base', color: '#F0F0F0' },
	{ name: 'White', color: '#FFFFFF' },
];

export default function Edit( { attributes, setAttributes } ) {
	const {
		displayMode,
		columns,
		postsPerPage,
		showRating,
		showCompany,
		showAvatar,
		autoplay,
		autoplaySpeed,
		cardStyle,
		cardBackgroundColor,
		cardBorderColor,
		cardBorderWidth,
		cardBorderRadius,
		cardShadow,
		textColor,
	} = attributes;

	const blockProps = useBlockProps();

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Layout', 'swishfolio-core' ) }>
					<ToggleGroupControl
						label={ __( 'Display Mode', 'swishfolio-core' ) }
						value={ displayMode }
						onChange={ ( value ) => setAttributes( { displayMode: value } ) }
						isBlock
					>
						<ToggleGroupControlOption value="grid" label={ __( 'Grid', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="carousel" label={ __( 'Carousel', 'swishfolio-core' ) } />
					</ToggleGroupControl>

					<ToggleGroupControl
						label={ __( 'Card Style', 'swishfolio-core' ) }
						value={ cardStyle }
						onChange={ ( value ) => setAttributes( { cardStyle: value } ) }
						isBlock
					>
						<ToggleGroupControlOption value="card" label={ __( 'Card', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="bubble" label={ __( 'Bubble', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="minimal" label={ __( 'Minimal', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="centered" label={ __( 'Centered', 'swishfolio-core' ) } />
					</ToggleGroupControl>

					{ displayMode === 'grid' && (
						<RangeControl
							label={ __( 'Columns', 'swishfolio-core' ) }
							value={ columns }
							onChange={ ( value ) => setAttributes( { columns: value } ) }
							min={ 1 }
							max={ 4 }
						/>
					) }

					<RangeControl
						label={ __( 'Testimonials to Show', 'swishfolio-core' ) }
						value={ postsPerPage }
						onChange={ ( value ) => setAttributes( { postsPerPage: value } ) }
						min={ 1 }
						max={ 12 }
					/>
				</PanelBody>

				<PanelBody title={ __( 'Content', 'swishfolio-core' ) } initialOpen={ false }>
					<ToggleControl
						label={ __( 'Show Rating', 'swishfolio-core' ) }
						checked={ showRating }
						onChange={ ( value ) => setAttributes( { showRating: value } ) }
					/>

					<ToggleControl
						label={ __( 'Show Avatar', 'swishfolio-core' ) }
						checked={ showAvatar }
						onChange={ ( value ) => setAttributes( { showAvatar: value } ) }
					/>

					<ToggleControl
						label={ __( 'Show Company', 'swishfolio-core' ) }
						checked={ showCompany }
						onChange={ ( value ) => setAttributes( { showCompany: value } ) }
					/>
				</PanelBody>

				<PanelBody title={ __( 'Style', 'swishfolio-core' ) } initialOpen={ false }>
					<p className="components-base-control__label">
						{ __( 'Card Background Color', 'swishfolio-core' ) }
					</p>
					<ColorPalette
						colors={ BAUHAUS_COLORS }
						value={ cardBackgroundColor }
						onChange={ ( color ) => setAttributes( { cardBackgroundColor: color || '' } ) }
						clearable
					/>

					<p className="components-base-control__label">
						{ __( 'Card Border Color', 'swishfolio-core' ) }
					</p>
					<ColorPalette
						colors={ BAUHAUS_COLORS }
						value={ cardBorderColor }
						onChange={ ( color ) => setAttributes( { cardBorderColor: color || '' } ) }
						clearable
					/>

					<RangeControl
						label={ __( 'Border Width (px)', 'swishfolio-core' ) }
						value={ cardBorderWidth }
						onChange={ ( value ) => setAttributes( { cardBorderWidth: value } ) }
						min={ 0 }
						max={ 8 }
					/>

					<RangeControl
						label={ __( 'Border Radius (px)', 'swishfolio-core' ) }
						value={ cardBorderRadius }
						onChange={ ( value ) => setAttributes( { cardBorderRadius: value } ) }
						min={ 0 }
						max={ 24 }
					/>

					<ToggleGroupControl
						label={ __( 'Shadow', 'swishfolio-core' ) }
						value={ cardShadow }
						onChange={ ( value ) => setAttributes( { cardShadow: value } ) }
						isBlock
					>
						<ToggleGroupControlOption value="none" label={ __( 'None', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="small" label={ __( 'S', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="medium" label={ __( 'M', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="large" label={ __( 'L', 'swishfolio-core' ) } />
					</ToggleGroupControl>

					<p className="components-base-control__label">
						{ __( 'Text Color', 'swishfolio-core' ) }
					</p>
					<ColorPalette
						colors={ BAUHAUS_COLORS }
						value={ textColor }
						onChange={ ( color ) => setAttributes( { textColor: color || '' } ) }
						clearable
					/>
				</PanelBody>

				{ displayMode === 'carousel' && (
					<PanelBody title={ __( 'Carousel Settings', 'swishfolio-core' ) } initialOpen={ false }>
						<ToggleControl
							label={ __( 'Autoplay', 'swishfolio-core' ) }
							checked={ autoplay }
							onChange={ ( value ) => setAttributes( { autoplay: value } ) }
						/>

						{ autoplay && (
							<RangeControl
								label={ __( 'Autoplay Speed (ms)', 'swishfolio-core' ) }
								value={ autoplaySpeed }
								onChange={ ( value ) => setAttributes( { autoplaySpeed: value } ) }
								min={ 2000 }
								max={ 10000 }
								step={ 500 }
							/>
						) }
					</PanelBody>
				) }
			</InspectorControls>

			<div { ...blockProps }>
				<ServerSideRender
					block="swishfolio-core/testimonials"
					attributes={ attributes }
					LoadingResponsePlaceholder={ () => (
						<div className="sfcore-testimonials__loading">
							<Spinner />
						</div>
					) }
					EmptyResponsePlaceholder={ () => (
						<div className="sfcore-testimonials__empty">
							<p>{ __( 'No testimonials found. Add some testimonials to display them here.', 'swishfolio-core' ) }</p>
						</div>
					) }
				/>
			</div>
		</>
	);
}
