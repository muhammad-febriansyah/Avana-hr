<?php

namespace App\Http\Controllers;

use App\Enums\OrgUnitType;
use App\Models\OrgUnit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrgUnitController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        OrgUnit::create($data);

        return back()->with('success', 'Unit organisasi dibuat.');
    }

    public function update(Request $request, OrgUnit $orgUnit): RedirectResponse
    {
        $data = $this->validateData($request, $orgUnit->id);

        if ($this->wouldCycle($orgUnit->id, $data['parent_id'] ?? null)) {
            return back()->withErrors(['parent_id' => 'Induk tidak boleh unit itu sendiri atau turunannya.']);
        }

        $orgUnit->update($data);

        return back()->with('success', 'Unit organisasi diperbarui.');
    }

    public function destroy(OrgUnit $orgUnit): RedirectResponse
    {
        if ($orgUnit->children()->exists()) {
            return back()->with('error', 'Hapus atau pindahkan sub-unit terlebih dahulu.');
        }

        if ($orgUnit->positions()->exists()) {
            return back()->with('error', 'Masih ada posisi pada unit ini.');
        }

        $orgUnit->delete();

        return back()->with('success', 'Unit organisasi dihapus.');
    }

    /**
     * @return array{name: string, type: string, parent_id: int|null, cost_center: string|null, effective_date: string|null}
     */
    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        /** @var array{name: string, type: string, parent_id: int|null, cost_center: string|null, effective_date: string|null} $validated */
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(OrgUnitType::class)],
            'parent_id' => [
                'nullable',
                Rule::exists('org_units', 'id')->whereNull('deleted_at'),
                Rule::notIn($ignoreId !== null ? [$ignoreId] : []),
            ],
            'cost_center' => ['nullable', 'string', 'max:255'],
            'effective_date' => ['nullable', 'date'],
        ], [
            'name.required' => 'Nama unit wajib diisi.',
            'type.required' => 'Tipe unit wajib dipilih.',
            'parent_id.not_in' => 'Induk tidak boleh unit itu sendiri.',
        ]);

        return $validated;
    }

    /**
     * True if $parentId is the unit itself or one of its descendants.
     */
    private function wouldCycle(int $unitId, ?int $parentId): bool
    {
        if ($parentId === null) {
            return false;
        }

        if ($parentId === $unitId) {
            return true;
        }

        $current = $parentId;
        $visited = [];

        while ($current !== null) {
            if ($current === $unitId) {
                return true;
            }

            if (in_array($current, $visited, true)) {
                break;
            }

            $visited[] = $current;

            $next = OrgUnit::whereKey($current)->value('parent_id');
            $current = $next === null ? null : (int) $next;
        }

        return false;
    }
}
