<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * The downloadable exception list produced after an employee import: the rows
 * that were rejected, with the reason for each.
 */
class EmployeeImportExceptions implements FromArray, WithHeadings
{
    /**
     * @param  list<array{row: int, name: string, reason: string}>  $exceptions
     */
    public function __construct(private array $exceptions) {}

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return ['Baris', 'Nama', 'Alasan'];
    }

    /**
     * @return list<array{int, string, string}>
     */
    public function array(): array
    {
        return array_map(
            fn (array $row): array => [$row['row'], $row['name'], $row['reason']],
            $this->exceptions,
        );
    }
}
