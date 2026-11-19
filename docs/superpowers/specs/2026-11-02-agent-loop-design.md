# Agent loop — provider tool-calling and conversational site management

**Date:** 2026-11-02
**Status:** approved (follows the tool layer spec of the same date)

## Why

The tool layer is finished plumbing with no model attached. Nothing in `src/AI/`
references `ToolRunner`, `src/AI/Providers/` has no `tools`/`tool_choice`
support, and no UI calls `/ai/tools/*`. So every capability the tool layer added
is unreachable by a user, and the model-in-the-loop eval is unbuildable.

This is the missing link, and it was absent from the five-slice decomposition.

## Decisions

1. **`converse()` is a second provider method, not an overload of `generate()`.**
   Structured output and tool-calling are different protocols — one constrains a
   single reply to a schema, the other is a multi-turn exchange — and the
   structured path is load-bearing for editing and generation.
2. **Tool names are remapped at the provider boundary.** Both providers require
   `^[a-zA-Z0-9_-]{1,64}$`, so `swishfolio/site-info` is rejected. Only the
   slash is illegal, so `/` becomes `__` and hyphens survive:
   `swishfolio__site-info`. Reversal splits on the first `__`, which is
   unambiguous because no tool name contains one — asserted, not assumed.
3. **A tool failure is fed back to the model, not raised.** The error contract
   exists so a model can correct itself; aborting the loop on a `schema_invalid`
   would waste that. Only a failure of the *loop* (provider down, iteration cap)
   raises.
4. **`confirm_required` pauses the loop.** The runner already refuses published
   and destructive calls without a token. The loop catches that, stops, and
   returns the pending calls plus a `ConfirmToken` for exactly them. A follow-up
   request with the token resumes. The agent proposes; the user approves.
5. **Iterations are capped** (default 8, filterable). An unbounded loop is an
   unbounded bill.

## Architecture

```
AgentLoop::run( instruction, ToolContext, history )
  → system prompt (rules + site facts) + messages
  → provider->converse( ConversationRequest with tool definitions )
  → reply has tool calls?
      no  → done, return the assistant's text
      yes → run each through ToolRunner
              ok               → ToolResult appended, loop
              ToolException    → ToolResult(isError) appended, loop
              confirm_required → stop: awaiting_confirmation + token
  → iteration cap reached → stop: capped
```

### Value objects (`src/AI/Conversation/`)

- `Message` — role (`user`|`assistant`|`tool_result`), text, tool calls, tool
  call id. One type covering all three shapes, because providers interleave them
  in a single list and separate classes would only be re-merged.
- `ToolCall` — id, name, input.
- `ToolDefinition` — name, description, input schema.
- `ConversationRequest` — system prompt, messages, tool definitions, model, max
  tokens.
- `ConversationReply` — text, tool calls, stop reason, token usage.
- `ToolNameMap` — the `/` ↔ `__` mapping, with the no-`__` assertion.

### Providers

`AnthropicProvider::converse()` maps to content blocks (`tool_use`,
`tool_result`); `OpenAIProvider::converse()` maps to `tool_calls` and
`role: tool` messages. `OpenAICompatibleProvider` inherits the OpenAI mapping.

### Loop result

`AgentRun` — status (`done`|`awaiting_confirmation`|`capped`), assistant text,
steps (tool name, summary, ok/error), pending calls, confirm token, usage
totals, message history for the next turn.

## REST

`POST /ai/agent` — `{instruction, history?, confirmToken?}` →
`{status, message, steps, pending, confirmToken, history, usage, runId}`.
Same capability and enabled gates as the other AI routes, rate limited.

## UI

A chat panel in the AI sidebar alongside the existing block-editing panel:
message list, input, per-step summaries, and an approval prompt rendering the
pending calls with Approve/Cancel. Approve resubmits with the token.

## Testing

- `ToolNameMap`: round-trips every registered tool name; rejects a name
  containing `__`.
- Provider mapping: a fake HTTP seam asserts the request body shape for both
  providers, and that a `tool_use` reply decodes to `ToolCall`s.
- Loop: a read-only instruction completes in one iteration; a tool error is fed
  back and the loop continues; a published write stops with
  `awaiting_confirmation` and a token that then resumes it; the iteration cap
  stops a model that never stops calling tools.
- Evals: the model-in-the-loop tool-selection eval the tool-layer spec deferred,
  in recorded mode.
