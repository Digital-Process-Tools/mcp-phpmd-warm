<?php

declare(strict_types=1);

namespace Dpt\McpPhpmdWarm\Tests\Integration;

use Dpt\McpPhpmdWarm\PhpmdRunner;
use PHPUnit\Framework\TestCase;

final class PhpmdRunnerTest extends TestCase
{
    private const RULESETS = 'cleancode,codesize,design,naming,unusedcode';

    private function fixture(string $name): string
    {
        return \dirname(__DIR__) . '/Fixtures/' . $name;
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
}
