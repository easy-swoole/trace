<?php

declare(strict_types=1);

namespace EasySwoole\Trace\Bean;

use EasySwoole\Trace\TraceStatus;
use EasySwoole\Trace\EndTrigger;
use InvalidArgumentException;
use JsonSerializable;
use JsonException;

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
        public readonly ?EndTrigger $endedBy = null,
    ) {
        self::assertIdentifier($traceId, 32, 'trace_id');
        self::assertIdentifier($spanId, 16, 'span_id');
        if ($parentSpanId !== null) {
            self::assertIdentifier($parentSpanId, 16, 'parent_span_id');
        }

        if (!in_array($type, ['serial', 'parallel'], true)) {
            throw new InvalidArgumentException("unknown span type '{$type}'");
        }

        if ($startedAtUnixUs < 0 || $durationUs < 0) {
            throw new InvalidArgumentException('span timestamps and duration cannot be negative');
        }

        if ($endedAtUnixUs !== null && $endedAtUnixUs < $startedAtUnixUs) {
            throw new InvalidArgumentException('span end time cannot be earlier than its start time');
        }
    }

    /**
     * 使用 snake_case 数据库字段创建 Bean。
     * params 字段可以是数组或 JSON 字符串。
     *
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        $required = [
            'trace_id',
            'span_id',
            'parent_span_id',
            'name',
            'type',
            'status',
            'started_at_unix_us',
            'ended_at_unix_us',
            'duration_us',
        ];

        foreach ($required as $field) {
            if (!array_key_exists($field, $row)) {
                throw new InvalidArgumentException("missing SpanBean field '{$field}'");
            }
        }

        $status = is_string($row['status'])
            ? TraceStatus::tryFrom($row['status'])
            : null;
        if ($status === null) {
            throw new InvalidArgumentException('invalid SpanBean status');
        }

        $params = $row['params'] ?? [];
        if (is_string($params)) {
            try {
                $params = json_decode($params, true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException $error) {
                throw new InvalidArgumentException('invalid SpanBean params JSON', previous: $error);
            }
        }
        if (!is_array($params)) {
            throw new InvalidArgumentException('spanBean params must be an array or JSON object');
        }

        $endedBy = null;
        if (isset($row['ended_by'])) {
            $endedBy = is_string($row['ended_by'])
                ? EndTrigger::tryFrom($row['ended_by'])
                : null;
            if ($endedBy === null) {
                throw new InvalidArgumentException('invalid end trigger');
            }
        }

        return new self(
            traceId: self::stringValue($row, 'trace_id'),
            spanId: self::stringValue($row, 'span_id'),
            parentSpanId: self::nullableStringValue($row, 'parent_span_id'),
            name: self::stringValue($row, 'name'),
            type: self::stringValue($row, 'type'),
            status: $status,
            startedAtUnixUs: self::intValue($row, 'started_at_unix_us'),
            endedAtUnixUs: self::nullableIntValue($row, 'ended_at_unix_us'),
            durationUs: self::intValue($row, 'duration_us'),
            params: $params,
            error: array_key_exists('error', $row)
                ? self::nullableStringValue($row, 'error')
                : null,
            endedBy: $endedBy,
        );
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
            'ended_by' => $this->endedBy?->value,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private static function assertIdentifier(string $id, int $length, string $field): void
    {
        if (!preg_match('/^[0-9a-f]{' . $length . '}$/', $id) || trim($id, '0') === '') {
            throw new InvalidArgumentException(
                sprintf('%s must be a non-zero, %d-character lowercase hexadecimal string', $field, $length),
            );
        }
    }

    /** @param array<string, mixed> $row */
    private static function stringValue(array $row, string $field): string
    {
        if (!is_string($row[$field])) {
            throw new InvalidArgumentException("spanBean field '{$field}' must be a string");
        }

        return $row[$field];
    }

    /** @param array<string, mixed> $row */
    private static function nullableStringValue(array $row, string $field): ?string
    {
        if ($row[$field] === null) {
            return null;
        }

        return self::stringValue($row, $field);
    }

    /** @param array<string, mixed> $row */
    private static function intValue(array $row, string $field): int
    {
        $value = $row[$field];
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        throw new InvalidArgumentException("spanBean field '{$field}' must be an integer");
    }

    /** @param array<string, mixed> $row */
    private static function nullableIntValue(array $row, string $field): ?int
    {
        if ($row[$field] === null) {
            return null;
        }

        return self::intValue($row, $field);
    }
}
