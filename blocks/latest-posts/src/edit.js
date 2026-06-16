/**
 * Latest Posts Block - Edit Component
 */

import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
	useSetting,
} from '@wordpress/block-editor';
import {
	BaseControl,
	ColorPalette,
	PanelBody,
	RangeControl,
	ToggleControl,
	Spinner,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import ServerSideRender from '@wordpress/server-side-render';
import InspectorTabs, {
	useInspectorTabs,
} from '../../shared/components/inspector-tabs';

export default function Edit( { attributes, setAttributes } ) {
	const {
		columns,
		postsPerPage,
		categories,
		showDate,
		showAuthor,
		showExcerpt,
		excerptLength,
		showFeaturedImage,
		imageAspectRatio,
		imageBorderRadius,
		titleColor,
		excerptColor,
		metaColor,
		inheritQuery,
	} = attributes;

	const blockProps = useBlockProps();
	const [ activeTab, setActiveTab ] = useInspectorTabs();
	const themeColors = useSetting( 'color.palette' ) || [];

	// Fetch categories
	const postCategories = useSelect( ( select ) => {
		return select( 'core' ).getEntityRecords( 'taxonomy', 'category', {
			per_page: -1,
		} );
	}, [] );

	const categoryOptions = postCategories
		? postCategories.map( ( cat ) => ( {
				label: cat.name,
				value: cat.id,
		  } ) )
		: [];

	return (
		<>
			<InspectorControls>
				<InspectorTabs
					activeTab={ activeTab }
					setActiveTab={ setActiveTab }
					allowedTabs={ [ 'general', 'style' ] }
				/>

				{ activeTab === 'general' && (
					<>
						<PanelBody title={ __( 'Query', 'swishfolio-core' ) }>
							<ToggleControl
								label={ __( 'Inherit query from page', 'swishfolio-core' ) }
								help={
									inheritQuery
										? __( 'Posts come from the page’s main query (blog/archive pages). Pagination is shown automatically.', 'swishfolio-core' )
										: __( 'Turn on to use this block on a blog or archive page with pagination.', 'swishfolio-core' )
								}
								checked={ inheritQuery }
								onChange={ ( value ) => setAttributes( { inheritQuery: value } ) }
							/>
						</PanelBody>

						<PanelBody title={ __( 'Layout', 'swishfolio-core' ) }>
							<RangeControl
								label={ __( 'Columns', 'swishfolio-core' ) }
								value={ columns }
								onChange={ ( value ) => setAttributes( { columns: value } ) }
								min={ 1 }
								max={ 4 }
							/>

							{ ! inheritQuery && (
								<RangeControl
									label={ __( 'Posts to Show', 'swishfolio-core' ) }
									value={ postsPerPage }
									onChange={ ( value ) => setAttributes( { postsPerPage: value } ) }
									min={ 1 }
									max={ 12 }
								/>
							) }

							<ToggleGroupControl
								label={ __( 'Image Aspect Ratio', 'swishfolio-core' ) }
								value={ imageAspectRatio }
								onChange={ ( value ) => setAttributes( { imageAspectRatio: value } ) }
								isBlock
							>
								<ToggleGroupControlOption value="square" label={ __( 'Square', 'swishfolio-core' ) } />
								<ToggleGroupControlOption value="landscape" label={ __( 'Landscape', 'swishfolio-core' ) } />
								<ToggleGroupControlOption value="portrait" label={ __( 'Portrait', 'swishfolio-core' ) } />
							</ToggleGroupControl>
						</PanelBody>

						<PanelBody title={ __( 'Content', 'swishfolio-core' ) } initialOpen={ false }>
							<ToggleControl
								label={ __( 'Show Featured Image', 'swishfolio-core' ) }
								checked={ showFeaturedImage }
								onChange={ ( value ) => setAttributes( { showFeaturedImage: value } ) }
							/>

							<ToggleControl
								label={ __( 'Show Date', 'swishfolio-core' ) }
								checked={ showDate }
								onChange={ ( value ) => setAttributes( { showDate: value } ) }
							/>

							<ToggleControl
								label={ __( 'Show Author', 'swishfolio-core' ) }
								checked={ showAuthor }
								onChange={ ( value ) => setAttributes( { showAuthor: value } ) }
							/>

							<ToggleControl
								label={ __( 'Show Excerpt', 'swishfolio-core' ) }
								checked={ showExcerpt }
								onChange={ ( value ) => setAttributes( { showExcerpt: value } ) }
							/>

							{ showExcerpt && (
								<RangeControl
									label={ __( 'Excerpt Length (words)', 'swishfolio-core' ) }
									value={ excerptLength }
									onChange={ ( value ) => setAttributes( { excerptLength: value } ) }
									min={ 10 }
									max={ 55 }
								/>
							) }
						</PanelBody>

						{ ! inheritQuery && categoryOptions.length > 0 && (
							<PanelBody title={ __( 'Filter by Category', 'swishfolio-core' ) } initialOpen={ false }>
								{ categoryOptions.map( ( cat ) => (
									<ToggleControl
										key={ cat.value }
										label={ cat.label }
										checked={ categories.includes( cat.value ) }
										onChange={ ( checked ) => {
											const newCategories = checked
												? [ ...categories, cat.value ]
												: categories.filter( ( id ) => id !== cat.value );
											setAttributes( { categories: newCategories } );
										} }
									/>
								) ) }
							</PanelBody>
						) }
					</>
				) }

				{ activeTab === 'style' && (
					<>
						<PanelBody title={ __( 'Image', 'swishfolio-core' ) }>
							<RangeControl
								label={ __( 'Border Radius (px)', 'swishfolio-core' ) }
								value={ imageBorderRadius }
								onChange={ ( value ) => setAttributes( { imageBorderRadius: value ?? 0 } ) }
								min={ 0 }
								max={ 50 }
							/>
						</PanelBody>

						<PanelBody title={ __( 'Colors', 'swishfolio-core' ) }>
							<BaseControl label={ __( 'Title', 'swishfolio-core' ) }>
								<ColorPalette
									colors={ themeColors }
									value={ titleColor }
									onChange={ ( color ) => setAttributes( { titleColor: color || '' } ) }
									clearable
								/>
							</BaseControl>

							<BaseControl label={ __( 'Excerpt', 'swishfolio-core' ) }>
								<ColorPalette
									colors={ themeColors }
									value={ excerptColor }
									onChange={ ( color ) => setAttributes( { excerptColor: color || '' } ) }
									clearable
								/>
							</BaseControl>

							<BaseControl label={ __( 'Meta (Date & Author)', 'swishfolio-core' ) }>
								<ColorPalette
									colors={ themeColors }
									value={ metaColor }
									onChange={ ( color ) => setAttributes( { metaColor: color || '' } ) }
									clearable
								/>
							</BaseControl>
						</PanelBody>
					</>
				) }
			</InspectorControls>

			<div { ...blockProps }>
				<ServerSideRender
					block="swishfolio-core/latest-posts"
					attributes={ attributes }
					LoadingResponsePlaceholder={ () => (
						<div className="sfcore-latest-posts__loading">
							<Spinner />
						</div>
					) }
					EmptyResponsePlaceholder={ () => (
						<div className="sfcore-latest-posts__empty">
							<p>{ __( 'No posts found.', 'swishfolio-core' ) }</p>
						</div>
					) }
				/>
			</div>
		</>
	);
}
