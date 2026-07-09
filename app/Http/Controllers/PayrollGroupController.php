<?php

namespace App\Http\Controllers;

use App\Models\PayrollGroup;
use App\Models\SalaryComponent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PayrollGroupController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('payroll-groups/index', [
            'groups' => PayrollGroup::withCount('components')->orderBy('name')->get()->map(fn (PayrollGroup $group): array => [
                'id' => $group->id,
                'code' => $group->code,
                'name' => $group->name,
                'frequency' => $group->frequency,
                'cutoff_day' => $group->cutoff_day,
                'is_active' => $group->is_active,
                'component_count' => $group->components_count,
                'component_ids' => $group->components()->pluck('salary_components.id'),
            ]),
            'components' => SalaryComponent::where('is_active', true)->orderBy('sort_order')->get(['id', 'code', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $group = PayrollGroup::create($data['attributes']);
        $group->components()->sync($data['component_ids']);

        return back()->with('success', 'Payroll group dibuat.');
    }

    public function update(Request $request, PayrollGroup $payrollGroup): RedirectResponse
    {
        $data = $this->validateData($request, $payrollGroup->id);
        $payrollGroup->update($data['attributes']);
        $payrollGroup->components()->sync($data['component_ids']);

        return back()->with('success', 'Payroll group diperbarui.');
    }

    public function destroy(PayrollGroup $payrollGroup): RedirectResponse
    {
        $payrollGroup->delete();

        return back()->with('success', 'Payroll group dihapus.');
    }

    /**
     * @return array{attributes: array<string, mixed>, component_ids: list<int>}
     */
    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'code' => [
                'required', 'string', 'max:32',
                Rule::unique('payroll_groups', 'code')
                    ->where('tenant_id', $request->user()->tenant_id)
                    ->whereNull('deleted_at')
                    ->ignore($ignoreId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'frequency' => ['required', Rule::in(['monthly', 'weekly', 'biweekly'])],
            'period_start_day' => ['required', 'integer', 'min:1', 'max:28'],
            'cutoff_day' => ['required', 'integer', 'min:1', 'max:28'],
            'attendance_source' => ['required', Rule::in(['current', 'previous'])],
            'overtime_source' => ['required', Rule::in(['current', 'previous'])],
            'prorate_method' => ['required', Rule::in(['calendar', 'workdays'])],
            'is_active' => ['boolean'],
            'component_ids' => ['array'],
            'component_ids.*' => [Rule::exists('salary_components', 'id')->whereNull('deleted_at')],
        ], [
            'code.required' => 'Kode wajib diisi.',
            'code.unique' => 'Kode sudah digunakan.',
            'name.required' => 'Nama wajib diisi.',
        ]);

        $componentIds = $validated['component_ids'] ?? [];
        unset($validated['component_ids']);

        return [
            'attributes' => $validated,
            'component_ids' => array_values(array_map('intval', $componentIds)),
        ];
    }
}
