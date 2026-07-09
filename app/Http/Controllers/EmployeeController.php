<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldValue;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\Grade;
use App\Models\OrgUnit;
use App\Models\Position;
use App\Support\EmployeeCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('q'));
        $status = (string) $request->string('status');
        $orgUnitId = $request->integer('org_unit_id');

        $employees = Employee::query()
            ->with(['position:id,name', 'orgUnit:id,name'])
            ->when($search !== '', fn ($query) => $query->where(function ($q) use ($search): void {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->when(in_array($status, ['active', 'inactive'], true), fn ($query) => $query->where('status', $status))
            ->when($orgUnitId !== 0, fn ($query) => $query->where('org_unit_id', $orgUnitId))
            ->orderBy('full_name')
            ->get()
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'full_name' => $employee->full_name,
                'email' => $employee->email,
                'position' => $employee->position?->name,
                'org_unit' => $employee->orgUnit?->name,
                'status' => $employee->status,
            ]);

        return Inertia::render('employees/index', [
            'employees' => $employees,
            'orgUnits' => OrgUnit::orderBy('name')->get(['id', 'name']),
            'filters' => [
                'q' => $search,
                'status' => $status,
                'org_unit_id' => $orgUnitId !== 0 ? $orgUnitId : null,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('employees/form', [
            'employee' => null,
            ...$this->formOptions(null),
            'customFields' => $this->customFieldDefinitions(),
            'customValues' => (object) [],
        ]);
    }

    public function edit(Employee $employee): Response
    {
        $primaryBranch = $employee->branchAssignments()->where('is_primary', true)->value('branch_id');

        return Inertia::render('employees/form', [
            'employee' => [
                ...$this->employeeAttributes($employee),
                'branch_id' => $primaryBranch,
            ],
            ...$this->formOptions($employee),
            'customFields' => $this->customFieldDefinitions(),
            'customValues' => $this->customFieldValues($employee->id),
        ]);
    }

    public function show(Employee $employee): Response
    {
        $employee->load(['position:id,name', 'grade:id,code,name', 'orgUnit:id,name', 'directManager:id,full_name']);
        $primaryBranch = $employee->branchAssignments()->where('is_primary', true)->with('branch:id,name')->first();

        $audits = AuditLog::query()
            ->where('auditable_type', Employee::class)
            ->where('auditable_id', $employee->id)
            ->with('user:id,name')
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (AuditLog $log): array => [
                'id' => $log->id,
                'event' => $log->event,
                'user' => $log->user_id === null ? 'Sistem' : $log->user?->name,
                'created_at' => $log->created_at->toIso8601String(),
            ]);

        $values = $this->customFieldValues($employee->id);
        $customFields = $this->customFieldDefinitions()
            ->map(fn (array $def): array => [
                'label' => $def['label'],
                'value' => $values[$def['id']] ?? null,
            ])
            ->all();

        return Inertia::render('employees/show', [
            'employee' => [
                ...$this->employeeAttributes($employee),
                'position' => $employee->position?->name,
                'grade' => $employee->grade ? $employee->grade->code.' — '.$employee->grade->name : null,
                'org_unit' => $employee->orgUnit?->name,
                'direct_manager' => $employee->directManager?->full_name,
                'branch' => $primaryBranch?->branch?->name,
            ],
            'customFields' => $customFields,
            'contracts' => $employee->contracts()->get()->map(fn (EmployeeContract $contract): array => [
                'id' => $contract->id,
                'contract_no' => $contract->contract_no,
                'type' => $contract->type,
                'start_date' => $contract->start_date->toDateString(),
                'end_date' => $contract->end_date?->toDateString(),
                'status' => $contract->status,
                'has_file' => $contract->file_path !== null,
            ])->all(),
            'audits' => $audits,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = $request->user()->tenant;
        $data = $this->validateData($request);
        $custom = $this->validateCustom($request);

        $branchId = $data['branch_id'] ?? null;
        unset($data['branch_id']);

        $employee = Employee::create([
            ...$data,
            'employee_code' => EmployeeCode::generate($tenant),
            'status' => 'active',
        ]);

        $this->syncPrimaryBranch($employee, $branchId);
        $this->saveCustomValues($employee->id, $custom);

        return to_route('employees.show', $employee->id)->with('success', 'Karyawan dibuat.');
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $data = $this->validateData($request, $employee->id);
        $custom = $this->validateCustom($request);

        $branchId = $data['branch_id'] ?? null;
        unset($data['branch_id']);

        $employee->update($data);
        $this->syncPrimaryBranch($employee, $branchId);
        $this->saveCustomValues($employee->id, $custom);

        return to_route('employees.show', $employee->id)->with('success', 'Karyawan diperbarui.');
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        $employee->delete();

        return to_route('employees.index')->with('success', 'Karyawan dihapus.');
    }

    private function syncPrimaryBranch(Employee $employee, ?int $branchId): void
    {
        if ($branchId === null) {
            return;
        }

        $employee->branchAssignments()->where('branch_id', '!=', $branchId)->update(['is_primary' => false]);
        $employee->branchAssignments()->updateOrCreate(
            ['branch_id' => $branchId],
            ['is_primary' => true],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function employeeAttributes(Employee $employee): array
    {
        return [
            'id' => $employee->id,
            'employee_code' => $employee->employee_code,
            'full_name' => $employee->full_name,
            'nik_ktp' => $employee->nik_ktp,
            'npwp' => $employee->npwp,
            'email' => $employee->email,
            'phone' => $employee->phone,
            'birth_date' => $employee->birth_date?->toDateString(),
            'gender' => $employee->gender,
            'marital_status' => $employee->marital_status,
            'ptkp_status' => $employee->ptkp_status,
            'position_id' => $employee->position_id,
            'grade_id' => $employee->grade_id,
            'org_unit_id' => $employee->org_unit_id,
            'direct_manager_employee_id' => $employee->direct_manager_employee_id,
            'employment_status' => $employee->employment_status,
            'join_date' => $employee->join_date?->toDateString(),
            'status' => $employee->status,
            'bank_name' => $employee->bank_name,
            'bank_account' => $employee->bank_account,
            'bank_account_name' => $employee->bank_account_name,
            'bpjs_kes_no' => $employee->bpjs_kes_no,
            'bpjs_tk_no' => $employee->bpjs_tk_no,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(?Employee $employee): array
    {
        return [
            'positions' => Position::with('orgUnit:id,name')->orderBy('name')->get(['id', 'name', 'org_unit_id']),
            'grades' => Grade::orderBy('code')->get(['id', 'code', 'name']),
            'orgUnits' => OrgUnit::orderBy('name')->get(['id', 'name']),
            'branches' => Branch::orderBy('name')->get(['id', 'name']),
            'managers' => Employee::query()
                ->when($employee !== null, fn ($q) => $q->whereKeyNot($employee->id))
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'employee_code']),
            'options' => [
                'gender' => $this->genderOptions,
                'marital_status' => $this->maritalOptions,
                'ptkp_status' => array_map(fn (string $v): array => ['value' => $v, 'label' => $v], $this->ptkpValues),
                'employment_status' => $this->employmentOptions,
            ],
        ];
    }

    /** @var list<array{value: string, label: string}> */
    private array $genderOptions = [
        ['value' => 'male', 'label' => 'Laki-laki'],
        ['value' => 'female', 'label' => 'Perempuan'],
    ];

    /** @var list<array{value: string, label: string}> */
    private array $maritalOptions = [
        ['value' => 'single', 'label' => 'Belum Menikah'],
        ['value' => 'married', 'label' => 'Menikah'],
        ['value' => 'divorced', 'label' => 'Cerai'],
        ['value' => 'widowed', 'label' => 'Janda/Duda'],
    ];

    /** @var list<array{value: string, label: string}> */
    private array $employmentOptions = [
        ['value' => 'pkwt', 'label' => 'PKWT (Kontrak)'],
        ['value' => 'pkwtt', 'label' => 'PKWTT (Tetap)'],
        ['value' => 'magang', 'label' => 'Magang'],
        ['value' => 'kemitraan', 'label' => 'Kemitraan'],
    ];

    /** @var list<string> */
    private array $ptkpValues = ['TK0', 'TK1', 'TK2', 'TK3', 'K0', 'K1', 'K2', 'K3'];

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'nik_ktp' => [
                'nullable', 'string', 'max:32',
                $this->uniqueBlind('nik_ktp_hash', $tenantId, $ignoreId, 'NIK KTP sudah terdaftar.'),
            ],
            'npwp' => [
                'nullable', 'string', 'max:32',
                $this->uniqueBlind('npwp_hash', $tenantId, $ignoreId, 'NPWP sudah terdaftar.'),
            ],
            'email' => [
                'nullable', 'email', 'max:255',
                Rule::unique('employees', 'email')->where('tenant_id', $tenantId)->whereNull('deleted_at')->ignore($ignoreId),
            ],
            'phone' => ['nullable', 'string', 'max:32'],
            'birth_date' => ['nullable', 'date'],
            'gender' => ['nullable', Rule::in(array_column($this->genderOptions, 'value'))],
            'marital_status' => ['nullable', Rule::in(array_column($this->maritalOptions, 'value'))],
            'ptkp_status' => ['nullable', Rule::in($this->ptkpValues)],
            'position_id' => ['nullable', Rule::exists('positions', 'id')->whereNull('deleted_at')],
            'grade_id' => ['nullable', Rule::exists('grades', 'id')->whereNull('deleted_at')],
            'org_unit_id' => ['nullable', Rule::exists('org_units', 'id')->whereNull('deleted_at')],
            'direct_manager_employee_id' => [
                'nullable',
                Rule::exists('employees', 'id')->whereNull('deleted_at'),
                Rule::notIn($ignoreId !== null ? [$ignoreId] : []),
            ],
            'employment_status' => ['nullable', Rule::in(array_column($this->employmentOptions, 'value'))],
            'join_date' => ['nullable', 'date'],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->whereNull('deleted_at')],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account' => ['nullable', 'string', 'max:64'],
            'bank_account_name' => ['nullable', 'string', 'max:255'],
            'bpjs_kes_no' => ['nullable', 'string', 'max:64'],
            'bpjs_tk_no' => ['nullable', 'string', 'max:64'],
            'status' => [$ignoreId !== null ? 'required' : 'nullable', Rule::in(['active', 'inactive'])],
        ], [
            'full_name.required' => 'Nama lengkap wajib diisi.',
            'email.unique' => 'Email sudah terdaftar.',
            'direct_manager_employee_id.not_in' => 'Karyawan tidak boleh menjadi atasan dirinya sendiri.',
        ]);

        return $validated;
    }

    /**
     * A validation closure that rejects a value whose blind hash already exists
     * for the tenant (per-field duplicate detection on encrypted PII).
     */
    private function uniqueBlind(string $hashColumn, ?int $tenantId, ?int $ignoreId, string $message): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($hashColumn, $tenantId, $ignoreId, $message): void {
            $hash = Employee::blindHash(is_string($value) ? $value : null);

            if ($hash === null) {
                return;
            }

            $exists = Employee::query()
                ->where('tenant_id', $tenantId)
                ->where($hashColumn, $hash)
                ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
                ->exists();

            if ($exists) {
                $fail($message);
            }
        };
    }

    /** @var Collection<int, array{id: int, label: string, key: string, field_type: string, options: list<string>, is_required: bool}>|null */
    private ?Collection $cachedDefinitions = null;

    /**
     * @return Collection<int, array{id: int, label: string, key: string, field_type: string, options: list<string>, is_required: bool}>
     */
    private function customFieldDefinitions(): Collection
    {
        return $this->cachedDefinitions ??= CustomFieldDefinition::where('entity', 'employee')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (CustomFieldDefinition $def): array => [
                'id' => $def->id,
                'label' => $def->label,
                'key' => $def->key,
                'field_type' => $def->field_type,
                'options' => array_values($def->options ?? []),
                'is_required' => $def->is_required,
            ]);
    }

    /**
     * @return array<int, string|null>
     */
    private function customFieldValues(int $employeeId): array
    {
        $definitionIds = $this->customFieldDefinitions()->pluck('id');

        /** @var array<int, string|null> $values */
        $values = CustomFieldValue::whereIn('definition_id', $definitionIds)
            ->where('entity_id', $employeeId)
            ->pluck('value', 'definition_id')
            ->all();

        return $values;
    }

    /**
     * @return array<int, mixed>
     */
    private function validateCustom(Request $request): array
    {
        $definitions = $this->customFieldDefinitions();

        $rules = [];
        $attributes = [];
        foreach ($definitions as $def) {
            $base = $def['is_required'] ? ['required'] : ['nullable'];
            $rules["custom_fields.{$def['id']}"] = match ($def['field_type']) {
                'number' => [...$base, 'numeric'],
                'date' => [...$base, 'date'],
                'select' => [...$base, Rule::in($def['options'])],
                default => [...$base, 'string', 'max:1000'],
            };
            $attributes["custom_fields.{$def['id']}"] = $def['label'];
        }

        /** @var array{custom_fields?: array<int, mixed>} $validated */
        $validated = $request->validate($rules, [], $attributes);

        return $validated['custom_fields'] ?? [];
    }

    /**
     * @param  array<int, mixed>  $custom
     */
    private function saveCustomValues(int $employeeId, array $custom): void
    {
        foreach ($this->customFieldDefinitions() as $def) {
            $value = $custom[$def['id']] ?? null;

            CustomFieldValue::updateOrCreate(
                ['definition_id' => $def['id'], 'entity_id' => $employeeId],
                ['value' => $value === null ? null : (string) $value],
            );
        }
    }
}
