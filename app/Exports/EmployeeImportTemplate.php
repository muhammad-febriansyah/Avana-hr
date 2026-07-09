<?php

namespace App\Exports;

use App\Imports\EmployeesImport;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * The blank import template (heading row + one example row) that HR downloads,
 * fills in, and re-uploads. Heading labels map to the keys read by
 * {@see EmployeesImport}.
 */
class EmployeeImportTemplate implements FromArray, WithHeadings
{
    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'Nama Lengkap',
            'Email',
            'Telepon',
            'NIK KTP',
            'NPWP',
            'Tanggal Lahir',
            'Jenis Kelamin',
            'Status Nikah',
            'PTKP',
            'Status Kerja',
            'Tanggal Masuk',
            'Unit',
            'Posisi',
            'Grade',
            'Cabang',
        ];
    }

    /**
     * @return list<list<string>>
     */
    public function array(): array
    {
        return [
            [
                'Budi Santoso',
                'budi@contoh.com',
                '081234567890',
                '3201234567890001',
                '091234567890000',
                '1990-05-17',
                'Laki-laki',
                'Menikah',
                'K1',
                'PKWT',
                '2026-01-06',
                'Finance',
                'Staff Finance',
                'G3',
                'BR-01',
            ],
        ];
    }
}
