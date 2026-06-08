/**
 * Counter Item - Save Component.
 *
 * Saves data-target / data-decimals attributes that view.js reads to drive
 * the count-up animation. No-JS visitors see the final value as authored.
 */

import { useBlockProps, RichText } from '@wordpress/block-editor';

const stripTags = ( s ) => ( s || '' ).replace( /<[^>]+>/g, '' );

const parseTarget = ( valueString ) => {
	const text = stripTags( valueString ).trim();
	const target = parseFloat( text );
	if ( ! Number.isFinite( target ) ) {
		return { target: 0, decimals: 0 };
	}
	const dot = text.indexOf( '.' );
	const decimals = dot === -1 ? 0 : text.length - dot - 1;
	return { target, decimals };
};

export default function save( { attributes } ) {
	const { prefix, value, suffix, title } = attributes;
	const { target, decimals } = parseTarget( value );

	const blockProps = useBlockProps.save( {
		className: 'sfcore-swish-counter-item',
	} );

	return (
		<figure { ...blockProps }>
			<div className="sfcore-swish-counter-item__counter">
				{ prefix && (
					<RichText.Content
						tagName="span"
						className="sfcore-swish-counter-item__prefix"
						value={ prefix }
					/>
				) }
				<RichText.Content
					tagName="span"
					className="sfcore-swish-counter-item__value"
					value={ value }
					data-target={ target }
					data-decimals={ decimals }
				/>
				{ suffix && (
					<RichText.Content
						tagName="span"
						className="sfcore-swish-counter-item__suffix"
						value={ suffix }
					/>
				) }
			</div>

			{ title && (
				<RichText.Content
					tagName="p"
					className="sfcore-swish-counter-item__title"
					value={ title }
				/>
			) }
		</figure>
	);
}
