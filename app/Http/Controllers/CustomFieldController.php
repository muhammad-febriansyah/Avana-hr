<?php

namespace App\Http\Controllers;

use App\Models\CustomFieldDefinition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tenant management of custom field definitions for the employee form.
 */
class CustomFieldController extends Controller
{
    /** @var list<string> */
    private array $fieldTypes = ['text', 'number', 'date', 'select'];

    public function index(): Response
    {
        $definitions = CustomFieldDefinition::where('entity', 'employee')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (CustomFieldDefinition $def): array => [
                'id' => $def->id,
                'label' => $def->label,
                'key' => $def->key,
                'field_type' => $def->field_type,
                'options' => $def->options ?? [],
                'is_required' => $def->is_required,
                'sort_order' => $def->sort_order,
            ]);

        return Inertia::render('employees/custom-fields', [
            'definitions' => $definitions,
            'fieldTypes' => $this->fieldTypes,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        CustomFieldDefinition::create($this->validateData($request));

        return back()->with('success', 'Custom field dibuat.');
    }

    public function update(Request $request, CustomFieldDefinition $customField): RedirectResponse
    {
        $customField->update($this->validateData($request, $customField->id));

        return back()->with('success', 'Custom field diperbarui.');
    }

    public function destroy(CustomFieldDefinition $customField): RedirectResponse
    {
        $customField->delete();

        return back()->with('success', 'Custom field dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'key' => [
                'required', 'string', 'max:255', 'alpha_dash',
                Rule::unique('custom_field_definitions', 'key')
                    ->where('tenant_id', $request->user()->tenant_id)
                    ->where('entity', 'employee')
                    ->ignore($ignoreId),
            ],
            'field_type' => ['required', Rule::in($this->fieldTypes)],
            'options' => ['nullable', 'array', 'required_if:field_type,select'],
            'options.*' => ['string', 'max:255'],
            'is_required' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ], [
            'label.required' => 'Label wajib diisi.',
            'key.required' => 'Key wajib diisi.',
            'key.unique' => 'Key sudah digunakan.',
            'options.required_if' => 'Opsi wajib diisi untuk tipe pilihan.',
        ]);

        $validated['entity'] = 'employee';
        $validated['options'] = $validated['field_type'] === 'select' ? array_values($validated['options'] ?? []) : null;

        return $validated;
    }
}
