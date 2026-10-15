<?php

declare(strict_types=1);

namespace EasySwoole\Trace;

use EasySwoole\Trace\Bean\SpanBean;

final class Trace extends TracePoint
{
    /** @param array<string, mixed> $params */
    public function __construct(
        string $name = 'trace',
        ?Clock $clock = null,
        ?string $traceId = null,
        ?string $parentSpanId = null,
        ?IdGenerator $idGenerator = null,
        ?WallClock $wallClock = null,
        array $params = [],
        ?SpanBean $restoredSpan = null,
        ?CoroutineDefer $coroutineDefer = null,
    ) {
        $clock ??= new HrtimeClock();
        $wallClock ??= new SystemWallClock();
        $idGenerator ??= new RandomIdGenerator();
        $coroutineDefer ??= new SwooleCoroutineDefer();

        if ($restoredSpan !== null) {
            $startedAt = $clock->now() - ($restoredSpan->durationUs * 1_000);
            parent::__construct(
                name: $restoredSpan->name,
                type: $restoredSpan->type,
                clock: $clock,
                wallClock: $wallClock,
                idGenerator: $idGenerator,
                traceId: $restoredSpan->traceId,
                spanId: $restoredSpan->spanId,
                parentSpanId: $restoredSpan->parentSpanId,
                startedAt: $startedAt,
                startedAtUnixUs: $restoredSpan->startedAtUnixUs,
                coroutineDefer: $coroutineDefer,
            );
            $this->restoreState($restoredSpan, $startedAt);

            return;
        }

        $traceId ??= $idGenerator->traceId();

        parent::__construct(
            name: $name,
            type: self::TYPE_SERIAL,
            clock: $clock,
            wallClock: $wallClock,
            idGenerator: $idGenerator,
            traceId: $traceId,
            spanId: $idGenerator->spanId(),
            parentSpanId: $parentSpanId,
            startedAt: $clock->now(),
            startedAtUnixUs: $wallClock->nowUnixUs(),
            coroutineDefer: $coroutineDefer,
        );

        $this->setParams($params);
    }

}
