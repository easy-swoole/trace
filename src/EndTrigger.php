<?php

declare(strict_types=1);

namespace EasySwoole\Trace;

enum EndTrigger: string
{
    case Manual = 'manual';
    case CoroutineDefer = 'coroutine_defer';
    case Destructor = 'destructor';
    case Parent = 'parent';
}
