import { useBlockProps, RichText } from '@wordpress/block-editor';

export default function save( { attributes } ) {
	const { rows = [], accentColor = '', titleColor = '' } = attributes;

	const wrapperStyle = {};
	if ( accentColor ) {
		wrapperStyle[ '--swish-cv-accent' ] = accentColor;
	}
	if ( titleColor ) {
		wrapperStyle[ '--swish-cv-title' ] = titleColor;
	}

	const blockProps = useBlockProps.save( {
		className: 'wp-block-swishfolio-core-swish-cv',
		style: Object.keys( wrapperStyle ).length ? wrapperStyle : undefined,
	} );

	return (
		<div { ...blockProps }>
			{ rows.map( ( row, index ) => (
				<div
					key={ index }
					className="wp-block-swishfolio-core-swish-cv__row"
				>
					<div className="wp-block-swishfolio-core-swish-cv__year">
						<RichText.Content value={ row.year } />
					</div>
					<div className="wp-block-swishfolio-core-swish-cv__middle">
						<h3 className="wp-block-swishfolio-core-swish-cv__role">
							<RichText.Content value={ row.role } />
							{ ' ' }
							<span className="wp-block-swishfolio-core-swish-cv__role-at">
								<RichText.Content value={ row.atCompany } />
							</span>
						</h3>
						<p className="wp-block-swishfolio-core-swish-cv__description">
							<RichText.Content value={ row.description } />
						</p>
					</div>
					<div className="wp-block-swishfolio-core-swish-cv__location">
						<RichText.Content value={ row.location } />
					</div>
				</div>
			) ) }
		</div>
	);
}
