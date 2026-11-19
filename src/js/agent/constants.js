/**
 * Shared constants for the site agent UI.
 */

export const REST_AGENT_PATH = '/swishfolio/v1/ai/agent';
export const REST_JOB_PATH = '/swishfolio/v1/ai/jobs/';

/** How often to ask a queued turn how it is getting on. */
export const POLL_INTERVAL_MS = 1500;

/*
 * A turn makes up to eight provider calls, so the whole thing can outlive PHP's
 * execution limit. Running it as a background job and polling is the only way a
 * long turn completes at all on a typical host.
 */
export const JOB_STATUS_DONE = 'done';
export const JOB_STATUS_FAILED = 'failed';

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
