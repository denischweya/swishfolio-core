/**
 * @wordpress/components stand-in.
 *
 * Each component renders the same *semantics* as the real one — a real
 * `<button>`, a `<textarea>` associated with its label, a checkbox input — so
 * Testing Library's role and label queries mean the same thing here as in the
 * browser. Presentational props are swallowed rather than spread onto the DOM
 * node, because the jest preset turns React's unknown-prop warning into a test
 * failure.
 *
 * The one behaviour worth spelling out is disabled buttons. The real Button
 * computes `trulyDisabled = !!disabled && ! accessibleWhenDisabled` (see
 * wp-includes/js/dist/components.js): a button that is disabled *and*
 * accessibleWhenDisabled keeps the `disabled` attribute OFF so it stays
 * focusable and its tooltip still reaches a keyboard user, and advertises its
 * state through `aria-disabled` instead. That is exactly what the Ask AI
 * toolbar button asks for, so the mock reproduces it — and tests must ask
 * `isDisabled()` (tests/js/helpers/dom.js) rather than looking only at the
 * `disabled` property.
 */

import { useId } from 'react';

export const Button = ( {
	children,
	className,
	onClick,
	disabled,
	accessibleWhenDisabled,
	label,
	href,
	icon,
	// Swallowed: presentation only.
	variant,
	size,
	isBusy,
	isDestructive,
	isPressed,
	showTooltip,
	shortcut,
	__next40pxDefaultSize,
	__nextHasNoMarginBottom,
	...rest
} ) => {
	const trulyDisabled = !! disabled && ! accessibleWhenDisabled;

	return (
		<button
			type="button"
			className={ className }
			aria-label={ label }
			aria-disabled={ disabled ? 'true' : undefined }
			disabled={ trulyDisabled || undefined }
			onClick={ ( event ) => {
				if ( disabled ) {
					event.preventDefault();

					return;
				}

				if ( onClick ) {
					onClick( event );
				}
			} }
			{ ...rest }
		>
			{ icon }
			{ children }
		</button>
	);
};

export const ToolbarGroup = ( { children } ) => (
	<div className="components-toolbar-group">{ children }</div>
);

export const ToolbarButton = ( {
	className,
	icon,
	label,
	disabled,
	accessibleWhenDisabled,
	isActive,
	onClick,
	children,
} ) => (
	<Button
		className={ className }
		icon={ icon }
		label={ label }
		disabled={ disabled }
		accessibleWhenDisabled={ accessibleWhenDisabled }
		aria-pressed={ isActive ? 'true' : undefined }
		onClick={ onClick }
	>
		{ children }
	</Button>
);

export const TextareaControl = ( {
	label,
	help,
	value,
	onChange,
	onKeyDown,
	rows,
	disabled,
	className,
	// Swallowed: opt-in styling flags with no DOM meaning here.
	__nextHasNoMarginBottom,
	...rest
} ) => {
	const id = useId();

	return (
		<div className={ className }>
			<label htmlFor={ id }>{ label }</label>
			<textarea
				id={ id }
				rows={ rows }
				value={ value }
				disabled={ disabled || undefined }
				aria-describedby={ help ? id + '-help' : undefined }
				onChange={ ( event ) => onChange( event.target.value ) }
				onKeyDown={ onKeyDown }
			/>
			{ help && <p id={ id + '-help' }>{ help }</p> }
		</div>
	);
};

export const CheckboxControl = ( {
	label,
	checked,
	onChange,
	className,
	// Swallowed: opt-in styling flags with no DOM meaning here.
	__nextHasNoMarginBottom,
	...rest
} ) => {
	const id = useId();

	return (
		<div className={ className }>
			<input
				type="checkbox"
				id={ id }
				checked={ !! checked }
				onChange={ ( event ) => onChange( event.target.checked ) }
			/>
			<label htmlFor={ id }>{ label }</label>
		</div>
	);
};

export const Notice = ( { children, className, status, ...rest } ) => (
	<div
		className={
			'components-notice is-' + status + ( className ? ' ' + className : '' )
		}
	>
		<div className="components-notice__content">{ children }</div>
	</div>
);

export const Spinner = () => <span className="components-spinner" />;

export const VisuallyHidden = ( { children } ) => (
	<span className="components-visually-hidden">{ children }</span>
);

export const Panel = ( { children } ) => <div>{ children }</div>;
export const PanelBody = ( { children } ) => <div>{ children }</div>;
