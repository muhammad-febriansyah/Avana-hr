<?php

namespace App\Http\Controllers;

use App\Exports\EmployeeImportExceptions;
use App\Exports\EmployeeImportTemplate;
use App\Imports\EmployeesImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeImportController extends Controller
{
    public function template(): BinaryFileResponse
    {
        return Excel::download(new EmployeeImportTemplate, 'template_import_karyawan.xlsx');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ], [
            'file.required' => 'Pilih berkas untuk diimpor.',
            'file.mimes' => 'Berkas harus berformat Excel (.xlsx/.xls) atau CSV.',
        ]);

        $import = new EmployeesImport($request->user()->tenant);
        Excel::import($import, $request->file('file'));

        $failed = count($import->exceptions);
        $token = null;

        if ($failed > 0) {
            $token = (string) Str::uuid();
            // Scope the file path to the user so only its owner can download it.
            Excel::store(new EmployeeImportExceptions($import->exceptions), $this->exceptionsPath($request->user()->id, $token), 'local');
        }

        Inertia::flash('toast', [
            'type' => $failed === 0 ? 'success' : 'warning',
            'message' => "{$import->importedCount} karyawan diimpor, {$failed} baris gagal.",
        ]);

        return to_route('employees.index')->with('importResult', [
            'imported' => $import->importedCount,
            'failed' => $failed,
            'exceptions' => array_slice($import->exceptions, 0, 100),
            'token' => $token,
        ]);
    }

    public function exceptions(Request $request, string $token): StreamedResponse
    {
        $path = $this->exceptionsPath($request->user()->id, $token);

        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, 'exception_import_karyawan.xlsx');
    }

    private function exceptionsPath(int $userId, string $token): string
    {
        return "imports/{$userId}/{$token}.xlsx";
    }
}
