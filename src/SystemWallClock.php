<?php

declare(strict_types=1);

namespace EasySwoole\Trace;

final class SystemWallClock implements WallClock
{
    public function nowUnixUs(): int
    {
        return (int) round(microtime(true) * 1_000_000);
    }
}
