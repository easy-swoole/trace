<?php

declare(strict_types=1);

namespace EasySwoole\Trace;

enum TraceStatus: string
{
    case Running = 'running';
    case Success = 'success';
    case Exception = 'exception';
    case Timeout = 'timeout';
    case Abandoned = 'abandoned';
}
