<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeContractController extends Controller
{
    /** @var list<array{value: string, label: string}> */
    private array $typeOptions = [
        ['value' => 'pkwt', 'label' => 'PKWT (Kontrak)'],
        ['value' => 'pkwtt', 'label' => 'PKWTT (Tetap)'],
        ['value' => 'magang', 'label' => 'Magang'],
        ['value' => 'kemitraan', 'label' => 'Kemitraan'],
    ];

    /** @var list<array{value: string, label: string}> */
    private array $statusOptions = [
        ['value' => 'active', 'label' => 'Aktif'],
        ['value' => 'expired', 'label' => 'Berakhir'],
        ['value' => 'terminated', 'label' => 'Diputus'],
    ];

    public function store(Request $request, Employee $employee): RedirectResponse
    {
        $data = $this->validateData($request, $employee);
        $data['file_path'] = $this->storeFile($request);

        $employee->contracts()->create($data);

        return to_route('employees.show', $employee->id)->with('success', 'Kontrak ditambahkan.');
    }

    public function update(Request $request, Employee $employee, EmployeeContract $contract): RedirectResponse
    {
        $data = $this->validateData($request, $employee, $contract->id);

        $newPath = $this->storeFile($request);
        if ($newPath !== null) {
            $this->deleteFile($contract->file_path);
            $data['file_path'] = $newPath;
        }

        $contract->update($data);

        return to_route('employees.show', $employee->id)->with('success', 'Kontrak diperbarui.');
    }

    public function destroy(Employee $employee, EmployeeContract $contract): RedirectResponse
    {
        $this->deleteFile($contract->file_path);
        $contract->delete();

        return to_route('employees.show', $employee->id)->with('success', 'Kontrak dihapus.');
    }

    public function download(Employee $employee, EmployeeContract $contract): StreamedResponse
    {
        abort_if(
            $contract->file_path === null || ! Storage::disk('local')->exists($contract->file_path),
            404,
        );

        $extension = pathinfo($contract->file_path, PATHINFO_EXTENSION);

        return Storage::disk('local')->download(
            $contract->file_path,
            $extension === '' ? $contract->contract_no : "{$contract->contract_no}.{$extension}",
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request, Employee $employee, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'contract_no' => [
                'required', 'string', 'max:255',
                Rule::unique('employee_contracts', 'contract_no')
                    ->where('tenant_id', $employee->tenant_id)
                    ->ignore($ignoreId),
            ],
            'type' => ['required', Rule::in(array_column($this->typeOptions, 'value'))],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['required', Rule::in(array_column($this->statusOptions, 'value'))],
            'file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ], [
            'contract_no.required' => 'Nomor kontrak wajib diisi.',
            'contract_no.unique' => 'Nomor kontrak sudah digunakan.',
            'type.required' => 'Jenis kontrak wajib dipilih.',
            'start_date.required' => 'Tanggal mulai wajib diisi.',
            'end_date.after_or_equal' => 'Tanggal berakhir harus sama atau setelah tanggal mulai.',
        ]);

        unset($validated['file']);

        return $validated;
    }

    private function storeFile(Request $request): ?string
    {
        if (! $request->hasFile('file')) {
            return null;
        }

        return $request->file('file')->store('contracts', 'local') ?: null;
    }

    private function deleteFile(?string $path): void
    {
        if ($path !== null && Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }
    }
}
