/**
 * Shared constants for the AI block editing UI.
 *
 * The sidebar identifier is derived the same way `ComplementaryArea` derives
 * it — `${ pluginName }/${ sidebarName }` — so the toolbar button can open the
 * sidebar through the `core/interface` store without importing
 * `@wordpress/interface` (which the build bundles rather than externalises).
 */

/** Where the editor reports how many proposed changes were actually applied. */
export const REST_APPLIED_PATH = '/swishfolio/v1/ai/applied';

export const REST_EDIT_PATH = '/swishfolio/v1/ai/edit';

export const PLUGIN_NAME = 'sfcore-ai';
export const SIDEBAR_NAME = 'sfcore-ai-sidebar';
export const SIDEBAR_SCOPE = 'core';
export const SIDEBAR_IDENTIFIER = PLUGIN_NAME + '/' + SIDEBAR_NAME;

export const INTERFACE_STORE = 'core/interface';

/** Key on `window` where PHP localises the capability manifests. */
export const GLOBAL_DATA_KEY = 'sfcoreAiEditor';

export const ACTION_UPDATE_ATTRIBUTES = 'update_block_attributes';
export const ACTION_UPDATE_CONTENT = 'update_block_content';
export const ACTION_INSERT = 'insert_block';
export const ACTION_REMOVE = 'remove_block';

export const ACTION_TYPES = [
	ACTION_UPDATE_ATTRIBUTES,
	ACTION_UPDATE_CONTENT,
	ACTION_INSERT,
	ACTION_REMOVE,
];

/** Mirrors ActionSchema::DEFAULT_MAX_ACTIONS. */
export const DEFAULT_MAX_ACTIONS = 25;

/** Sanity cap on the prompt field; the server imposes none. */
export const MAX_INSTRUCTION_LENGTH = 1000;
