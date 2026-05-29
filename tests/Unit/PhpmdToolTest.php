<?php

declare(strict_types=1);

namespace Dpt\McpPhpmdWarm\Tests\Unit;

use Dpt\McpPhpmdWarm\PhpmdTool;
use PHPUnit\Framework\TestCase;

final class PhpmdToolTest extends TestCase
{
    public function testRejectsPathOutsideWorkingDirectory(): void
    {
        $tool = new PhpmdTool();
        $result = $tool->analyse('/etc/hosts');

        $this->assertSame(-1, $result['exit_code']);
        $this->assertSame('SecurityError', $result['error_class'] ?? null);
    }

    public function testRejectsNonExistentPath(): void
    {
        $tool = new PhpmdTool();
        $result = $tool->analyse(getcwd() . '/does-not-exist-' . __LINE__ . '.php');

        $this->assertSame(-1, $result['exit_code']);
        $this->assertSame('SecurityError', $result['error_class'] ?? null);
    }
}
