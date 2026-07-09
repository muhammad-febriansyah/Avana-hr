<?php

namespace App\Http\Controllers;

use App\Actions\Approval\SubmitForApproval;
use App\Models\Employee;
use App\Models\EmployeeMovement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class EmployeeMovementController extends Controller
{
    public function __construct(private SubmitForApproval $submit) {}

    /** @var list<array{value: string, label: string}> */
    private array $typeOptions = [
        ['value' => 'mutation', 'label' => 'Mutasi'],
        ['value' => 'promotion', 'label' => 'Promosi'],
        ['value' => 'demotion', 'label' => 'Demosi'],
    ];

    public function store(Request $request, Employee $employee): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_column($this->typeOptions, 'value'))],
            'to_position_id' => ['nullable', Rule::exists('positions', 'id')->whereNull('deleted_at')],
            'to_org_unit_id' => ['nullable', Rule::exists('org_units', 'id')->whereNull('deleted_at')],
            'to_grade_id' => ['nullable', Rule::exists('grades', 'id')->whereNull('deleted_at')],
            'to_branch_id' => ['nullable', Rule::exists('branches', 'id')->whereNull('deleted_at')],
            'to_salary' => ['nullable', 'integer', 'min:0'],
            'effective_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [
            'type.required' => 'Jenis pergerakan wajib dipilih.',
            'effective_date.required' => 'Tanggal efektif wajib diisi.',
        ]);

        if (($data['to_position_id'] ?? null) === null
            && ($data['to_org_unit_id'] ?? null) === null
            && ($data['to_grade_id'] ?? null) === null
            && ($data['to_branch_id'] ?? null) === null) {
            throw ValidationException::withMessages([
                'type' => 'Minimal satu tujuan (posisi, unit, grade, atau cabang) wajib diisi.',
            ]);
        }

        $primaryBranchId = $employee->branchAssignments()->where('is_primary', true)->value('branch_id');

        $movement = EmployeeMovement::create([
            'employee_id' => $employee->id,
            'type' => $data['type'],
            'from_position_id' => $employee->position_id,
            'to_position_id' => $data['to_position_id'] ?? null,
            'from_org_unit_id' => $employee->org_unit_id,
            'to_org_unit_id' => $data['to_org_unit_id'] ?? null,
            'from_grade_id' => $employee->grade_id,
            'to_grade_id' => $data['to_grade_id'] ?? null,
            'from_branch_id' => $primaryBranchId,
            'to_branch_id' => $data['to_branch_id'] ?? null,
            'to_salary' => $data['to_salary'] ?? null,
            'effective_date' => $data['effective_date'],
            'note' => $data['note'] ?? null,
            'status' => 'pending',
        ]);

        try {
            $this->submit->handle($movement);
        } catch (RuntimeException $exception) {
            $movement->delete();

            return back()->with('error', $exception->getMessage());
        }

        return to_route('employees.show', $employee->id)->with('success', 'Pengajuan pergerakan dibuat.');
    }
}
