/**
 * Shared constants for the site agent UI.
 */

export const REST_AGENT_PATH = '/swishfolio/v1/ai/agent';

export const AGENT_SIDEBAR_NAME = 'sfcore-ai-agent';

/** Mirrors AgentRun's statuses. */
export const STATUS_DONE = 'done';
export const STATUS_PENDING = 'awaiting_confirmation';
export const STATUS_CAPPED = 'capped';

/** Local-only states the panel is in while no reply has arrived. */
export const STATUS_IDLE = 'idle';
export const STATUS_WORKING = 'working';
export const STATUS_ERROR = 'error';

/** Sanity cap on the message field; the server imposes none. */
export const MAX_INSTRUCTION_LENGTH = 2000;
