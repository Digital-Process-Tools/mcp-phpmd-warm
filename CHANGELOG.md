# Changelog

All notable changes to this project will be documented in this file.

This project follows [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [0.1.1] — 2026-08-22

### Changed

- `mcp/sdk` requirement raised to `^0.7.1`, picking up the fix for the SSE transport's unbounded read buffer. This server speaks stdio, so the advisory never applied to it in practice; the bump keeps the dependency current and lets a consumer install all four DPT warm servers side by side on one SDK version.

## [0.1.0] — 2026-05-29

### Added

- Initial release. Warm-process MCP server for PHPMD — keeps the interpreter, opcache and autoloader hot across calls. ~24× faster per call vs cold CLI on a small file.
- `phpmd_analyse` MCP tool: runs PHPMD on a path with server-pinned rulesets (`--rulesets`), returns PHPMD's JSON report + `warm_boot` flag.
- Path containment on `phpmd_analyse`: `$path` is realpath-canonicalised against `realpath(getcwd())` (pinned at boot via `--working-dir`); out-of-cwd targets return a `SecurityError` before PHPMD runs, preventing content disclosure on arbitrary files.
- Rulesets rebuilt per call (fresh `RuleSetFactory` + `Report` + `JSONRenderer`) to avoid PHPDepend per-run state leaking across files — verified leak-free by integration test.
- Tests: `PhpmdRunnerTest` (violations, clean file, warm-boot flip, no state leak) + `PhpmdToolTest` (path containment).
