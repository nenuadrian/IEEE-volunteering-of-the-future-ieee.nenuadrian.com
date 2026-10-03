<?php

namespace App\Support;

use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Driver-aware SQL for grouping timestamps into day / week / month buckets
 * (MySQL / MariaDB in production, SQLite in the test suite), plus helpers to
 * build a gap-free series of bucket keys.
 */
class DateBucket
{
    public static function expression(string $column, string $unit = 'month'): string
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return match ($unit) {
                'day' => "strftime('%Y-%m-%d', {$column})",
                'week' => "strftime('%Y-%m-%d', {$column}, 'weekday 1', '-7 days')",
                default => "strftime('%Y-%m', {$column})",
            };
        }

        if ($driver === 'pgsql') {
            return match ($unit) {
                'day' => "to_char({$column}, 'YYYY-MM-DD')",
                'week' => "to_char(date_trunc('week', {$column}), 'YYYY-MM-DD')",
                default => "to_char({$column}, 'YYYY-MM')",
            };
        }

        return match ($unit) {
            'day' => "DATE_FORMAT({$column}, '%Y-%m-%d')",
            // Monday of the ISO week, so keys sort and label naturally.
            'week' => "DATE_FORMAT(DATE_SUB({$column}, INTERVAL WEEKDAY({$column}) DAY), '%Y-%m-%d')",
            default => "DATE_FORMAT({$column}, '%Y-%m')",
        };
    }

    /** Bucket key for a PHP date, matching expression(). */
    public static function key(Carbon $date, string $unit = 'month'): string
    {
        return match ($unit) {
            'day' => $date->format('Y-m-d'),
            'week' => $date->copy()->startOfWeek(Carbon::MONDAY)->format('Y-m-d'),
            default => $date->format('Y-m'),
        };
    }

    /**
     * Ordered list of bucket keys => display labels between two dates.
     *
     * @return array<string,string>
     */
    public static function range(Carbon $from, Carbon $to, string $unit = 'month'): array
    {
        $start = match ($unit) {
            'day' => $from->copy()->startOfDay(),
            'week' => $from->copy()->startOfWeek(Carbon::MONDAY),
            default => $from->copy()->startOfMonth(),
        };

        $interval = match ($unit) {
            'day' => '1 day',
            'week' => '1 week',
            default => '1 month',
        };

        $keys = [];
        foreach (CarbonPeriod::create($start, $interval, $to) as $date) {
            $keys[static::key($date, $unit)] = match ($unit) {
                'day' => $date->format('j M'),
                'week' => $date->format('j M'),
                default => $date->format('M Y'),
            };
        }

        return $keys;
    }

    /**
     * Fill a sparse [bucket => value] result into the full range.
     *
     * @param  array<string,string>  $range
     * @param  array<string,int|float>  $values
     * @return array<int,int|float>
     */
    public static function fill(array $range, array $values): array
    {
        return array_map(fn ($key) => $values[$key] ?? 0, array_keys($range));
    }
}
