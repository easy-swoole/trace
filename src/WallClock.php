<?php

declare(strict_types=1);

namespace EasySwoole\Trace;

interface WallClock
{
    /** 返回 Unix 微秒时间戳。 */
    public function nowUnixUs(): int;
}
