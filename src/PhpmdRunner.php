<?php

declare(strict_types=1);

namespace Dpt\McpPhpmdWarm;

use PHPMD\PHPMD;
use PHPMD\Renderer\JSONRenderer;
use PHPMD\Report;
use PHPMD\RuleSetFactory;
use PHPMD\Writer\StreamWriter;

/**
 * Holds a warm PHPMD process across multiple analyse calls.
 *
 * The win is not a reused container (PHPMD has none) — it's the persistent PHP
 * process: classes, opcache and the autoloader stay hot, so each call skips the
 * cold interpreter boot + autoload that dominates a fresh CLI invocation.
 *
 * Rulesets are rebuilt per call on purpose: some PHPDepend rules accumulate
 * per-run state, so reusing RuleSet objects across files would leak violations.
 * Ruleset XML parsing is cheap relative to the boot cost we already amortize.
 */
final class PhpmdRunner
{
    private bool $warm = false;

    /** @param string $rulesets Comma-separated ruleset names or XML file paths, pinned at server startup. */
    public function __construct(private readonly string $rulesets)
    {
    }

    public function isWarm(): bool
    {
        return $this->warm;
    }

    /**
     * Run PHPMD on a single path. Exit codes mirror the PHPMD CLI: 0 clean, 2 violations.
     *
     * @return array{exit_code: int, output: string, warm_boot: bool}
     */
    public function run(string $path): array
    {
        $warmBoot = $this->warm;
        $this->warm = true;

        $factory = new RuleSetFactory();
        $ruleSetList = $factory->createRuleSets($this->rulesets);

        $stream = fopen('php://memory', 'r+');
        if ($stream === false) {
            throw new \RuntimeException('Could not open memory stream for renderer output.');
        }

        $renderer = new JSONRenderer();
        $renderer->setWriter(new StreamWriter($stream));

        $phpmd = new PHPMD();
        $report = new Report();

        $phpmd->processFiles($path, [], [$renderer], $ruleSetList, $report);

        rewind($stream);
        $output = stream_get_contents($stream);
        fclose($stream);

        return [
            'exit_code' => $phpmd->hasViolations() ? 2 : 0,
            'output' => $output === false ? '' : $output,
            'warm_boot' => $warmBoot,
        ];
    }
}
