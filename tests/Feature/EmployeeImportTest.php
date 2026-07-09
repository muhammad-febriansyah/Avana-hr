<?php

use App\Enums\Role as RoleEnum;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Grade;
use App\Models\OrgUnit;
use App\Models\Position;
use App\Models\Tenant;
use Database\Seeders\PermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create(['employee_id_prefix' => 'ACME']);
    $this->admin = makeTenantUser($this->tenant, RoleEnum::CompanyAdmin->value);
});

const IMPORT_HEADING = 'Nama Lengkap,Email,Telepon,NIK KTP,NPWP,Tanggal Lahir,Jenis Kelamin,Status Nikah,PTKP,Status Kerja,Tanggal Masuk,Unit,Posisi,Grade,Cabang';

/**
 * Build a fake CSV upload from data rows (each an ordered list of 15 columns).
 *
 * @param  list<string>  $rows
 */
function csvUpload(array $rows): UploadedFile
{
    $csv = IMPORT_HEADING."\n".implode("\n", $rows)."\n";
    $path = tempnam(sys_get_temp_dir(), 'imp').'.csv';
    file_put_contents($path, $csv);

    return new UploadedFile($path, 'karyawan.csv', 'text/csv', null, true);
}

it('imports valid rows and creates employees with generated codes', function () {
    Storage::fake('local');

    $file = csvUpload([
        'Budi Santoso,budi@contoh.com,0812,,,1990-05-17,Laki-laki,Menikah,K1,PKWT,2026-01-06,,,,',
        'Siti Aminah,siti@contoh.com,0813,,,1992-02-02,Perempuan,Belum Menikah,TK0,PKWTT,2026-01-06,,,,',
    ]);

    $this->actingAs($this->admin)
        ->post('/employees/import', ['file' => $file])
        ->assertRedirect('/employees')
        ->assertSessionHas('importResult');

    expect(Employee::count())->toBe(2);
    expect(Employee::where('full_name', 'Budi Santoso')->value('gender'))->toBe('male');
    expect(Employee::where('full_name', 'Siti Aminah')->value('employment_status'))->toBe('pkwtt');
    expect(session('importResult')['imported'])->toBe(2);
    expect(session('importResult')['failed'])->toBe(0);
});

it('resolves unit, position, grade and branch by name/code', function () {
    Storage::fake('local');

    $unit = OrgUnit::create(['tenant_id' => $this->tenant->id, 'name' => 'Finance', 'type' => 'department']);
    $position = Position::create(['tenant_id' => $this->tenant->id, 'org_unit_id' => $unit->id, 'name' => 'Staff Finance']);
    $grade = Grade::create(['tenant_id' => $this->tenant->id, 'code' => 'G3', 'name' => 'Grade 3']);
    $branch = Branch::factory()->create(['tenant_id' => $this->tenant->id, 'code' => 'BR-01']);

    $file = csvUpload([
        'Budi Santoso,budi@contoh.com,0812,,,1990-05-17,Laki-laki,Menikah,K1,PKWT,2026-01-06,Finance,Staff Finance,G3,BR-01',
    ]);

    $this->actingAs($this->admin)
        ->post('/employees/import', ['file' => $file])
        ->assertRedirect('/employees');

    $employee = Employee::where('full_name', 'Budi Santoso')->firstOrFail();
    expect($employee->position_id)->toBe($position->id);
    expect($employee->grade_id)->toBe($grade->id);
    $this->assertDatabaseHas('employee_branch_assignments', [
        'employee_id' => $employee->id,
        'branch_id' => $branch->id,
        'is_primary' => true,
    ]);
});

it('sends invalid and duplicate rows to the exception list without importing them', function () {
    Storage::fake('local');

    Employee::factory()->create(['tenant_id' => $this->tenant->id, 'email' => 'ada@contoh.com']);

    $file = csvUpload([
        ',,,,,,,,,,,,,,',                                                    // missing name
        'Andi,andi@contoh.com,,,,,Alien,,,,,,,,',                            // unknown gender
        'Dupli Kat,ada@contoh.com,,,,,Laki-laki,,,,,,,,',                    // duplicate email
        'Cahaya Baru,cahaya@contoh.com,,,,,Perempuan,,,,,Tidak Ada,,,',      // unknown unit
        'Valid Orang,valid@contoh.com,,,,,Laki-laki,,,,,,,,',               // ok
    ]);

    $this->actingAs($this->admin)
        ->post('/employees/import', ['file' => $file])
        ->assertRedirect('/employees');

    $result = session('importResult');
    expect($result['imported'])->toBe(1);
    expect($result['failed'])->toBe(4);
    expect(Employee::where('full_name', 'Valid Orang')->exists())->toBeTrue();
    expect(Employee::where('full_name', 'Andi')->exists())->toBeFalse();

    // Exception file was stored (scoped to the user) and is downloadable via its token.
    expect($result['token'])->not->toBeNull();
    Storage::disk('local')->assertExists("imports/{$this->admin->id}/{$result['token']}.xlsx");
});

it('downloads the exception list via its token', function () {
    Storage::fake('local');

    $file = csvUpload(['Andi Gagal,bukan-email,,,,,,,,,,,,,']);
    $this->actingAs($this->admin)->post('/employees/import', ['file' => $file]);
    $token = session('importResult')['token'];

    $this->actingAs($this->admin)
        ->get("/employees/import/exceptions/{$token}")
        ->assertOk()
        ->assertDownload('exception_import_karyawan.xlsx');
});

it('rejects an exception token that belongs to another user', function () {
    Storage::fake('local');

    $file = csvUpload(['Andi Gagal,bukan-email,,,,,,,,,,,,,']);
    $this->actingAs($this->admin)->post('/employees/import', ['file' => $file]);
    $token = session('importResult')['token'];

    $intruder = makeTenantUser($this->tenant, RoleEnum::CompanyAdmin->value);
    $this->actingAs($intruder)
        ->get("/employees/import/exceptions/{$token}")
        ->assertNotFound();
});

it('downloads the import template', function () {
    $this->actingAs($this->admin)
        ->get('/employees/import/template')
        ->assertOk()
        ->assertDownload('template_import_karyawan.xlsx');
});

it('rejects a non-spreadsheet upload', function () {
    $bad = UploadedFile::fake()->create('data.pdf', 10, 'application/pdf');

    $this->actingAs($this->admin)
        ->post('/employees/import', ['file' => $bad])
        ->assertSessionHasErrors('file');
});

it('forbids an employee-role user from importing', function () {
    $user = makeTenantUser($this->tenant, RoleEnum::Employee->value);

    $this->actingAs($user)->get('/employees/import/template')->assertForbidden();
    $this->actingAs($user)->post('/employees/import', [
        'file' => csvUpload(['Budi,budi@contoh.com,,,,,,,,,,,,,']),
    ])->assertForbidden();
});
