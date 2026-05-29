<?php

declare(strict_types=1);

namespace Fixtures;

final class Clean
{
    public function add(int $first, int $second): int
    {
        return $first + $second;
    }
}
