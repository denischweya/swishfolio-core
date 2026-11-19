/**
 * @wordpress/i18n stand-in.
 *
 * Translation is the identity function, which is what an untranslated site
 * does. `sprintf` is real enough to matter: the UI uses both sequential (`%d`)
 * and positional (`%1$s`) placeholders, and a preview row that rendered
 * "Applied %d AI changes." would be a bug this suite must catch.
 */

export function __( text ) {
	return text;
}

export function _x( text ) {
	return text;
}

export function _n( single, plural, number ) {
	return number === 1 ? single : plural;
}

export function _nx( single, plural, number ) {
	return number === 1 ? single : plural;
}

export function sprintf( format, ...args ) {
	let sequential = 0;

	return String( format ).replace(
		/%(?:(\d+)\$)?([sd%])/g,
		( match, position, kind ) => {
			if ( kind === '%' ) {
				return '%';
			}

			const value = position
				? args[ Number( position ) - 1 ]
				: args[ sequential++ ];

			return kind === 'd' ? String( Number( value ) ) : String( value );
		}
	);
}

export function isRTL() {
	return false;
}
