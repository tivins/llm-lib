# TODO — Anomalies and behaviors to review

Behaviors currently **locked in by unit tests**. They are not treated as bugs until an explicit decision has been made. This file serves as a backlog for later analysis and resolution.

---

## 1. Empty content: `''` in storage, `null` in API requests

**Current behavior**

- `Message::toArray()` keeps `content` as an empty string `''`.
- `Message::toChatCompletionArray()` converts empty content to `null` (format expected by the OpenAI API).

**Reference test**: `tests/MessageTest.php` — `testEmptyContentBecomesNullInChatCompletionPayload`

**Open questions**

- Is this dual handling intentional and sufficiently documented for library consumers?
- Should internal storage be harmonized (`''` vs `null`), or should the distinction be documented explicitly?

**Possible actions**

- [ ] Document in the README or `Message` docs
- [ ] Add a PHPDoc note on `toChatCompletionArray()`
- [ ] Revisit whether `toArray()` should normalize as well

---

## 2. `reasoning_content`: logged but never sent back to the LLM

**Current behavior**

- `reasoning_content` is included in `Message::toArray()` (logs, persistence).
- It is **deliberately omitted** from `Message::toChatCompletionArray()` so chain-of-thought is not re-injected into the LLM context.

**Reference test**: `tests/MessageTest.php` — `testToChatCompletionArrayOmitsReasoningContentAndMeta`

**Open questions**

- Is the comment in `Message.php` sufficient as public documentation?
- Are there cases where re-injecting it would be desirable (multi-turn models with reasoning)?

**Possible actions**

- [ ] Document the choice in the README
- [ ] Consider an explicit option (`includeReasoningInRequests`) if the need arises

---

## 3. Unknown tool: error JSON in the tool message, no exception

**Current behavior**

- If `ToolRegistry::execute()` finds no handler, it returns a `tool`-role `Message` whose content is `{"error":"No handler for tool: <name>"}`.
- No exception is thrown; the conversation continues.

**Reference test**: `tests/ToolRegistryTest.php` — `testUnknownToolReturnsJsonErrorContent`

**Open questions**

- Is this the right contract for a production agent (silent error on the caller side)?
- Should the LLM receive the error and react, or should the agent fail immediately?

**Possible actions**

- [ ] Throw a dedicated exception (`UnknownToolException`) with an option for legacy behavior
- [ ] Log a warning when a handler is missing
- [ ] Document the expected behavior for integrators

---

## 4. Duplicate tool name: silent handler overwrite

**Current behavior**

- `ToolRegistry::registerTools()` silently overwrites an existing tool if the same `name` is registered again.
- The last registered handler is the one that runs.

**Reference test**: `tests/ToolRegistryTest.php` — `testRegisteringSameNameOverwritesPreviousHandler`

**Open questions**

- Should duplicates be detected and raise an exception?
- Or at minimum emit a warning / log?

**Possible actions**

- [ ] Reject duplicate registration (potential breaking change)
- [ ] Add a strict mode (`ToolRegistry::strict()` or constructor parameter)
- [ ] Document the current behavior

---

## 5. `Agent::runTurn()`: `$options` mutation and `temperature` in meta

**Current behavior**

- `runTurn()` **mutates** `$options->tools` in place to enforce the agent's registry (or throws `InvalidArgumentException` if a different registry is provided).
- When storing the assistant message, `temperature` is merged into the message `meta` (`toStoredAssistantMessage`).

**Reference tests**

- `tests/AgentTest.php` — `testSimpleStopResponseStoresAssistantMessage` (`tools` injection, `temperature` meta)
- `tests/AgentTest.php` — `testMismatchedToolsRegistryThrows`

**Open questions**

- Is mutating `$options` acceptable for callers who reuse the same options instance?
- Is `temperature` in message `meta` useful for auditing, or metadata pollution?

**Possible actions**

- [ ] Clone `$options` internally to avoid side effects
- [ ] Separate message meta from request parameters
- [ ] Document the `runTurn()` contract regarding `$options`

---

## 6. `finish_reason: length` without tool calls → success

**Current behavior**

- If the LLM responds with `finish_reason: length` and **no** `tool_calls`, `Agent` treats the turn as successful and stores the assistant message (truncated response).

**Reference test**: `tests/AgentTest.php` — `testLengthFinishReasonWithoutToolCallsIsSuccess`

**Open questions**

- Should a truncated response be treated as success or partial failure?
- Should `finish_reason` be exposed on `AgentTurnResult` so the caller can decide?

**Possible actions**

- [ ] Add a `truncated` flag or `finishReason` on `AgentTurnResult`
- [ ] Treat `length` as `success: false` with an explicit message
- [ ] Keep current behavior but document the semantics

---

## Tracking

| # | Topic | Priority | Decision | Status |
|---|-------|----------|----------|--------|
| 1 | Empty content `''` / `null` | — | — | To review |
| 2 | `reasoning_content` omitted from requests | — | — | To review |
| 3 | Unknown tool without exception | — | — | To review |
| 4 | Duplicate tool name | — | — | To review |
| 5 | `$options` mutation / `temperature` meta | — | — | To review |
| 6 | `finish_reason: length` = success | — | — | To review |

Update this table when each item has been resolved (document, change the library, or confirm as intended behavior).
