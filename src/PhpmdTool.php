<?php

declare(strict_types=1);

namespace Dpt\McpPhpmdWarm;

use Mcp\Capability\Attribute\McpTool;

final class PhpmdTool
{
    private readonly PhpmdRunner $runner;

    /**
     * Parameterless on purpose: the MCP SDK container auto-wires constructor
     * dependencies, and it cannot resolve PhpmdRunner's required string $rulesets.
     * So the tool builds its own runner here, reading the rulesets pinned at server
     * startup via the MCP_PHPMD_RULESETS env var (set by bin/mcp-phpmd-warm from the
     * --rulesets flag). Falls back to the PHPMD defaults when unset.
     */
    public function __construct()
    {
        $this->runner = new PhpmdRunner(
            getenv('MCP_PHPMD_RULESETS') ?: 'cleancode,codesize,controversial,design,naming,unusedcode'
        );
    }

    /**
     * Run PHPMD mess detection on a path. Rulesets are server-pinned.
     *
     * @param string $path Absolute path to a file under the server's working dir
     * @return array{exit_code: int, output: string, warm_boot: bool, error?: string, error_class?: string, trace?: string}
     */
    #[McpTool(name: 'phpmd_analyse', description: 'Run PHPMD mess detection on a path. Server-pinned rulesets, JSON output.')]
    public function analyse(string $path): array
    {
        // Containment: phpmd reads PHP source at $path. Reject paths outside
        // realpath(cwd) — set at boot via --working-dir. Prevents a hostile MCP
        // caller from triggering content disclosure on arbitrary files.
        $cwd = realpath(getcwd() ?: '.');
        $real = realpath($path);
        if ($cwd === false || $real === false || ($real !== $cwd && !str_starts_with($real, $cwd . DIRECTORY_SEPARATOR))) {
            return [
                'exit_code' => -1,
                'output' => '',
                'warm_boot' => $this->runner->isWarm(),
                'error' => 'phpmd_analyse: path is outside the configured working directory.',
                'error_class' => 'SecurityError',
                'trace' => '',
            ];
        }

        try {
            return $this->runner->run($real);
        } catch (\Throwable $e) {
            return [
                'exit_code' => -1,
                'output' => '',
                'warm_boot' => $this->runner->isWarm(),
                'error' => $e->getMessage(),
                'error_class' => $e::class,
                'trace' => $e->getTraceAsString(),
            ];
        }
    }
}
