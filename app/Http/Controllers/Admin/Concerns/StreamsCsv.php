<?php

namespace App\Http\Controllers\Admin\Concerns;

use Closure;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** CSV downloads for admin exports. */
trait StreamsCsv
{
    /**
     * Stream a CSV file. $write receives a function that writes one row.
     *
     * @param  Closure(Closure(array<int, mixed>): void): void  $write
     */
    protected function streamCsv(string $filename, Closure $write): StreamedResponse
    {
        return response()->streamDownload(function () use ($write) {
            $out = fopen('php://output', 'w');
            $write(fn (array $row) => fputcsv($out, array_map($this->csvCell(...), $row), ',', '"', ''));
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Neutralise spreadsheet formulas in user-supplied text (CSV injection). */
    private function csvCell(mixed $value): mixed
    {
        return is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)
            ? "'".$value
            : $value;
    }
}
