<?php

declare(strict_types=1);

namespace EasySwoole\Trace;

interface IdGenerator
{
    /** Returns a W3C-compatible 32-character lowercase hexadecimal trace ID. */
    public function traceId(): string;

    /** Returns a W3C-compatible 16-character lowercase hexadecimal span ID. */
    public function spanId(): string;
}
