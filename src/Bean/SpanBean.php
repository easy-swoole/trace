<?php

declare(strict_types=1);

namespace EasySwoole\Trace\Bean;

use EasySwoole\Trace\TraceStatus;
use JsonSerializable;

final class SpanBean implements JsonSerializable
{
    /** @param array<string, mixed> $params */
    public function __construct(
        public readonly string $traceId,
        public readonly string $spanId,
        public readonly ?string $parentSpanId,
        public readonly string $name,
        public readonly string $type,
        public readonly TraceStatus $status,
        public readonly int $startedAtUnixUs,
        public readonly ?int $endedAtUnixUs,
        public readonly int $durationUs,
        public readonly array $params,
        public readonly ?string $error,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'trace_id' => $this->traceId,
            'span_id' => $this->spanId,
            'parent_span_id' => $this->parentSpanId,
            'name' => $this->name,
            'type' => $this->type,
            'status' => $this->status->value,
            'started_at_unix_us' => $this->startedAtUnixUs,
            'ended_at_unix_us' => $this->endedAtUnixUs,
            'duration_us' => $this->durationUs,
            'params' => $this->params,
            'error' => $this->error,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
