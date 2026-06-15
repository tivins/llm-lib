# TODO — Tool approval and rejection via hooks

Design note for integrators building a **code harness** (or any agent where sensitive actions require human validation). Documents what the library supports today, recommended patterns, and possible future enhancements.

---

## Current capability: `beforeToolCall` + `$replacement`

The primary hook for gating tool execution is **`BeforeToolCall`**.

`BeforeToolCallEvent` exposes a mutable `$replacement` property. If a listener sets it, the real tool handler is **skipped** and the replacement message is returned to the conversation instead.

**Reference**

- `src/Hooks/BeforeToolCallEvent.php`
- `src/ToolCallRejection.php`
- `src/Agent.php` — `executeToolCall()`
- `tests/AgentTest.php` — `testBeforeToolCallReplacementSkipsHandler`
- `examples/ex070-advanced-hooks.php` — mocked tool execution via `$replacement`

```php
$hooks->beforeToolCall(function (BeforeToolCallEvent $event): void {
    if (!in_array($event->call->name, ['write_file', 'run_command'], true)) {
        return; // safe tools: no approval gate
    }

    // Show the proposed action to the user, then wait for input.
    $approved = promptUser(
        tool: $event->call->name,
        arguments: $event->call->arguments,
    );

    if (!$approved) {
        $event->replacement = ToolCallRejection::userRejected($event->call);
    }

    // If $replacement stays null, the tool handler runs normally.
});
```

The LLM receives the tool message (success or rejection) and can adapt its next response.

---

## Recommended architecture for a code harness

1. **Model each sensitive action as a `Tool`** (write file, run shell command, apply patch, etc.).
2. **Gate sensitive tools in `beforeToolCall`** — ask for user approval before execution.
3. **Orchestrate at the application level** — loop over `runTurn()`, display intermediate state, and decide when to send the next user message. Do not rely on a single opaque turn for the whole harness.

Complementary hooks (observability, not gating):

| Hook | Use |
|------|-----|
| `beforeToolRound` | Preview all tool calls planned for the current round (read-only) |
| `afterToolCall` | Audit what was actually executed |
| `onAssistantResponse` | Rewrite the final visible assistant message |
| `afterTurn` | Decide whether to continue, retry, or stop |

---

## What hooks do **not** support today

| Need | Supported? | Notes |
|------|------------|-------|
| Approve/reject a single tool call before execution | **Yes** | `beforeToolCall` + `$replacement` |
| Preview a full tool round before any execution | **Read-only** | `beforeToolRound` has no cancel flag |
| Cancel an entire tool round at once | **No** | Would require a new API on `BeforeToolRoundEvent` |
| Block or veto an LLM call | **No** | `beforeLlmCall` is informational only |
| Abort `runTurn()` mid-flight from a hook | **No** | No turn-level veto mechanism |
| Gate code emitted as plain assistant text (no tool) | **No** | Must be handled outside the tool pipeline (`onAssistantResponse`, `afterTurn`, or caller logic) |
| Native async approval (web UI, webhook, queue) | **Partial** | Listeners are synchronous; async flows must block inside the listener or be orchestrated outside `runTurn()` |

---

## Open questions

- Should the library provide a first-class **approval contract** (e.g. `ToolApprovalDecision` enum, dedicated exception, or callback interface) instead of leaving integrators to invent `$replacement` error payloads?
- Should `BeforeToolRoundEvent` support **batch approval** (approve/reject the whole round, or selectively skip individual calls before the loop starts)?
- Should `AgentTurnResult` expose enough metadata (`finishReason`, per-tool outcomes) for harnesses to decide whether to continue without re-parsing the conversation?
- Should rejected tools use a **standardized error JSON schema** so models recover more reliably?

---

## Possible actions

- [ ] Document the `beforeToolCall` approval pattern in the README (integrator guide section)
- [x] Add an example (`ex110-tool-proposal-rejected.php`) demonstrating a rejected tool proposal in one round
- [ ] Add an example with interactive CLI approval
- [ ] Add `BeforeToolRoundEvent::$cancel` or per-call skip list to cancel a round without fake replacements
- [ ] Introduce a `ToolApprovalHandler` interface (sync or async) injectable into `Agent`
- [ ] Standardize rejection payload shape (`{"error":"...", "code":"user_rejected"}`) — **done for user rejection via `ToolCallRejection::userRejected()`**; other tool errors still ad hoc
- [ ] Expose `finishReason` on `AgentTurnResult` for harness decision logic

---

## Tracking

| Topic | Priority | Decision | Status |
|-------|----------|----------|--------|
| `beforeToolCall` approval via `$replacement` | — | Works today; integrator responsibility | Documented |
| Batch / round-level cancellation | — | — | To review |
| First-class approval API | — | — | To review |
| Standard rejection payload | — | `ToolCallRejection::userRejected()` | Done |

Update this table when each item has been resolved (document, implement, or confirm as out of scope).
