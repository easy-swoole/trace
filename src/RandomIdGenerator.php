<?php

declare(strict_types=1);

namespace EasySwoole\Trace;

final class RandomIdGenerator implements IdGenerator
{
    public function traceId(): string
    {
        return $this->nonZeroHex(16);
    }

    public function spanId(): string
    {
        return $this->nonZeroHex(8);
    }

    private function nonZeroHex(int $bytes): string
    {
        do {
            $id = bin2hex(random_bytes($bytes));
        } while (trim($id, '0') === '');

        return $id;
    }
}
