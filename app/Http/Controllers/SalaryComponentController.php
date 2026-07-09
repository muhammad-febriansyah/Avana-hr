<?php

namespace App\Http\Controllers;

use App\Models\SalaryComponent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SalaryComponentController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('salary-components/index', [
            'components' => SalaryComponent::orderBy('sort_order')->orderBy('code')->get()->map(fn (SalaryComponent $component): array => [
                'id' => $component->id,
                'code' => $component->code,
                'name' => $component->name,
                'type' => $component->type,
                'calc_basis' => $component->calc_basis,
                'fixed_amount' => $component->fixed_amount,
                'is_taxable' => $component->is_taxable,
                'bpjs_basis' => $component->bpjs_basis,
                'prorate_enabled' => $component->prorate_enabled,
                'overtime_related' => $component->overtime_related,
                'is_active' => $component->is_active,
                'sort_order' => $component->sort_order,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        SalaryComponent::create($this->validateData($request));

        return back()->with('success', 'Komponen gaji dibuat.');
    }

    public function update(Request $request, SalaryComponent $salaryComponent): RedirectResponse
    {
        $salaryComponent->update($this->validateData($request, $salaryComponent->id));

        return back()->with('success', 'Komponen gaji diperbarui.');
    }

    public function destroy(SalaryComponent $salaryComponent): RedirectResponse
    {
        $salaryComponent->delete();

        return back()->with('success', 'Komponen gaji dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'code' => [
                'required', 'string', 'max:32',
                Rule::unique('salary_components', 'code')
                    ->where('tenant_id', $request->user()->tenant_id)
                    ->whereNull('deleted_at')
                    ->ignore($ignoreId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['earning', 'deduction'])],
            'calc_basis' => ['required', Rule::in(['formula', 'table', 'fixed'])],
            'fixed_amount' => ['nullable', 'integer', 'min:0', 'required_if:calc_basis,fixed'],
            'min_amount' => ['nullable', 'integer', 'min:0'],
            'max_amount' => ['nullable', 'integer', 'min:0'],
            'is_taxable' => ['boolean'],
            'bpjs_basis' => ['boolean'],
            'prorate_enabled' => ['boolean'],
            'overtime_related' => ['boolean'],
            'show_on_payslip' => ['boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
            'is_active' => ['boolean'],
        ], [
            'code.required' => 'Kode wajib diisi.',
            'code.unique' => 'Kode sudah digunakan.',
            'name.required' => 'Nama komponen wajib diisi.',
            'fixed_amount.required_if' => 'Nominal wajib diisi untuk basis tetap.',
        ]);

        return $validated;
    }
}
