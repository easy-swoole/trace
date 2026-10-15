<?php

declare(strict_types=1);

namespace EasySwoole\Trace;

use Closure;
use Swoole\Coroutine;
use Throwable;

final class SwooleCoroutineDefer implements CoroutineDefer
{
    public function register(Closure $callback): bool
    {
        if (!class_exists(Coroutine::class)
            || !method_exists(Coroutine::class, 'getCid')
            || !method_exists(Coroutine::class, 'defer')) {
            return false;
        }

        try {
            if (Coroutine::getCid() < 0) {
                return false;
            }

            Coroutine::defer($callback);

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
