/**
 * The review step: one row per proposed action, before beside after.
 *
 * Reviewing is just not dispatching. Nothing here has touched the post; the
 * buttons at the bottom are the only thing that does.
 */

import { Button, CheckboxControl, VisuallyHidden } from '@wordpress/components';
import { Fragment } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';

const ValueChip = ( { side, variant } ) => {
	if ( ! side ) {
		return null;
	}

	const className =
		'sfcore-ai-sidebar__value sfcore-ai-sidebar__value--' + variant;

	return (
		<span className={ className }>
			{ side.colour && side.colour.css && (
				<span
					className="sfcore-ai-sidebar__swatch"
					style={ { backgroundColor: side.colour.css } }
					aria-hidden="true"
				/>
			) }
			<span className="sfcore-ai-sidebar__value-text">
				{ side.colour && side.colour.label
					? side.colour.label
					: side.text }
			</span>
		</span>
	);
};

const FieldChange = ( { field } ) => (
	<li className="sfcore-ai-sidebar__field">
		<span className="sfcore-ai-sidebar__field-label">{ field.label }</span>
		<span className="sfcore-ai-sidebar__field-values">
			{ field.before && (
				<ValueChip side={ field.before } variant="before" />
			) }
			{ field.before && (
				<span className="sfcore-ai-sidebar__arrow" aria-hidden="true">
					→
				</span>
			) }
			{ field.before && (
				<VisuallyHidden>
					{ __( 'changes to', 'swishfolio-core' ) }
				</VisuallyHidden>
			) }
			<ValueChip side={ field.after } variant="after" />
		</span>
	</li>
);

const ChangeRow = ( { row, isChecked, onToggle } ) => (
	<li className="sfcore-ai-sidebar__row">
		<CheckboxControl
			__nextHasNoMarginBottom
			className="sfcore-ai-sidebar__row-check"
			checked={ isChecked }
			onChange={ () => onToggle( row.index ) }
			label={
				<span className="sfcore-ai-sidebar__row-head">
					<span className="sfcore-ai-sidebar__row-type">
						{ row.typeLabel }
					</span>
					<span className="sfcore-ai-sidebar__row-block">
						{ row.blockLabel }
					</span>
				</span>
			}
		/>

		{ row.fields.length > 0 && (
			<ul className="sfcore-ai-sidebar__fields">
				{ row.fields.map( ( field ) => (
					<FieldChange key={ field.attribute } field={ field } />
				) ) }
			</ul>
		) }

		{ row.note !== '' && (
			<p className="sfcore-ai-sidebar__row-note">{ row.note }</p>
		) }
		{ row.reason !== '' && (
			<p className="sfcore-ai-sidebar__row-reason">{ row.reason }</p>
		) }
	</li>
);

const ChangePreview = ( {
	proposal,
	selectedActions,
	onToggle,
	onApplyAll,
	onApplySelected,
	onDiscard,
} ) => {
	const total = proposal.rows.length;
	const selectedCount = selectedActions.length;

	return (
		<div className="sfcore-ai-sidebar__preview">
			{ proposal.summary !== '' && (
				<p className="sfcore-ai-sidebar__summary">
					{ proposal.summary }
				</p>
			) }

			{ total === 0 ? (
				<Fragment>
					<p className="sfcore-ai-sidebar__empty">
						{ __(
							'No changes were proposed. Nothing was changed.',
							'swishfolio-core'
						) }
					</p>
					<div className="sfcore-ai-sidebar__preview-actions">
						<Button variant="secondary" onClick={ onDiscard }>
							{ __( 'Back', 'swishfolio-core' ) }
						</Button>
					</div>
				</Fragment>
			) : (
				<Fragment>
					<p className="sfcore-ai-sidebar__count">
						{ sprintf(
							/* translators: %d: number of proposed changes. */
							_n(
								'%d proposed change',
								'%d proposed changes',
								total,
								'swishfolio-core'
							),
							total
						) }
					</p>

					<ul className="sfcore-ai-sidebar__rows">
						{ proposal.rows.map( ( row ) => (
							<ChangeRow
								key={ row.index }
								row={ row }
								isChecked={
									selectedActions.indexOf( row.index ) !== -1
								}
								onToggle={ onToggle }
							/>
						) ) }
					</ul>

					<div className="sfcore-ai-sidebar__preview-actions">
						<Button variant="primary" onClick={ onApplyAll }>
							{ __( 'Apply all', 'swishfolio-core' ) }
						</Button>
						<Button
							variant="secondary"
							onClick={ onApplySelected }
							disabled={ selectedCount === 0 }
						>
							{ sprintf(
								/* translators: %d: number of selected changes. */
								__( 'Apply selected (%d)', 'swishfolio-core' ),
								selectedCount
							) }
						</Button>
						<Button
							variant="tertiary"
							isDestructive
							onClick={ onDiscard }
						>
							{ __( 'Discard', 'swishfolio-core' ) }
						</Button>
					</div>
				</Fragment>
			) }
		</div>
	);
};

export default ChangePreview;
