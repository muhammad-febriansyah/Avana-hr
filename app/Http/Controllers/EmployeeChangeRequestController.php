<?php

namespace App\Http\Controllers;

use App\Actions\Approval\SubmitForApproval;
use App\Models\Employee;
use App\Models\EmployeeChangeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class EmployeeChangeRequestController extends Controller
{
    public function __construct(private SubmitForApproval $submit) {}

    /**
     * Non-sensitive employee fields that may be changed via maker-checker.
     * Encrypted PII (NIK/NPWP/bank account) and structural fields (handled by
     * movements) are intentionally excluded.
     */
    public function store(Request $request, Employee $employee): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => [
                'nullable', 'email', 'max:255',
                Rule::unique('employees', 'email')->where('tenant_id', $tenantId)->whereNull('deleted_at')->ignore($employee->id),
            ],
            'phone' => ['nullable', 'string', 'max:32'],
            'marital_status' => ['nullable', Rule::in(['single', 'married', 'divorced', 'widowed'])],
            'ptkp_status' => ['nullable', Rule::in(['TK0', 'TK1', 'TK2', 'TK3', 'K0', 'K1', 'K2', 'K3'])],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account_name' => ['nullable', 'string', 'max:255'],
        ], [
            'full_name.required' => 'Nama lengkap wajib diisi.',
            'email.unique' => 'Email sudah terdaftar.',
        ]);

        $changes = [];
        foreach ($data as $field => $value) {
            $newValue = $value === '' ? null : $value;

            if ($newValue !== ($employee->getAttribute($field) ?? null)) {
                $changes[$field] = $newValue;
            }
        }

        if ($changes === []) {
            throw ValidationException::withMessages([
                'full_name' => 'Tidak ada perubahan yang diajukan.',
            ]);
        }

        $changeRequest = EmployeeChangeRequest::create([
            'employee_id' => $employee->id,
            'requested_by' => $request->user()->id,
            'changes' => $changes,
            'status' => 'pending',
        ]);

        try {
            $this->submit->handle($changeRequest);
        } catch (RuntimeException $exception) {
            $changeRequest->delete();

            return back()->with('error', $exception->getMessage());
        }

        return to_route('employees.show', $employee->id)->with('success', 'Pengajuan perubahan data dibuat.');
    }
}
