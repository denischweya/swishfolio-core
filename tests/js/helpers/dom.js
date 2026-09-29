/**
 * Assertions about editor DOM that need to hold for both flavours of a disabled
 * WordPress button.
 */

/**
 * A WordPress Button rendered with `accessibleWhenDisabled` keeps the `disabled`
 * attribute off so it stays focusable — its state lives in `aria-disabled`. A
 * plain disabled Button carries both. Either counts as disabled.
 *
 * @param {HTMLElement} element Button element.
 *
 * @return {boolean} Whether the control refuses interaction.
 */
export function isDisabled( element ) {
	return (
		element.disabled === true ||
		element.getAttribute( 'aria-disabled' ) === 'true'
	);
}
