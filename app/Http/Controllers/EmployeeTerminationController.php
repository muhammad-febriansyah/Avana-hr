<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmployeeTerminationController extends Controller
{
    /** @var list<array{value: string, label: string}> */
    private array $typeOptions = [
        ['value' => 'resign', 'label' => 'Resign'],
        ['value' => 'phk', 'label' => 'PHK'],
        ['value' => 'pensiun', 'label' => 'Pensiun'],
        ['value' => 'meninggal', 'label' => 'Meninggal'],
    ];

    public function store(Request $request, Employee $employee): RedirectResponse
    {
        if ($employee->termination()->exists()) {
            return back()->with('error', 'Karyawan sudah memiliki data terminasi.');
        }

        $data = $request->validate([
            'type' => ['required', Rule::in(array_column($this->typeOptions, 'value'))],
            'effective_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ], [
            'type.required' => 'Jenis terminasi wajib dipilih.',
            'effective_date.required' => 'Tanggal efektif wajib diisi.',
        ]);

        $employee->termination()->create([
            ...$data,
            'status' => 'pending',
        ]);

        return to_route('employees.show', $employee->id)->with('success', 'Data terminasi dibuat.');
    }

    public function clearance(Request $request, Employee $employee): RedirectResponse
    {
        $termination = $employee->termination()->firstOrFail();

        if ($termination->status === 'completed') {
            return back()->with('error', 'Terminasi sudah selesai diproses.');
        }

        $termination->update([
            'clearance_completed_at' => now(),
            'status' => 'cleared',
        ]);

        return back()->with('success', 'Exit clearance ditandai selesai.');
    }
}
