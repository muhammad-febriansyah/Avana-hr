<?php

namespace App\Imports;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\Grade;
use App\Models\OrgUnit;
use App\Models\Position;
use App\Models\Tenant;
use App\Support\EmployeeCode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Imports employees from an uploaded spreadsheet, one row at a time. Invalid or
 * duplicate rows are collected in {@see $exceptions} instead of aborting the run.
 */
class EmployeesImport implements ToCollection, WithHeadingRow
{
    public int $importedCount = 0;

    /** @var list<array{row: int, name: string, reason: string}> */
    public array $exceptions = [];

    /** @var array<string, int> lower(name) => id */
    private array $orgUnits;

    /** @var array<string, int> lower(name) => id */
    private array $positions;

    /** @var array<string, int> lower(code) => id */
    private array $grades;

    /** @var array<string, int> lower(code) => id */
    private array $branches;

    /** @var array<string, true> */
    private array $seenNik = [];

    /** @var array<string, true> */
    private array $seenNpwp = [];

    /** @var array<string, true> */
    private array $seenEmail = [];

    /** @var array<string, true> */
    private array $existingNik;

    /** @var array<string, true> */
    private array $existingNpwp;

    /** @var array<string, true> */
    private array $existingEmail;

    /** @var array<string, string> */
    private const GENDER_MAP = [
        'male' => 'male', 'laki-laki' => 'male', 'laki' => 'male', 'l' => 'male', 'pria' => 'male',
        'female' => 'female', 'perempuan' => 'female', 'wanita' => 'female', 'p' => 'female',
    ];

    /** @var array<string, string> */
    private const MARITAL_MAP = [
        'single' => 'single', 'belum menikah' => 'single', 'lajang' => 'single',
        'married' => 'married', 'menikah' => 'married', 'kawin' => 'married',
        'divorced' => 'divorced', 'cerai' => 'divorced', 'cerai hidup' => 'divorced',
        'widowed' => 'widowed', 'janda' => 'widowed', 'duda' => 'widowed', 'cerai mati' => 'widowed',
    ];

    /** @var array<string, string> */
    private const EMPLOYMENT_MAP = [
        'pkwt' => 'pkwt', 'kontrak' => 'pkwt',
        'pkwtt' => 'pkwtt', 'tetap' => 'pkwtt', 'permanen' => 'pkwtt',
        'magang' => 'magang', 'intern' => 'magang',
        'kemitraan' => 'kemitraan', 'mitra' => 'kemitraan', 'partner' => 'kemitraan',
    ];

    /** @var list<string> */
    private const PTKP = ['TK0', 'TK1', 'TK2', 'TK3', 'K0', 'K1', 'K2', 'K3'];

    public function __construct(private Tenant $tenant)
    {
        $this->orgUnits = $this->lookupMap(OrgUnit::pluck('id', 'name'));
        $this->positions = $this->lookupMap(Position::pluck('id', 'name'));
        $this->grades = $this->lookupMap(Grade::pluck('id', 'code'));
        $this->branches = $this->lookupMap(Branch::pluck('id', 'code'));

        $this->existingNik = array_fill_keys(Employee::whereNotNull('nik_ktp_hash')->pluck('nik_ktp_hash')->all(), true);
        $this->existingNpwp = array_fill_keys(Employee::whereNotNull('npwp_hash')->pluck('npwp_hash')->all(), true);
        $this->existingEmail = array_fill_keys(
            Employee::whereNotNull('email')->pluck('email')->map(fn (string $e): string => mb_strtolower($e))->all(),
            true,
        );
    }

    /**
     * @param  Collection<int, Collection<string, mixed>>  $rows
     */
    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            // Heading occupies sheet row 1, so the first data row is spreadsheet row 2.
            $this->processRow($row->toArray(), (int) $index + 2);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function processRow(array $data, int $excelRow): void
    {
        $name = trim((string) ($data['nama_lengkap'] ?? ''));

        if ($name === '') {
            $this->fail($excelRow, '', 'Nama lengkap wajib diisi.');

            return;
        }

        $errors = [];
        $attributes = ['full_name' => $name];

        $this->resolveEmail($data, $attributes, $errors);
        $this->resolveBlindField($data, 'nik_ktp', 'nik_ktp', $this->existingNik, $this->seenNik, 'NIK KTP', $attributes, $errors);
        $this->resolveBlindField($data, 'npwp', 'npwp', $this->existingNpwp, $this->seenNpwp, 'NPWP', $attributes, $errors);
        $this->resolveEnum($data, 'jenis_kelamin', self::GENDER_MAP, 'gender', 'Jenis kelamin', $attributes, $errors);
        $this->resolveEnum($data, 'status_nikah', self::MARITAL_MAP, 'marital_status', 'Status nikah', $attributes, $errors);
        $this->resolveEnum($data, 'status_kerja', self::EMPLOYMENT_MAP, 'employment_status', 'Status kerja', $attributes, $errors);
        $this->resolvePtkp($data, $attributes, $errors);
        $this->resolveDate($data, 'tanggal_lahir', 'birth_date', 'Tanggal lahir', $attributes, $errors);
        $this->resolveDate($data, 'tanggal_masuk', 'join_date', 'Tanggal masuk', $attributes, $errors);
        $this->resolveLookup($data, 'unit', $this->orgUnits, 'org_unit_id', 'Unit', $attributes, $errors);
        $this->resolveLookup($data, 'posisi', $this->positions, 'position_id', 'Posisi', $attributes, $errors);
        $this->resolveLookup($data, 'grade', $this->grades, 'grade_id', 'Grade', $attributes, $errors);

        $phone = trim((string) ($data['telepon'] ?? ''));
        if ($phone !== '') {
            $attributes['phone'] = $phone;
        }

        $branchId = null;
        $this->resolveLookupValue($data, 'cabang', $this->branches, 'Cabang', $branchId, $errors);

        if ($errors !== []) {
            $this->fail($excelRow, $name, implode(' ', $errors));

            return;
        }

        $this->markSeen($attributes);

        $employee = Employee::create([
            ...$attributes,
            'employee_code' => EmployeeCode::generate($this->tenant),
            'status' => 'active',
        ]);

        if ($branchId !== null) {
            $employee->branchAssignments()->create(['branch_id' => $branchId, 'is_primary' => true]);
        }

        $this->importedCount++;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $errors
     */
    private function resolveEmail(array $data, array &$attributes, array &$errors): void
    {
        $email = trim((string) ($data['email'] ?? ''));

        if ($email === '') {
            return;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email tidak valid.';

            return;
        }

        $key = mb_strtolower($email);
        if (isset($this->existingEmail[$key]) || isset($this->seenEmail[$key])) {
            $errors[] = 'Email sudah terdaftar.';

            return;
        }

        $attributes['email'] = $email;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, true>  $existing
     * @param  array<string, true>  $seen
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $errors
     */
    private function resolveBlindField(array $data, string $column, string $attribute, array $existing, array $seen, string $label, array &$attributes, array &$errors): void
    {
        $value = trim((string) ($data[$column] ?? ''));

        if ($value === '') {
            return;
        }

        $hash = Employee::blindHash($value);

        if ($hash !== null && (isset($existing[$hash]) || isset($seen[$hash]))) {
            $errors[] = "{$label} sudah terdaftar.";

            return;
        }

        $attributes[$attribute] = $value;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $map
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $errors
     */
    private function resolveEnum(array $data, string $column, array $map, string $attribute, string $label, array &$attributes, array &$errors): void
    {
        $value = mb_strtolower(trim((string) ($data[$column] ?? '')));

        if ($value === '') {
            return;
        }

        if (! isset($map[$value])) {
            $errors[] = "{$label} tidak dikenali.";

            return;
        }

        $attributes[$attribute] = $map[$value];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $errors
     */
    private function resolvePtkp(array $data, array &$attributes, array &$errors): void
    {
        $value = strtoupper(trim((string) ($data['ptkp'] ?? '')));

        if ($value === '') {
            return;
        }

        if (! in_array($value, self::PTKP, true)) {
            $errors[] = 'PTKP tidak dikenali.';

            return;
        }

        $attributes['ptkp_status'] = $value;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $errors
     */
    private function resolveDate(array $data, string $column, string $attribute, string $label, array &$attributes, array &$errors): void
    {
        $raw = $data[$column] ?? null;

        if ($raw === null || trim((string) $raw) === '') {
            return;
        }

        try {
            $date = is_numeric($raw)
                ? Carbon::instance(ExcelDate::excelToDateTimeObject((float) $raw))
                : Carbon::parse((string) $raw);
        } catch (\Throwable) {
            $errors[] = "{$label} tidak valid (format YYYY-MM-DD).";

            return;
        }

        $attributes[$attribute] = $date->toDateString();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, int>  $map
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $errors
     */
    private function resolveLookup(array $data, string $column, array $map, string $attribute, string $label, array &$attributes, array &$errors): void
    {
        $id = null;
        $this->resolveLookupValue($data, $column, $map, $label, $id, $errors);

        if ($id !== null) {
            $attributes[$attribute] = $id;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, int>  $map
     * @param  list<string>  $errors
     */
    private function resolveLookupValue(array $data, string $column, array $map, string $label, ?int &$id, array &$errors): void
    {
        $value = mb_strtolower(trim((string) ($data[$column] ?? '')));

        if ($value === '') {
            return;
        }

        if (! isset($map[$value])) {
            $errors[] = "{$label} \"{$data[$column]}\" tidak ditemukan.";

            return;
        }

        $id = $map[$value];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function markSeen(array $attributes): void
    {
        if (isset($attributes['email'])) {
            $this->seenEmail[mb_strtolower($attributes['email'])] = true;
        }
        if (isset($attributes['nik_ktp']) && ($hash = Employee::blindHash($attributes['nik_ktp'])) !== null) {
            $this->seenNik[$hash] = true;
        }
        if (isset($attributes['npwp']) && ($hash = Employee::blindHash($attributes['npwp'])) !== null) {
            $this->seenNpwp[$hash] = true;
        }
    }

    private function fail(int $excelRow, string $name, string $reason): void
    {
        $this->exceptions[] = ['row' => $excelRow, 'name' => $name, 'reason' => $reason];
    }

    /**
     * @param  Collection<int|string, int>  $pairs  value => id keyed by name/code
     * @return array<string, int>
     */
    private function lookupMap(Collection $pairs): array
    {
        /** @var array<string, int> $map */
        $map = [];
        foreach ($pairs as $label => $id) {
            $map[mb_strtolower(trim((string) $label))] = (int) $id;
        }

        return $map;
    }
}
