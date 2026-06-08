import {
	useBlockProps,
	RichText,
	BlockControls,
	InspectorControls,
	PanelColorSettings,
} from '@wordpress/block-editor';
import { Button, ToolbarGroup, ToolbarButton } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { plus, trash, arrowUp, arrowDown } from '@wordpress/icons';
import { useState } from '@wordpress/element';

const blankRow = () => ( {
	year: '',
	role: '',
	atCompany: '',
	description: '',
	location: '',
} );

export default function Edit( { attributes, setAttributes } ) {
	const { rows = [], accentColor = '', titleColor = '' } = attributes;
	const [ activeRow, setActiveRow ] = useState( 0 );

	const wrapperStyle = {};
	if ( accentColor ) {
		wrapperStyle[ '--swish-cv-accent' ] = accentColor;
	}
	if ( titleColor ) {
		wrapperStyle[ '--swish-cv-title' ] = titleColor;
	}

	const blockProps = useBlockProps( {
		className: 'wp-block-swishfolio-core-swish-cv',
		style: Object.keys( wrapperStyle ).length ? wrapperStyle : undefined,
	} );

	const updateRow = ( index, field, value ) => {
		const next = rows.map( ( row, i ) =>
			i === index ? { ...row, [ field ]: value } : row
		);
		setAttributes( { rows: next } );
	};

	const addRow = () => {
		setAttributes( { rows: [ ...rows, blankRow() ] } );
		setActiveRow( rows.length );
	};

	const removeRow = ( index ) => {
		const next = rows.filter( ( _, i ) => i !== index );
		setAttributes( { rows: next } );
		setActiveRow( Math.max( 0, index - 1 ) );
	};

	const moveRow = ( index, direction ) => {
		const target = index + direction;
		if ( target < 0 || target >= rows.length ) {
			return;
		}
		const next = [ ...rows ];
		[ next[ index ], next[ target ] ] = [ next[ target ], next[ index ] ];
		setAttributes( { rows: next } );
		setActiveRow( target );
	};

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelColorSettings
					title={ __( 'Color', 'swishfolio-core' ) }
					initialOpen
					colorSettings={ [
						{
							value: accentColor,
							onChange: ( color ) =>
								setAttributes( { accentColor: color || '' } ),
							label: __( 'Accent', 'swishfolio-core' ),
						},
						{
							value: titleColor,
							onChange: ( color ) =>
								setAttributes( { titleColor: color || '' } ),
							label: __( 'Title', 'swishfolio-core' ),
						},
					] }
				/>
			</InspectorControls>

			<BlockControls>
				<ToolbarGroup>
					<ToolbarButton
						icon={ arrowUp }
						label={ __( 'Move row up', 'swishfolio-core' ) }
						onClick={ () => moveRow( activeRow, -1 ) }
						disabled={ activeRow <= 0 }
					/>
					<ToolbarButton
						icon={ arrowDown }
						label={ __( 'Move row down', 'swishfolio-core' ) }
						onClick={ () => moveRow( activeRow, 1 ) }
						disabled={ activeRow >= rows.length - 1 }
					/>
					<ToolbarButton
						icon={ trash }
						label={ __( 'Remove row', 'swishfolio-core' ) }
						onClick={ () => removeRow( activeRow ) }
						disabled={ rows.length <= 1 }
						isDestructive
					/>
				</ToolbarGroup>
			</BlockControls>

			{ rows.map( ( row, index ) => (
				<div
					key={ index }
					className={ `wp-block-swishfolio-core-swish-cv__row${
						activeRow === index ? ' is-active' : ''
					}` }
					onClick={ () => setActiveRow( index ) }
					onFocus={ () => setActiveRow( index ) }
				>
					<RichText
						tagName="div"
						className="wp-block-swishfolio-core-swish-cv__year"
						value={ row.year }
						onChange={ ( v ) => updateRow( index, 'year', v ) }
						placeholder={ __( '2024 — Now', 'swishfolio-core' ) }
						allowedFormats={ [] }
					/>

					<div className="wp-block-swishfolio-core-swish-cv__middle">
						<h3 className="wp-block-swishfolio-core-swish-cv__role">
							<RichText
								tagName="span"
								value={ row.role }
								onChange={ ( v ) => updateRow( index, 'role', v ) }
								placeholder={ __( 'Role', 'swishfolio-core' ) }
								allowedFormats={ [] }
							/>
							{ ' ' }
							<RichText
								tagName="span"
								className="wp-block-swishfolio-core-swish-cv__role-at"
								value={ row.atCompany }
								onChange={ ( v ) =>
									updateRow( index, 'atCompany', v )
								}
								placeholder={ __( '— Company', 'swishfolio-core' ) }
								allowedFormats={ [] }
							/>
						</h3>
						<RichText
							tagName="p"
							className="wp-block-swishfolio-core-swish-cv__description"
							value={ row.description }
							onChange={ ( v ) =>
								updateRow( index, 'description', v )
							}
							placeholder={ __(
								'What you did and the impact.',
								'swishfolio-core'
							) }
							allowedFormats={ [
								'core/bold',
								'core/italic',
								'core/link',
							] }
						/>
					</div>

					<RichText
						tagName="div"
						className="wp-block-swishfolio-core-swish-cv__location"
						value={ row.location }
						onChange={ ( v ) => updateRow( index, 'location', v ) }
						placeholder={ __( 'City', 'swishfolio-core' ) }
						allowedFormats={ [] }
					/>
				</div>
			) ) }

			<div className="wp-block-swishfolio-core-swish-cv__add">
				<Button variant="secondary" icon={ plus } onClick={ addRow }>
					{ __( 'Add CV row', 'swishfolio-core' ) }
				</Button>
			</div>
		</div>
	);
}
