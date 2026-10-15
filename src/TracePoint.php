<?php

declare(strict_types=1);

namespace EasySwoole\Trace;

use Closure;
use EasySwoole\Trace\Bean\SpanBean;
use InvalidArgumentException;
use JsonSerializable;
use LogicException;
use Throwable;
use WeakReference;

class TracePoint implements JsonSerializable
{
    public const TYPE_SERIAL = 'serial';
    public const TYPE_PARALLEL = 'parallel';

    private ?int $endedAt = null;
    private ?int $endedAtUnixUs = null;
    private TraceStatus $status = TraceStatus::Running;
    private ?string $error = null;
    private int $nextChildNumber = 1;

    /** @var array<string, mixed> */
    private array $params = [];

    /** @var list<TracePoint> */
    private array $children = [];

    private ?TracePoint $nextPoint = null;
    private bool $persisted = false;
    private ?EndTrigger $endedBy = null;

    /** @var null|Closure(TracePoint): void */
    private ?Closure $autoPersistCallback = null;
    private bool $coroutineDeferRegistered = false;

    protected function __construct(
        private readonly string $name,
        private readonly string $type,
        private readonly Clock $clock,
        private readonly WallClock $wallClock,
        private readonly IdGenerator $idGenerator,
        private readonly string $traceId,
        private readonly string $spanId,
        private readonly ?string $parentSpanId,
        private readonly int $startedAt,
        private readonly int $startedAtUnixUs,
        private readonly CoroutineDefer $coroutineDefer,
    ) {
        if (!in_array($type, [self::TYPE_SERIAL, self::TYPE_PARALLEL], true)) {
            throw new LogicException("unknown trace point type: {$type}");
        }

        self::assertIdentifier($traceId, 32, 'trace ID');
        self::assertIdentifier($spanId, 16, 'span ID');
        if ($parentSpanId !== null) {
            self::assertIdentifier($parentSpanId, 16, 'parent span ID');
        }

        $this->registerCoroutineDefer();
    }

    /** @param array<string, mixed> $params */
    public function next(?string $name = null, array $params = []): self
    {
        if ($this->nextPoint !== null) {
            throw new LogicException(
                "trace point '{$this->name}' already has a next point",
            );
        }

        $this->nextPoint = $this->addChild($name, self::TYPE_SERIAL, $params);

        return $this->nextPoint;
    }

    /** @param array<string, mixed> $params */
    public function parallel(?string $name = null, array $params = []): self
    {
        return $this->addChild($name, self::TYPE_PARALLEL, $params);
    }

    public function endCall(
        TraceStatus $status = TraceStatus::Success,
        ?Throwable $error = null,
    ): self
    {
        if ($this->isEnded()) {
            return $this;
        }

        $this->finishAt(
            $this->clock->now(),
            $this->wallClock->nowUnixUs(),
            $status,
            EndTrigger::Manual,
            $error,
        );

        return $this;
    }

    public function fail(Throwable $error): self
    {
        return $this->endCall(TraceStatus::Exception, $error);
    }

    public function timeout(?string $message = null): self
    {
        if ($this->isEnded()) {
            return $this;
        }

        $this->error = $message;
        $this->finishAt(
            $this->clock->now(),
            $this->wallClock->nowUnixUs(),
            TraceStatus::Timeout,
            EndTrigger::Manual,
        );

        return $this;
    }

    public function isEnded(): bool
    {
        return $this->endedAt !== null;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function status(): TraceStatus
    {
        return $this->status;
    }

    public function traceId(): string
    {
        return $this->traceId;
    }

    public function spanId(): string
    {
        return $this->spanId;
    }

    public function parentSpanId(): ?string
    {
        return $this->parentSpanId;
    }

    public function isPersisted(): bool
    {
        return $this->persisted;
    }

    public function markPersisted(bool $withChildren = true): self
    {
        $this->persisted = true;

        if ($withChildren) {
            foreach ($this->children as $child) {
                $child->markPersisted();
            }
        }

        return $this;
    }

    /**
     * 注册自动持久化兜底回调；节点析构且尚未持久化时执行。
     * 回调会传播给已经存在以及后续创建的所有子节点。
     *
     * @param callable(TracePoint): void $callback
     */
    public function setAutoPersist(callable $callback): self
    {
        $this->autoPersistCallback = Closure::fromCallable($callback);
        $this->registerCoroutineDefer();

        foreach ($this->children as $child) {
            $child->setAutoPersist($this->autoPersistCallback);
        }

        return $this;
    }

    /** @param array<string, mixed> $params */
    public function setParams(array $params): self
    {
        $this->params = $params;
        $this->persisted = false;

        return $this;
    }

    /** @return array<string, mixed> */
    public function params(): array
    {
        return $this->params;
    }

    public function durationMs(): float
    {
        return $this->durationUs() / 1_000;
    }

    /** @return list<TracePoint> */
    public function children(): array
    {
        return $this->children;
    }

    public function toSpanBean(): SpanBean
    {
        return new SpanBean(
            traceId: $this->traceId,
            spanId: $this->spanId,
            parentSpanId: $this->parentSpanId,
            name: $this->name,
            type: $this->type,
            status: $this->status,
            startedAtUnixUs: $this->startedAtUnixUs,
            endedAtUnixUs: $this->endedAtUnixUs,
            durationUs: $this->durationUs(),
            params: $this->params,
            error: $this->error,
            endedBy: $this->endedBy,
        );
    }

    /** @return list<SpanBean> */
    public function spans(bool $onlySelf = false): array
    {
        $spans = [$this->toSpanBean()];

        if ($onlySelf) {
            return $spans;
        }

        foreach ($this->children as $child) {
            array_push($spans, ...$child->spans());
        }

        return $spans;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            ...$this->toSpanBean()->toArray(),
            'duration_ms' => round($this->durationMs(), 3),
            'children' => array_map(
                static fn (TracePoint $child): array => $child->toArray(),
                $this->children,
            ),
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /** @param array<string, mixed> $params */
    private function addChild(?string $name, string $type, array $params): self
    {
        if ($this->isEnded()) {
            throw new LogicException("cannot add a child to ended trace point '{$this->name}'");
        }

        $number = $this->nextChildNumber++;
        $child = new self(
            $name ?? "point-{$number}",
            $type,
            $this->clock,
            $this->wallClock,
            $this->idGenerator,
            $this->traceId,
            $this->idGenerator->spanId(),
            $this->spanId,
            $this->clock->now(),
            $this->wallClock->nowUnixUs(),
            $this->coroutineDefer,
        );
        $child->setParams($params);
        if ($this->autoPersistCallback !== null) {
            $child->setAutoPersist($this->autoPersistCallback);
        }
        $this->children[] = $child;

        return $child;
    }

    private function finishAt(
        int $endedAt,
        int $endedAtUnixUs,
        TraceStatus $status,
        EndTrigger $endedBy,
        ?Throwable $error = null,
        bool $withChildren = true,
    ): void {
        if ($withChildren) {
            foreach ($this->children as $child) {
                if (!$child->isEnded()) {
                    $child->finishAt(
                        $endedAt,
                        $endedAtUnixUs,
                        TraceStatus::Abandoned,
                        EndTrigger::Parent,
                    );
                }
            }
        }

        $this->endedAt = max($endedAt, $this->startedAt);
        $this->endedAtUnixUs = max($endedAtUnixUs, $this->startedAtUnixUs);
        $this->status = $status;
        $this->endedBy = $endedBy;
        $this->persisted = false;

        if ($error !== null) {
            $this->error = sprintf('%s: %s', $error::class, $error->getMessage());
        }
    }

    private function durationUs(): int
    {
        $end = $this->endedAt ?? $this->clock->now();

        return max(0, intdiv($end - $this->startedAt, 1_000));
    }

    public function __destruct()
    {
        $this->finishAndPersistSafely(EndTrigger::Destructor);
    }

    /**
     * 为当前节点执行一次已配置的持久化兜底回调。
     *
     * @return bool 本次是否执行了回调。
     */
    public function persistIfNeeded(): bool
    {
        if ($this->persisted || $this->autoPersistCallback === null) {
            return false;
        }

        ($this->autoPersistCallback)($this);
        $this->markPersisted(withChildren: false);

        return true;
    }

    /** 在协程结束时增加一次自动持久化兜底。 */
    private function registerCoroutineDefer(): void
    {
        if ($this->coroutineDeferRegistered) {
            return;
        }

        $reference = WeakReference::create($this);
        $this->coroutineDeferRegistered = $this->coroutineDefer->register(
            static function () use ($reference): void {
                $reference->get()?->finishAndPersistSafely(EndTrigger::CoroutineDefer);
            },
        );
    }

    /** 自动持久化失败时仅产生警告，避免影响析构或协程清理流程。 */
    private function finishAndPersistSafely(EndTrigger $trigger): void
    {
        try {
            if (!$this->isEnded()) {
                $this->finishAt(
                    $this->clock->now(),
                    $this->wallClock->nowUnixUs(),
                    TraceStatus::Abandoned,
                    $trigger,
                    withChildren: false,
                );
            }

            $this->persistIfNeeded();
        } catch (Throwable $error) {
            trigger_error(
                sprintf(
                    "auto-persist failed for trace point '%s': %s",
                    $this->name,
                    $error->getMessage(),
                ),
                E_USER_WARNING,
            );
        }
    }

    /** @internal 仅供 Utility 重建已持久化调用链时使用。 */
    public static function restoreFromSpanBean(
        SpanBean $span,
        Clock $clock,
        WallClock $wallClock,
        IdGenerator $idGenerator,
    ): self {
        $startedAt = $clock->now() - ($span->durationUs * 1_000);
        $point = new self(
            name: $span->name,
            type: $span->type,
            clock: $clock,
            wallClock: $wallClock,
            idGenerator: $idGenerator,
            traceId: $span->traceId,
            spanId: $span->spanId,
            parentSpanId: $span->parentSpanId,
            startedAt: $startedAt,
            startedAtUnixUs: $span->startedAtUnixUs,
            coroutineDefer: new SwooleCoroutineDefer(),
        );
        $point->restoreState($span, $startedAt);

        return $point;
    }

    protected function restoreState(SpanBean $span, int $startedAt): void
    {
        $this->status = $span->status;
        $this->endedAtUnixUs = $span->endedAtUnixUs;
        $this->endedAt = $span->status === TraceStatus::Running
            ? null
            : $startedAt + ($span->durationUs * 1_000);
        $this->params = $span->params;
        $this->error = $span->error;
        $this->endedBy = $span->endedBy;
    }

    /** @internal 仅供 Utility 重建已持久化调用链时使用。 */
    public function attachRestoredChild(self $child): void
    {
        if ($child->parentSpanId !== $this->spanId) {
            throw new LogicException(
                "span '{$child->spanId}' does not belong to parent '{$this->spanId}'",
            );
        }

        if ($child->type === self::TYPE_SERIAL) {
            if ($this->nextPoint !== null) {
                throw new LogicException(
                    "trace point '{$this->name}' has multiple restored next points",
                );
            }

            $this->nextPoint = $child;
        }

        $this->children[] = $child;
    }

    private static function assertIdentifier(string $id, int $length, string $name): void
    {
        if (!preg_match('/^[0-9a-f]{' . $length . '}$/', $id) || trim($id, '0') === '') {
            throw new InvalidArgumentException(
                sprintf('the %s must be a non-zero, %d-character lowercase hexadecimal string', $name, $length),
            );
        }
    }

}
