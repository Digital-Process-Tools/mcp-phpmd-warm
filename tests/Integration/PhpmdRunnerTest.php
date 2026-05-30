<?php

declare(strict_types=1);

namespace Dpt\McpPhpmdWarm\Tests\Integration;

use Dpt\McpPhpmdWarm\PhpmdRunner;
use PHPUnit\Framework\TestCase;

final class PhpmdRunnerTest extends TestCase
{
    private const RULESETS = 'cleancode,codesize,design,naming,unusedcode';

    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
        $this->tempFiles = [];
    }

    private function fixture(string $name): string
    {
        return \dirname(__DIR__) . '/Fixtures/' . $name;
    }

    private function makeTempPhpFile(string $content): string
    {
        $path = sys_get_temp_dir() . '/phpmd-staleness-' . uniqid('', true) . '.php';
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;
        return $path;
    }

    public function testMessyFileReportsViolations(): void
    {
        $runner = new PhpmdRunner(self::RULESETS);
        $result = $runner->run($this->fixture('Messy.php'));

        $this->assertSame(2, $result['exit_code']);
        $this->assertFalse($result['warm_boot'], 'First call must report a cold boot.');

        $decoded = json_decode($result['output'], true);
        $violations = $decoded['files'][0]['violations'] ?? [];
        $this->assertNotEmpty($violations, 'Messy fixture must produce violations.');
    }

    public function testCleanFileReportsNoViolations(): void
    {
        $runner = new PhpmdRunner(self::RULESETS);
        $result = $runner->run($this->fixture('Clean.php'));

        $this->assertSame(0, $result['exit_code']);
        $decoded = json_decode($result['output'], true);
        $this->assertSame([], $decoded['files'] ?? []);
    }

    public function testSecondCallIsWarmAndDoesNotLeakState(): void
    {
        $runner = new PhpmdRunner(self::RULESETS);
        $first = $runner->run($this->fixture('Messy.php'));
        $second = $runner->run($this->fixture('Messy.php'));

        $this->assertFalse($first['warm_boot']);
        $this->assertTrue($second['warm_boot'], 'Second call must reuse the warm process.');

        $firstCount = \count(json_decode($first['output'], true)['files'][0]['violations']);
        $secondCount = \count(json_decode($second['output'], true)['files'][0]['violations']);
        $this->assertSame($firstCount, $secondCount, 'Reused process must not leak rule state across calls.');
    }

    public function testEditedSourceIsReanalysedAcrossCalls(): void
    {
        // A minimal clean PHP file — no violations under any of the configured rulesets.
        // Parameter names must be ≥3 chars to avoid ShortVariable (naming ruleset).
        $cleanSource = <<<'PHP'
<?php
declare(strict_types=1);
class StalenessProbe
{
    public function compute(int $first, int $second): int
    {
        return $first + $second;
    }
}
PHP;

        // Same class but with an unused local variable — triggers unusedcode/UnusedLocalVariable.
        $messySource = <<<'PHP'
<?php
declare(strict_types=1);
class StalenessProbe
{
    public function compute(int $first, int $second): int
    {
        $unused = 42;
        return $first + $second;
    }
}
PHP;

        $path = $this->makeTempPhpFile($cleanSource);

        // Scope to the single ruleset under test (unusedcode). The broad RULESETS set
        // makes the "0 violations" baseline fragile — a rule from another ruleset could
        // fire on the clean fixture across PHPMD versions and fail for the wrong reason.
        $runner = new PhpmdRunner('unusedcode');

        // First call — file is clean, expect 0 violations.
        $before = $runner->run($path);
        $beforeDecoded = json_decode($before['output'], true);
        $this->assertIsArray($beforeDecoded, 'phpmd produced no/invalid JSON on first call: ' . $before['output']);
        $beforeViolations = $beforeDecoded['files'][0]['violations'] ?? [];
        $this->assertSame(
            0,
            \count($beforeViolations),
            'Clean fixture must produce 0 violations before edit.',
        );

        // Edit the file on disk to introduce an UnusedLocalVariable violation. No mtime
        // bump needed: PhpmdRunner builds a fresh PHPMD/Report per call and re-reads the
        // file content — it has no mtime-keyed cache.
        file_put_contents($path, $messySource);

        // Second call on the SAME runner instance — must re-read the file from disk.
        $after = $runner->run($path);
        $this->assertTrue($after['warm_boot'], 'Second call must use the warm process.');

        $afterDecoded = json_decode($after['output'], true);
        $this->assertIsArray($afterDecoded, 'phpmd produced no/invalid JSON on second call: ' . $after['output']);
        $afterViolations = $afterDecoded['files'][0]['violations'] ?? [];
        $this->assertGreaterThanOrEqual(
            1,
            \count($afterViolations),
            'Warm process must report violations introduced by on-disk edit (phpmd is NOT stale).',
        );
    }
}
