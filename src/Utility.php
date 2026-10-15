<?php

declare(strict_types=1);

namespace EasySwoole\Trace;

use EasySwoole\Trace\Bean\SpanBean;
use InvalidArgumentException;

final class Utility
{
    public static function toTree(TracePoint $root): string
    {
        $lines = [sprintf('Trace: %s', $root->traceId())];
        self::appendTreeNode($lines, $root, '', true, true);

        return implode(PHP_EOL, $lines);
    }

    /**
     * 使用数据库读取的 SpanBean 重建调用树。
     *
     * @param list<SpanBean> $beans
     */
    public static function restoreFromBeans(
        array $beans,
        ?Clock $clock = null,
        ?WallClock $wallClock = null,
        ?IdGenerator $idGenerator = null,
    ): Trace {
        if ($beans === []) {
            throw new InvalidArgumentException('cannot restore a trace from an empty SpanBean list');
        }

        $clock ??= new HrtimeClock();
        $wallClock ??= new SystemWallClock();
        $idGenerator ??= new RandomIdGenerator();
        $traceId = null;
        $beansBySpanId = [];

        foreach ($beans as $bean) {
            if (!$bean instanceof SpanBean) {
                throw new InvalidArgumentException('every restored item must be a SpanBean');
            }

            $traceId ??= $bean->traceId;
            if ($bean->traceId !== $traceId) {
                throw new InvalidArgumentException('all SpanBeans must have the same trace ID');
            }

            if (isset($beansBySpanId[$bean->spanId])) {
                throw new InvalidArgumentException("duplicate span ID '{$bean->spanId}'");
            }

            $beansBySpanId[$bean->spanId] = $bean;
        }

        $roots = array_values(array_filter(
            $beans,
            static fn (SpanBean $bean): bool => $bean->parentSpanId === null
                || !isset($beansBySpanId[$bean->parentSpanId]),
        ));

        if (count($roots) !== 1) {
            throw new InvalidArgumentException('the SpanBean list must contain exactly one root span');
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
                static fn (SpanBean $left, SpanBean $right): int => [
                    $left->startedAtUnixUs,
                    $left->spanId,
                ] <=> [
                    $right->startedAtUnixUs,
                    $right->spanId,
                ],
            );
        }
        unset($children);

        $trace = new Trace(
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
                    throw new InvalidArgumentException('the SpanBean list contains a cycle');
                }

                $visited[$childBean->spanId] = true;
                $child = TracePoint::restoreFromSpanBean(
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
            throw new InvalidArgumentException('the SpanBean list contains disconnected or cyclic spans');
        }

        $trace->markPersisted();

        return $trace;
    }

    /** @param list<string> $lines */
    private static function appendTreeNode(
        array &$lines,
        TracePoint $point,
        string $prefix,
        bool $isLast,
        bool $isRoot = false,
    ): void {
        $span = $point->toSpanBean();
        $connector = $isRoot ? '' : ($isLast ? '└── ' : '├── ');
        $line = sprintf(
            '%s%s%s [%s | %s | %.3f ms] (span: %s)',
            $prefix,
            $connector,
            $span->name,
            $span->type,
            $span->status->value,
            $span->durationUs / 1_000,
            $span->spanId,
        );

        if ($span->error !== null) {
            $line .= " error: {$span->error}";
        }
        $lines[] = $line;

        $children = $point->children();
        $childPrefix = $isRoot
            ? ''
            : $prefix . ($isLast ? '    ' : '│   ');
        $lastIndex = count($children) - 1;

        foreach ($children as $index => $child) {
            self::appendTreeNode(
                $lines,
                $child,
                $childPrefix,
                $index === $lastIndex,
            );
        }
    }
}
