<?php

declare(strict_types=1);

namespace EasySwoole\Trace;

final class HrtimeClock implements Clock
{
    public function now(): int
    {
        return hrtime(true);
    }
}
