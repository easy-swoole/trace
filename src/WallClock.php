<?php

declare(strict_types=1);

namespace EasySwoole\Trace;

interface WallClock
{
    /** Returns Unix time in microseconds. */
    public function nowUnixUs(): int;
}
