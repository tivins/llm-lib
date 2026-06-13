# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.2.0] - 2026-06-13

### Added

- `LLM::tokenize()` — tokenize text via llama.cpp-compatible `POST /tokenize` endpoint.
- `examples/example-tokenize.php` — compare token vectors for similar and dissimilar phrases.
- `examples/example-tokenize-words.php` — word-level typos, inflections, synonyms, and unrelated pairs.
- `HarmonyContent` — parser for GPT-OSS Harmony channel format (`analysis` / `final`).

### Changed

- `LLM` — HTTP request logic extracted into shared `request()` method (used by `chatCompletion` and `tokenize`).
- `LLM::chatCompletion()` — normalizes GPT-OSS / Harmony `<|channel|>` markers in assistant responses; recovers usable text from llama.cpp "Failed to parse input" HTTP 500 errors when the embedded output is parseable.
- `Message::toChatCompletionArray()` — strips Harmony channel markers from assistant `content` before re-sending history to the server.

## [0.1.3] - 2026-06-09

### Changed

- PHP requirement raised from `^8.1` to `^8.3`.
- PHP CS Fixer and PHPStan configuration now include the `tests/` directory.

### Added

- [`README.md`](README.md) — project overview, quick start, architecture, and behavioral contracts.
- [`CHANGELOG.md`](CHANGELOG.md) — this file.
- [`TODO.md`](TODO.md) — backlog of behaviors locked by tests and open design questions.
- Expanded test coverage (agent hooks, edge cases).

## [0.1.2] - 2026-06-09

### Added

- PHPUnit as a development dependency.
- `phpunit.xml.dist` configuration.
- Initial test suite: `Agent`, `AgentHooks`, `ChatCompletionOptions`, `ChatCompletionResponse`, `Conversation`, `Logger`, `Message`, `Tool`, `ToolCall`, `ToolRegistry`.
- Test support utilities: `tests/Support/StubLLM.php`, `tests/Support/ResponseFactory.php`.

### Changed

- `Agent`: assistant messages stored via `toStoredAssistantMessage()`; `temperature` merged into message `meta`.
- `ChatCompletionResponse::toStoredMessage()`: simplified signature (duration taken from response; options no longer passed in).

## [0.1.1] - 2026-06-08

### Added

- `.php-cs-fixer.dist.php` for code style enforcement.
- `friendsofphp/php-cs-fixer` and `phpstan/phpstan` as development dependencies.
- Composer scripts: `cs-check`, `cs-fix`, `phpstan`, `analyse`.

### Changed

- Code formatting applied across `src/` for consistency.

## [0.1.0] - 2026-06-08

### Added

- Initial library structure and Composer package `tivins/llm-lib`.
- `LLM` — HTTP client for OpenAI-compatible `/v1/chat/completions` (PHP cURL).
- `Conversation` and `Message` — chat history with dual serialization (`toArray` / `toChatCompletionArray`).
- `Agent` — single-turn orchestration with tool-call loop and `maxToolRounds` guard.
- `ToolRegistry`, `Tool`, `ToolSchema`, `ToolCall` — tool registration and execution.
- `ChatCompletionOptions`, `ChatCompletionResponse`, `Choice`, `Usage` — request/response models.
- `AgentTurnResult` — turn outcome (`success`, `message`, `error`, `toolRounds`).
- `AgentHooks` and typed hook events under `src/Hooks/`.
- `Logger` — optional JSON file persistence on each `addMessage()`.
- `Role` enum: `system`, `user`, `assistant`, `tool`, `unknown`.
- Docblocks on public classes and hook events.

