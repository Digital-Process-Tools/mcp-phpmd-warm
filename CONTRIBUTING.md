# Contributing

Thanks for the interest. This project is small and intentionally focused.

## Reporting issues

Open a GitHub issue with:

- PHPMD version (`composer show phpmd/phpmd`)
- PHP version (`php -v`)
- MCP client (Claude Desktop, Cline, ...)
- Repro: the `--rulesets` value + the failing file/command

## Pull requests

1. Fork, branch from `main`.
2. Add a test for the change (`tests/Unit` for logic, `tests/Integration` for end-to-end stdio behavior).
3. Run the suite:
   ```bash
   ./vendor/bin/phpunit --no-coverage
   ```
4. Open the PR with a one-paragraph summary of the change.

## What we'll merge

- Bug fixes with a regression test.
- PHPMD version compatibility shims.
- New MCP tools that have a clear use case from an MCP client.
- Doc / README improvements.

## What we won't merge

- Changes that reuse `RuleSet` objects across calls without proving no per-run state leaks (it would corrupt results — see `PhpmdRunner`).
- Wrappers that just shell out to `vendor/bin/phpmd` — defeats the whole purpose.

## Local development

```bash
git clone https://github.com/Digital-Process-Tools/mcp-phpmd-warm.git
cd mcp-phpmd-warm
composer install
./vendor/bin/phpunit --no-coverage
```

Smoke test the binary against the fixtures:

```bash
bin/mcp-phpmd-warm --working-dir=tests --rulesets=cleancode,codesize,design,naming,unusedcode
# (then paste MCP JSON-RPC on stdin)
```
