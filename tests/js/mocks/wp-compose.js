/**
 * @wordpress/compose stand-in.
 *
 * `createHigherOrderComponent` only names the wrapper in the real package; the
 * mapper it is given is the whole behaviour.
 */

export const createHigherOrderComponent = ( mapper, modifierName ) => {
	const hoc = ( Inner ) => {
		const Wrapped = mapper( Inner );

		Wrapped.displayName = modifierName;

		return Wrapped;
	};

	hoc.displayName = modifierName;

	return hoc;
};
