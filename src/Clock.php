<?php

declare(strict_types=1);

namespace EasySwoole\Trace;

interface Clock
{
    public function now(): int;
}
