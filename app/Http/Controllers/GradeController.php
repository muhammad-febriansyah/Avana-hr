<?php

namespace App\Http\Controllers;

use App\Models\Grade;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GradeController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        Grade::create($this->validateData($request));

        return back()->with('success', 'Grade dibuat.');
    }

    public function update(Request $request, Grade $grade): RedirectResponse
    {
        $grade->update($this->validateData($request, $grade->id));

        return back()->with('success', 'Grade diperbarui.');
    }

    public function destroy(Grade $grade): RedirectResponse
    {
        if ($grade->positions()->exists()) {
            return back()->with('error', 'Grade masih dipakai oleh posisi.');
        }

        $grade->delete();

        return back()->with('success', 'Grade dihapus.');
    }

    /**
     * @return array{code: string, name: string, salary_min: int, salary_max: int}
     */
    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        /** @var array{code: string, name: string, salary_min: int, salary_max: int} $validated */
        $validated = $request->validate([
            'code' => [
                'required', 'string', 'max:255',
                Rule::unique('grades', 'code')
                    ->where('tenant_id', $request->user()->tenant_id)
                    ->whereNull('deleted_at')
                    ->ignore($ignoreId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'salary_min' => ['required', 'integer', 'min:0'],
            'salary_max' => ['required', 'integer', 'gte:salary_min'],
        ], [
            'code.required' => 'Kode grade wajib diisi.',
            'code.unique' => 'Kode grade sudah digunakan.',
            'salary_max.gte' => 'Batas atas gaji harus ≥ batas bawah.',
        ]);

        return $validated;
    }
}
