<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Tolerant readers for decoded response bodies, and the writers that go with
 * them.
 *
 * Every DTO reads through these so that one rule holds everywhere: a field that
 * is missing, null or of the wrong shape becomes the type's empty value rather
 * than a TypeError inside a mapper. A newer platform adds fields and an older
 * one omits them; neither should turn a successful call into a crash.
 *
 * @internal
 */
final class Read
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function string(array $data, string $key, string $default = ''): string
    {
        $value = $data[$key] ?? null;

        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function stringOrNull(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function int(array $data, string $key, int $default = 0): int
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function intOrNull(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function float(array $data, string $key, float $default = 0.0): float
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (float) $value : $default;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function floatOrNull(array $data, string $key): ?float
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function bool(array $data, string $key, bool $default = false): bool
    {
        $value = $data[$key] ?? null;

        return is_bool($value) ? $value : $default;
    }

    /**
     * A timestamp, or null when absent or unparseable — a malformed moment is not
     * worth failing a read over.
     *
     * @param  array<string, mixed>  $data
     */
    public static function date(array $data, string $key): ?DateTimeImmutable
    {
        $value = $data[$key] ?? null;

        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     */
    public static function strings(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $item) {
            if (is_scalar($item)) {
                $out[] = (string) $item;
            }
        }

        return $out;
    }

    /**
     * A JSON object with arbitrary values.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function map(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        return is_array($value) ? self::assoc($value) : [];
    }

    /**
     * A JSON object whose values are strings.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    public static function stringMap(array $data, string $key): array
    {
        $out = [];
        foreach (self::map($data, $key) as $k => $v) {
            if (is_scalar($v)) {
                $out[$k] = (string) $v;
            }
        }

        return $out;
    }

    /**
     * A JSON object whose values are numbers.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, float>
     */
    public static function floatMap(array $data, string $key): array
    {
        $out = [];
        foreach (self::map($data, $key) as $k => $v) {
            if (is_numeric($v)) {
                $out[$k] = (float) $v;
            }
        }

        return $out;
    }

    /**
     * A nested object, or null when there is none.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    public static function object(array $data, string $key): ?array
    {
        $value = $data[$key] ?? null;

        return is_array($value) ? self::assoc($value) : null;
    }

    /**
     * An array of objects; anything in it that is not an object is dropped.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, mixed>>
     */
    public static function objects(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                $out[] = self::assoc($item);
            }
        }

        return $out;
    }

    /**
     * A moment as the contract writes it: RFC 3339 with an offset.
     */
    public static function timestamp(?DateTimeInterface $moment): ?string
    {
        return $moment?->format(DateTimeInterface::RFC3339);
    }

    /**
     * A calendar day (`YYYY-MM-DD`). A string is passed through as it is, so a
     * caller holding a date string does not have to parse it first.
     */
    public static function day(DateTimeInterface|string|null $day): ?string
    {
        return $day instanceof DateTimeInterface ? $day->format('Y-m-d') : $day;
    }

    /**
     * Drop the nulls from a request body or query — an argument left out sends
     * nothing rather than an explicit null the platform would read as "clear".
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public static function compact(array $values): array
    {
        return array_filter($values, static fn ($v): bool => $v !== null);
    }

    /**
     * @param  array<mixed>  $value
     * @return array<string, mixed>
     */
    public static function assoc(array $value): array
    {
        $out = [];
        foreach ($value as $k => $v) {
            $out[(string) $k] = $v;
        }

        return $out;
    }
}
