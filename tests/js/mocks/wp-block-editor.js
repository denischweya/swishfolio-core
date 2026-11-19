/**
 * @wordpress/block-editor stand-in.
 *
 * `BlockControls` is a Slot/Fill in the real editor: the fill renders nothing
 * until a matching Slot places it in the block toolbar. Here it renders its
 * children inline so a test can assert what the fill *contains*. Whether the
 * slot places it in the real toolbar is a browser question, not a Jest one.
 */

export const store = { name: 'core/block-editor' };

export const BlockControls = ( { children, group } ) => (
	<div className="block-editor-block-controls" data-group={ group }>
		{ children }
	</div>
);

export const InspectorControls = ( { children } ) => <div>{ children }</div>;
