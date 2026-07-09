<?php

namespace App\Http\Controllers;

use App\Models\Position;
use App\Support\ReportingLineGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PositionController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        Position::create($data);

        return back()->with('success', 'Posisi dibuat.');
    }

    public function update(Request $request, Position $position): RedirectResponse
    {
        $data = $this->validateData($request, $position->id);

        if (ReportingLineGuard::wouldCycle($position->id, $data['reports_to_position_id'] ?? null)) {
            return back()->withErrors([
                'reports_to_position_id' => 'Struktur pelaporan melingkar terdeteksi.',
            ]);
        }

        $position->update($data);

        return back()->with('success', 'Posisi diperbarui.');
    }

    public function destroy(Position $position): RedirectResponse
    {
        if ($position->directReports()->exists()) {
            return back()->with('error', 'Masih ada posisi yang melapor ke posisi ini.');
        }

        $position->delete();

        return back()->with('success', 'Posisi dihapus.');
    }

    /**
     * @return array{name: string, org_unit_id: int, grade_id: int|null, reports_to_position_id: int|null}
     */
    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        /** @var array{name: string, org_unit_id: int, grade_id: int|null, reports_to_position_id: int|null} $validated */
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'org_unit_id' => ['required', Rule::exists('org_units', 'id')->whereNull('deleted_at')],
            'grade_id' => ['nullable', Rule::exists('grades', 'id')->whereNull('deleted_at')],
            'reports_to_position_id' => [
                'nullable',
                Rule::exists('positions', 'id')->whereNull('deleted_at'),
                Rule::notIn($ignoreId !== null ? [$ignoreId] : []),
            ],
        ], [
            'name.required' => 'Nama posisi wajib diisi.',
            'org_unit_id.required' => 'Unit organisasi wajib dipilih.',
            'reports_to_position_id.not_in' => 'Posisi tidak boleh melapor ke dirinya sendiri.',
        ]);

        return $validated;
    }
}
