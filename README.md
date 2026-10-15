# EasySwoole Trace

一个面向 PHP 8.1+ 的轻量级手动调用链追踪器。适合在 HTTP 请求、CLI 任务、Swoole 协程、RPC 调用和后台消费任务中记录串行/并行调用关系、耗时、状态、参数与异常，并将节点转换成统一的 `SpanBean` 后批量入库。

本组件只负责记录调用关系和耗时，不负责启动协程、调度并行任务或直接写数据库。

## 特性

- 使用 `hrtime()` 单调时钟计算耗时，不受系统时间校准影响。
- 同时记录 Unix 微秒时间，便于数据库查询、排序和展示。
- 使用 W3C Trace Context 兼容格式的 Trace ID 与 Span ID。
- 每个节点有一个串行后继节点和任意多个并行节点。
- 父节点结束时自动收口未结束的后代节点。
- 支持成功、异常、超时和未正常收口等状态。
- 支持协程延迟节点的自动持久化兜底。
- 通过 `SpanBean` 统一数据库字段。
- 支持从数据库 Bean 恢复完整调用树。
- 支持 JSON 树形输出和纯文本调用树展示。

## 环境要求

- PHP `>= 8.1`
- Composer

安装依赖并运行测试：

```bash
composer install
composer test
```

## 快速开始

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use EasySwoole\Trace\Trace;
use EasySwoole\Trace\Utility;

$trace = new Trace('http-request', params: [
    'method' => 'GET',
    'uri' => '/users/10001',
]);

$controller = $trace->next('UserController::profile');
$service = $controller->next('UserService::profile', [
    'user_id' => 10001,
]);

$orders = $service->parallel('order-service');
$coupons = $service->parallel('coupon-service');

$orders->endCall();
$coupons->endCall();
$service->endCall();
$controller->endCall();
$trace->endCall();

echo Utility::toTree($trace), PHP_EOL;
```

示例输出：

```text
Trace: 4b0b2f5d54bb61e080ecce28ded4bc6a
http-request [serial | success | 10.000 ms] (span: 0000000000000001)
└── UserController::profile [serial | success | 9.500 ms] (span: 0000000000000002)
    └── UserService::profile [serial | success | 8.000 ms] (span: 0000000000000003)
        ├── order-service [parallel | success | 5.000 ms] (span: 0000000000000004)
        └── coupon-service [parallel | success | 7.000 ms] (span: 0000000000000005)
```

## 调用链结构

### 串行后继节点

使用 `next()` 创建串行调用链。每个节点最多只能创建一个 `next`：

```php
$controller = $trace->next('controller');
$service = $controller->next('service');
$database = $service->next('database');
```

形成：

```text
trace → controller → service → database
```

同一个节点第二次调用 `next()` 会抛出 `LogicException`：

```php
$trace->next('first');
$trace->next('second'); // LogicException
```

### 并行节点

使用 `parallel()` 在当前节点下创建任意多个并行节点：

```php
$serviceA = $point->parallel('service-a');
$serviceB = $point->parallel('service-b');
$serviceC = $point->parallel('service-c');
```

`parallel()` 只标记节点与父节点的关系，不会真正启动协程或异步任务。实际并发需要由 Swoole 协程、进程池或其他异步框架完成。

一个节点可以同时拥有一个 `next` 和多个 `parallel`：

```php
$next = $point->next('next-call');
$parallel1 = $point->parallel('parallel-1');
$parallel2 = $point->parallel('parallel-2');
```

## 节点结束与状态

节点状态使用原生字符串枚举 `TraceStatus`：

```php
use EasySwoole\Trace\TraceStatus;

TraceStatus::Running;
TraceStatus::Success;
TraceStatus::Exception;
TraceStatus::Timeout;
TraceStatus::Abandoned;
```

正常结束：

```php
$point->endCall();
// 等价于：
$point->endCall(TraceStatus::Success);
```

异常结束：

```php
try {
    callRemoteService();
    $point->endCall();
} catch (Throwable $error) {
    $point->fail($error);
    throw $error;
}
```

超时：

```php
$point->timeout('deadline exceeded');
```

`endCall()`、`fail()` 和 `timeout()` 都是幂等的。节点第一次结束后，后续结束调用不会覆盖已有状态。

父节点结束时，所有尚未结束的后代节点会使用相同结束时间自动收口，并标记为 `TraceStatus::Abandoned`：

```php
$parallel1 = $point->parallel('service-1');
$parallel2 = $point->parallel('service-2');

$parallel1->endCall();
// parallel2 没有正常结束。

$point->endCall(); // parallel2 自动变为 abandoned
```

节点结束后不能继续创建 `next()` 或 `parallel()` 节点。

## 参数记录

创建节点时可以记录任意数组参数：

```php
$point = $trace->next('query-user', [
    'user_id' => 10001,
    'filters' => [
        'status' => 'active',
    ],
]);
```

执行过程中也可以整体替换：

```php
$point->setParams([
    'user_id' => 10002,
]);

$params = $point->params();
```

参数由追踪器原样保存。写数据库时可以由存储层编码为 JSON：

```php
$paramsJson = json_encode(
    $span->params,
    JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
);
```

### 参数注意事项

- 不要记录密码、Token、Cookie、Authorization 或密钥。
- 谨慎记录身份证号、银行卡号、手机号等个人信息。
- 不建议记录完整请求体、响应体、文件内容或大对象。
- 确保参数能够被 `json_encode()` 正确编码。
- 参数大小不受本组件限制，应由调用方和日志服务控制。

## Trace ID 与 Span ID

每条调用链共享一个 Trace ID，每个节点拥有独立的 Span ID，并使用 Parent Span ID 关联父节点：

```php
$trace->traceId();
$point->spanId();
$point->parentSpanId();
```

标识格式：

- Trace ID：32 位非零小写十六进制字符串。
- Span ID：16 位非零小写十六进制字符串。
- 根节点的 Parent Span ID 默认为 `null`。

接收上游调用链时，可以继续使用上游标识：

```php
$trace = new Trace(
    name: 'incoming-request',
    traceId: $incomingTraceId,
    parentSpanId: $incomingSpanId,
);
```

传入格式错误或全零的 ID 会抛出 `InvalidArgumentException`。本组件不会自动解析或生成 HTTP `traceparent` Header，Header 的收发应由应用层完成。

## HTTP 跨服务链路衔接

推荐使用 W3C Trace Context 的 `traceparent` Header 在服务之间传递链路上下文：

```text
traceparent: 00-{trace-id}-{parent-span-id}-{trace-flags}
```

例如：

```text
traceparent: 00-4b0b2f5d54bb61e080ecce28ded4bc6a-00f067aa0ba902b7-01
```

字段含义：

- `00`：Trace Context 版本。
- `4b0b...bc6a`：32 位 Trace ID。
- `00f0...02b7`：16 位上游 Span ID。
- `01`：Trace Flags，最低位为采样标志。

下面以“客户端 → 用户服务 A → 订单服务 B”为例。

### 解析和生成 traceparent

下面的辅助代码可以放在应用自己的 HTTP 中间件中：

```php
/**
 * @return null|array{trace_id: string, parent_span_id: string, flags: string}
 */
function parseTraceparent(?string $header): ?array
{
    if ($header === null) {
        return null;
    }

    $header = strtolower(trim($header));
    if (!preg_match(
        '/^00-([0-9a-f]{32})-([0-9a-f]{16})-([0-9a-f]{2})$/',
        $header,
        $matches,
    )) {
        return null;
    }

    // W3C 不允许全零 Trace ID 或 Span ID。
    if (trim($matches[1], '0') === '' || trim($matches[2], '0') === '') {
        return null;
    }

    return [
        'trace_id' => $matches[1],
        'parent_span_id' => $matches[2],
        'flags' => $matches[3],
    ];
}

function buildTraceparent(
    string $traceId,
    string $parentSpanId,
    string $flags = '01',
): string {
    return sprintf('00-%s-%s-%s', $traceId, $parentSpanId, $flags);
}
```

生产环境可以把这两个函数封装进框架中间件；不要在每个控制器里重复解析 Header。

### 服务 A：接收上游请求

服务 A 的 HTTP 中间件读取请求 Header。存在合法 `traceparent` 时继续上游链路，否则创建一条新链路：

```php
use EasySwoole\Trace\Trace;

$incoming = parseTraceparent(
    $request->getHeaderLine('traceparent'),
);

if ($incoming === null) {
    $trace = new Trace('user-service.request', params: [
        'method' => $request->getMethod(),
        'route' => '/users/{id}',
    ]);
    $traceFlags = '01';
} else {
    $trace = new Trace(
        name: 'user-service.request',
        traceId: $incoming['trace_id'],
        parentSpanId: $incoming['parent_span_id'],
        params: [
            'method' => $request->getMethod(),
            'route' => '/users/{id}',
        ],
    );
    $traceFlags = $incoming['flags'];
}

$controller = $trace->next('UserController::profile');
```

此时服务 A 的根节点使用相同 Trace ID，并通过 `parentSpanId` 指向上游服务的 Span。

### 服务 A：调用服务 B

服务 A 调用订单服务 B 前，先创建代表这次 HTTP 客户端调用的 Span。发送给 B 的 Parent Span ID 必须是这个客户端 Span 的 ID，而不是服务 A 根节点的 ID：

```php
$orderRequest = $controller->parallel('HTTP GET order-service', [
    'method' => 'GET',
    'host' => 'order-service',
    'route' => '/orders',
]);

$headers = [
    'traceparent' => buildTraceparent(
        $trace->traceId(),
        $orderRequest->spanId(),
        $traceFlags,
    ),
];

try {
    $response = $httpClient->get(
        'http://order-service/orders?user_id=10001',
        headers: $headers,
    );

    if ($response->getStatusCode() >= 500) {
        $orderRequest->fail(
            new RuntimeException('order-service returned 5xx'),
        );
    } else {
        $orderRequest->endCall();
    }
} catch (Throwable $error) {
    $orderRequest->fail($error);
    throw $error;
}
```

链路关系为：

```text
上游服务 Span
└── user-service.request
    └── UserController::profile
        └── HTTP GET order-service
```

### 服务 B：继续服务 A 的链路

订单服务 B 收到请求后，同样在 HTTP 中间件中解析 `traceparent`：

```php
$incoming = parseTraceparent(
    $request->getHeaderLine('traceparent'),
);

$trace = $incoming === null
    ? new Trace('order-service.request')
    : new Trace(
        name: 'order-service.request',
        traceId: $incoming['trace_id'],
        parentSpanId: $incoming['parent_span_id'],
    );

$controller = $trace->next('OrderController::list');
$database = $controller->next('query-orders', [
    'user_id' => 10001,
]);

try {
    $orders = queryOrders(10001);
    $database->endCall();
    $controller->endCall();
    $trace->endCall();
} catch (Throwable $error) {
    $database->fail($error);
    $controller->fail($error);
    $trace->fail($error);
    throw $error;
}
```

服务 A 和服务 B 入库后，通过相同的 `trace_id` 可以查询到完整跨服务链路：

```text
user-service.request
└── UserController::profile
    └── HTTP GET order-service
        └── order-service.request
            └── OrderController::list
                └── query-orders
```

数据库中的服务 B 根 Span，其 `parent_span_id` 等于服务 A 中 `HTTP GET order-service` Span 的 `span_id`。

### HTTP 跨服务注意事项

- 优先使用标准 `traceparent`，不要同时维护含义重复的私有 Trace Header。
- 永远不要直接信任外部 Header；格式非法、版本不支持或 ID 全零时，应创建新的 Trace。
- 下游 Header 中的 Parent Span ID 应使用“本次 HTTP 客户端调用节点”的 Span ID。
- Trace ID 在整条跨服务链路中保持不变；每个服务和调用节点都生成新的 Span ID。
- `traceparent` 只传递链路上下文，不应放入用户资料、认证信息或业务参数。
- 如果需要严格遵循采样决策，应原样传播 Trace Flags；当前组件本身不执行采样。
- HTTP 客户端 Span 应覆盖完整网络调用时间，并在响应、异常或超时时结束。
- 服务端根 Span 应覆盖完整请求生命周期，建议在 HTTP 中间件的 `finally` 阶段统一收口和入库。
- 多个服务可能并发写入相同 Trace ID，数据库必须使用 Span ID 区分记录。
- 跨服务恢复整棵树时，需要从共享存储或日志分析服务查询该 Trace ID 的所有 Span。

## 时间与耗时

每个 `SpanBean` 同时包含两类时间信息：

- `startedAtUnixUs` / `endedAtUnixUs`：Unix 微秒时间，用于入库、排序和展示。
- `durationUs`：使用 `hrtime()` 单调时钟计算的耗时。

不要使用 Unix 时间直接计算高精度耗时，因为系统时间可能被 NTP 或管理员调整。

## SpanBean 与数据库持久化

单节点转换：

```php
use EasySwoole\Trace\Bean\SpanBean;

$span = $point->toSpanBean();
```

整条调用链转换为扁平 Bean 集合：

```php
/** @var SpanBean[] $spans */
$spans = $trace->spans();
```

只获取当前节点：

```php
$current = $point->spans(onlySelf: true);
```

`SpanBean` 字段：

| 属性 | 数据库字段 | 类型 | 说明 |
|---|---|---|---|
| `traceId` | `trace_id` | `string` | 调用链 ID |
| `spanId` | `span_id` | `string` | 当前节点 ID |
| `parentSpanId` | `parent_span_id` | `?string` | 父节点 ID |
| `name` | `name` | `string` | 节点名称 |
| `type` | `type` | `string` | `serial` 或 `parallel` |
| `status` | `status` | `TraceStatus` | 节点状态 |
| `startedAtUnixUs` | `started_at_unix_us` | `int` | 开始时间 |
| `endedAtUnixUs` | `ended_at_unix_us` | `?int` | 结束时间 |
| `durationUs` | `duration_us` | `int` | 单调时钟耗时 |
| `params` | `params` | `array` | 原始参数，入库时可编码为 JSON |
| `error` | `error` | `?string` | 异常类名与错误消息 |

转换成数据库字段数组：

```php
$row = $span->toArray();
```

一个简单的批量入库流程：

```php
if (!$trace->isPersisted()) {
    $repository->insertMany($trace->spans());
    $trace->markPersisted();
}
```

只有数据库事务成功提交后才应调用 `markPersisted()`。该方法默认递归标记当前节点及所有已经存在的后代节点，并且支持重复调用。

## 协程与自动持久化

在协程模式下，外层流程可能先完成批量入库，而内部协程之后才创建或结束新的节点。可以通过 `setAutoPersist()` 设置补写回调：

```php
use EasySwoole\Trace\TracePoint;

$trace->setAutoPersist(
    static function (TracePoint $point) use ($repository): void {
        $repository->insertMany(
            $point->spans(onlySelf: true),
        );
    },
);

// 在外层入库前先建立一个仍处于运行状态的协程范围。
$asyncScope = $trace->parallel('async-scope');
```

回调会自动传播给：

- 设置回调前已经存在的所有后代节点。
- 设置回调后创建的 `next()` 节点。
- 设置回调后创建的 `parallel()` 节点。
- 后续任意深度的新节点。

典型流程：

```php
// 外层先批量写入当前已经存在的节点。
$repository->insertMany($trace->spans());
$trace->markPersisted();

// 内部协程之后创建的新节点默认是未持久化状态，
// 并且已经继承 auto-persist callback。
$latePoint = $asyncScope->next('late-coroutine');
```

如果节点创建于 Swoole 协程中，会自动为该节点注册一次 `Coroutine::defer()`。协程结束时如果节点仍未持久化，会调用回调并传入当前节点。非协程环境不会注册 `defer`，节点析构仍会执行相同的兜底逻辑。

协程检测不要求项目必须安装 Swoole 扩展；没有安装扩展或当前不在协程中时会直接跳过。`defer` 使用弱引用关联节点，不会因为兜底回调而额外延长节点生命周期。

也可以在协程结束前主动补写：

```php
$point->persistIfNeeded();
```

`persistIfNeeded()` 返回本次是否执行了回调：

```php
$didPersist = $point->persistIfNeeded();
```

已经标记入库的节点，在后续执行以下操作后会重新变成未持久化状态：

- `endCall()`
- `fail()`
- `timeout()`
- `setParams()`

这保证运行中的协程节点结束后能够补写最终状态。

### 自动持久化注意事项

- 析构回调只是兜底机制，不保证在业务期望的准确时间执行。
- Swoole 协程中的 `defer` 比析构更及时，但仍属于兜底；业务可以控制流程时仍应显式持久化。
- 优先在协程结束前显式调用 `persistIfNeeded()`。
- 回调应尽量短小，不要在析构阶段执行长时间阻塞操作。
- 回调必须在数据库连接和依赖对象仍然可用时执行。
- 回调抛出异常时不会标记成功，并产生 `E_USER_WARNING`。
- 回调应只写当前节点，使用 `spans(onlySelf: true)`，避免重复写入整棵树。
- 不要让回调强引用当前 Trace，否则可能形成引用环并推迟析构。
- 数据库应以 `(trace_id, span_id)` 建立唯一约束，并使用 upsert 支持节点状态补写。
- `defer` 执行时不会自动结束仍在运行的节点，只会按节点当时的状态补写。应在业务逻辑中先调用 `endCall()`、`fail()` 或 `timeout()`。
- 自动补写不允许绕过节点生命周期：父节点一旦 `endCall()`，就不能再创建后继或并行节点。
- 如果内部协程需要晚于外层入库继续建链，应保持对应的协程范围节点为运行状态，只先入库和 `markPersisted()`，待协程完成后再结束节点。

## 从数据库恢复调用树

数据库查询结果可以通过 `SpanBean::fromArray()` 转换。该方法支持数据库驱动返回的整数字符串，也支持 `params` 为数组或 JSON 字符串：

```php
use EasySwoole\Trace\Bean\SpanBean;
use EasySwoole\Trace\Utility;

$rows = $repository->findByTraceId(
    '4b0b2f5d54bb61e080ecce28ded4bc6a',
);

$beans = array_map(
    static fn (array $row): SpanBean => SpanBean::fromArray($row),
    $rows,
);

$trace = Utility::restoreFromBeans($beans);
```

输入顺序没有要求。恢复过程会校验：

- Bean 集合不能为空。
- 所有 Bean 必须属于同一个 Trace ID。
- Span ID 不能重复。
- 必须能够确定唯一根节点。
- 父子关系不能断裂或形成循环。
- 每个节点最多只能有一个串行后继节点。

恢复后的所有节点会自动标记为已经持久化，避免析构时重复补写。

恢复调用树需要查询该 Trace 范围内的完整 Span 集合。如果只传入部分数据，缺失父节点的 Span 可能被识别为新的根节点，最终导致多根或断链校验失败。

## 输出调用树

实时 Trace 和数据库恢复后的 Trace 都可以输出为文本树：

```php
echo Utility::toTree($trace), PHP_EOL;
```

输出包含：

- Trace ID
- 节点名称
- 串行或并行类型
- 节点状态
- 毫秒耗时
- Span ID
- 异常信息

获取完整树形数组：

```php
$tree = $trace->toArray();
```

JSON 输出：

```php
echo json_encode(
    $trace,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
);
```

`toArray()` 和 JSON 序列化包含递归 `children`；`spans()` 返回不包含 `children` 的扁平 `SpanBean[]`，适合数据库入库。

## API 速查

### Trace / TracePoint

```php
$point->next($name, $params);          // 创建唯一串行后继节点
$point->parallel($name, $params);      // 创建并行节点
$point->endCall();                     // 成功结束
$point->fail($throwable);              // 异常结束
$point->timeout($message);             // 超时结束
$point->setParams($params);            // 替换节点参数
$point->params();                      // 获取参数
$point->status();                      // 获取 TraceStatus
$point->children();                    // 获取直接子节点
$point->toSpanBean();                  // 转换当前节点
$point->spans();                       // 当前节点及所有后代 Bean
$point->spans(onlySelf: true);         // 仅当前节点 Bean
$point->toArray();                     // 树形数组
$point->setAutoPersist($callback);     // 设置自动补写回调
$point->persistIfNeeded();             // 主动执行补写
$point->isPersisted();                 // 是否已持久化
$point->markPersisted();               // 标记已持久化
```

### Utility

```php
Utility::restoreFromBeans($beans);     // 从 Bean 恢复调用树
Utility::toTree($trace);               // 输出文本调用树
```

## 常见注意点

1. `next()` 表示唯一串行后继，不是普通的子节点集合；多个并列调用应使用 `parallel()`。
2. 父节点结束会递归结束未完成后代，因此不要过早调用父节点的 `endCall()`。
3. `parallel()` 不会创建协程，只记录调用关系。
4. `markPersisted()` 必须在数据库写入成功后调用。
5. 对同一个 Span 的多次写入应使用唯一键和 upsert，尤其是在协程补写场景下。
6. 不要使用 Unix 时间差代替 `durationUs` 作为精确耗时。
7. 自动持久化依赖对象析构，只应作为兜底；优先显式调用 `persistIfNeeded()`。
8. `SpanBean::fromArray()` 会严格校验字段、ID、状态、类型和时间，非法数据库数据会抛出 `InvalidArgumentException`。
9. 节点结束后不能继续添加调用节点。
10. `toArray()` 适合展示，`spans()` 适合入库，不要把递归 `children` 写入 Span 表。
