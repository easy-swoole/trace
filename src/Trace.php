<?php

declare(strict_types=1);

namespace EasySwoole\Trace;

use EasySwoole\Trace\Bean\SpanBean;
use InvalidArgumentException;

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
    ) {
        $clock ??= new HrtimeClock();
        $wallClock ??= new SystemWallClock();
        $idGenerator ??= new RandomIdGenerator();

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
            );
            $this->restoreState($restoredSpan, $startedAt);

            return;
        }

        $traceId ??= $idGenerator->traceId();

        self::assertIdentifier($traceId, 32, 'trace ID');
        if ($parentSpanId !== null) {
            self::assertIdentifier($parentSpanId, 16, 'parent span ID');
        }

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
        );

        $this->setParams($params);
    }

    /**
     * Rebuilds a trace tree from database SpanBean rows.
     *
     * @param list<SpanBean> $beans
     */
    public static function restoreFromBeans(
        array $beans,
        ?Clock $clock = null,
        ?WallClock $wallClock = null,
        ?IdGenerator $idGenerator = null,
    ): self {
        if ($beans === []) {
            throw new InvalidArgumentException('Cannot restore a trace from an empty SpanBean list');
        }

        $clock ??= new HrtimeClock();
        $wallClock ??= new SystemWallClock();
        $idGenerator ??= new RandomIdGenerator();
        $traceId = null;
        $beansBySpanId = [];

        foreach ($beans as $bean) {
            if (!$bean instanceof SpanBean) {
                throw new InvalidArgumentException('Every restored item must be a SpanBean');
            }

            $traceId ??= $bean->traceId;
            if ($bean->traceId !== $traceId) {
                throw new InvalidArgumentException('All SpanBeans must have the same trace ID');
            }

            if (isset($beansBySpanId[$bean->spanId])) {
                throw new InvalidArgumentException("Duplicate span ID '{$bean->spanId}'");
            }

            $beansBySpanId[$bean->spanId] = $bean;
        }

        $roots = array_values(array_filter(
            $beans,
            static fn (SpanBean $bean): bool => $bean->parentSpanId === null
                || !isset($beansBySpanId[$bean->parentSpanId]),
        ));

        if (count($roots) !== 1) {
            throw new InvalidArgumentException('The SpanBean list must contain exactly one root span');
        }

        $rootBean = $roots[0];
        $childrenByParent = [];
        foreach ($beans as $bean) {
            if ($bean === $rootBean) {
                continue;
            }

            $childrenByParent[$bean->parentSpanId][] = $bean;
        }

        foreach ($childrenByParent as &$children) {
            usort(
                $children,
                static fn (SpanBean $left, SpanBean $right): int =>
                    $left->startedAtUnixUs <=> $right->startedAtUnixUs,
            );
        }
        unset($children);

        $trace = new self(
            clock: $clock,
            idGenerator: $idGenerator,
            wallClock: $wallClock,
            restoredSpan: $rootBean,
        );
        $visited = [$rootBean->spanId => true];

        $restoreChildren = function (TracePoint $parent) use (
            &$restoreChildren,
            &$visited,
            $childrenByParent,
            $clock,
            $wallClock,
            $idGenerator,
        ): void {
            foreach ($childrenByParent[$parent->spanId()] ?? [] as $childBean) {
                if (isset($visited[$childBean->spanId])) {
                    throw new InvalidArgumentException('The SpanBean list contains a cycle');
                }

                $visited[$childBean->spanId] = true;
                $child = self::restoreFromSpanBean(
                    $childBean,
                    $clock,
                    $wallClock,
                    $idGenerator,
                );
                $parent->attachRestoredChild($child);
                $restoreChildren($child);
            }
        };
        $restoreChildren($trace);

        if (count($visited) !== count($beans)) {
            throw new InvalidArgumentException('The SpanBean list contains disconnected or cyclic spans');
        }

        $trace->markPersisted();

        return $trace;
    }

    private static function assertIdentifier(string $id, int $length, string $name): void
    {
        if (!preg_match('/^[0-9a-f]{' . $length . '}$/', $id) || trim($id, '0') === '') {
            throw new \InvalidArgumentException(
                sprintf('The %s must be a non-zero, %d-character lowercase hexadecimal string', $name, $length),
            );
        }
    }
}
