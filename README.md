# EasySwoole Trace

一个轻量的 PHP 手动调用链耗时追踪器。它使用 `hrtime()` 单调时钟，支持串行和并行节点，并能在父节点结束时自动收口未正常结束的子节点。

## 安装与验证

```bash
composer install
composer test
```

## 使用

```php
use EasySwoole\Trace\Trace;

$trace = new Trace('request');
$point = $trace->next('controller');
$sub = $point->next('load-data');

$parallel1 = $sub->parallel('service-1');
$parallel2 = $sub->parallel('service-2');

$parallel1->endCall();
// $parallel2 没有正常结束。

$sub->endCall(); // parallel2 自动标记为 abandoned
$point->endCall();
$trace->endCall();

echo json_encode($trace, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
```

每条调用链都有一个 32 位十六进制 `traceId`，每个节点都有独立的 16 位十六进制 `spanId`，并通过 `parentSpanId` 关联父节点：

```php
$trace->traceId();
$point->spanId();
$point->parentSpanId();
```

接收上游调用链时，可以继续使用传入的 W3C Trace Context 标识：

```php
$trace = new Trace(
    name: 'request',
    traceId: $incomingTraceId,
    parentSpanId: $incomingSpanId,
);
```

`toArray()` 和 JSON 序列化保留树形结构。持久化前可以将节点转换成强类型 `SpanBean`，数据库字段统一以该 Bean 为准：

```php
use EasySwoole\Trace\Bean\SpanBean;

$span = $point->toSpanBean();
$span->traceId;
$span->spanId;
$span->status;
$span->params;
```

`spans()` 返回扁平的 `SpanBean` 集合：

```php
$beans = $trace->spans();
$repository->insertMany($beans);
```

从数据库读取同一个 `trace_id` 的全部记录并转换为 `SpanBean` 后，可以自动恢复原始调用树。输入顺序不作要求：

```php
$rows = $repository->findByTraceId(
    '4b0b2f5d54bb61e080ecce28ded4bc6a',
);
$beans = array_map(
    static fn (array $row): SpanBean => SpanBean::fromArray($row),
    $rows,
);

$trace = Trace::restoreFromBeans($beans);
$tree = $trace->toArray();
```

恢复过程会校验所有 Bean 的 `traceId` 一致、`spanId` 唯一、只有一个根节点，并检查父子关系、断链和循环。恢复后的所有节点自动标记为已经入库。

每行包含 `trace_id`、`span_id`、`parent_span_id`、真实 Unix 微秒时间、单调时钟计算出的 `duration_us`、状态和错误信息。

节点参数通过原始 `params` 数组记录，入库时由存储层编码成 JSON：

```php
$point = $trace->next('query-user', [
    'user_id' => 10001,
    'filters' => ['status' => 'active'],
]);

// 也可以在执行过程中整体替换。
$point->setParams($params);

$paramsJson = json_encode(
    $point->params(),
    JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
);
```

根 Trace 会记录调用方是否已经完成入库，默认值为 `false`。只有入库成功后才应调用 `markPersisted()`：

```php
if (!$trace->isPersisted()) {
    $repository->insertMany($trace->spans());
    $trace->markPersisted();
}
```

标记操作是幂等的，多次调用不会产生副作用。

协程中的节点可能晚于外层入库完成。可以设置自动持久化回调；回调会传播给已经存在和未来创建的子节点。节点析构时，如果自身尚未入库，就会调用该回调：

```php
$trace->setAutoPersist(
    static function (TracePoint $point) use ($repository): void {
        $repository->insertMany($point->spans(onlySelf: true));
    },
);

// 外层批量入库成功，现有节点会一起标记为已入库。
$repository->insertMany($trace->spans());
$trace->markPersisted();
```

此后创建的新节点初始仍为未入库状态，并继承自动持久化回调。回调成功返回后，该节点会自动标记为已入库。回调抛出异常时不会标记，并产生 `E_USER_WARNING`，但异常不会从析构函数继续抛出。

已经标记入库的运行中节点，在后续执行 `endCall()`、`fail()`、`timeout()` 或 `setParams()` 后会自动重新变为未入库状态。除了析构兜底，也可以主动触发一次补写：

```php
$point->persistIfNeeded();
```

`spans()` 默认返回当前节点及全部后代；自动补写单个节点时使用命名参数：

```php
$allBeans = $point->spans();
$currentBean = $point->spans(onlySelf: true);
```

节点状态使用 PHP 原生枚举 `TraceStatus`，包括 `Running`、`Success`、`Exception`、`Timeout` 和 `Abandoned`。数据库及 JSON 输出仍使用对应的小写字符串值。

```php
use EasySwoole\Trace\TraceStatus;

$point->endCall(TraceStatus::Success);

if ($point->status() === TraceStatus::Success) {
    // 调用成功
}
```

`endCall()` 可以安全地重复调用；节点结束后不能再添加子节点。

每个节点最多只能拥有一个 `next`，从而保证串行调用是一条链；同一个节点仍然可以拥有任意数量的并行节点：

```php
$next = $point->next('next-call');

$parallel1 = $point->parallel('parallel-1');
$parallel2 = $point->parallel('parallel-2');

// 再次调用会抛出 LogicException。
$point->next('another-call');
```

异常场景建议使用 `try/catch/finally`：

```php
$point = $trace->next('database');

try {
    // 执行业务调用
} catch (Throwable $error) {
    $point->fail($error);
    throw $error;
} finally {
    $point->endCall();
}
```
