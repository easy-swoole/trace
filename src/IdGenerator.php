<?php

declare(strict_types=1);

namespace EasySwoole\Trace;

interface IdGenerator
{
    /** 返回符合 W3C 格式的 32 位小写十六进制 Trace ID。 */
    public function traceId(): string;

    /** 返回符合 W3C 格式的 16 位小写十六进制 Span ID。 */
    public function spanId(): string;
}
