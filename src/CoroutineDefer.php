<?php

declare(strict_types=1);

namespace EasySwoole\Trace;

use Closure;

interface CoroutineDefer
{
    /**
     * 在当前协程结束时执行回调。
     *
     * @return bool 当前是否处于受支持的协程环境并成功注册。
     */
    public function register(Closure $callback): bool;
}
