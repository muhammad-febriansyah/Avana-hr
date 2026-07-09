<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BranchController extends Controller
{
    /**
     * Work timezones supported for branches (Indonesia).
     *
     * @var list<array{value: string, label: string}>
     */
    private array $timezones = [
        ['value' => 'Asia/Jakarta', 'label' => 'WIB (Asia/Jakarta)'],
        ['value' => 'Asia/Makassar', 'label' => 'WITA (Asia/Makassar)'],
        ['value' => 'Asia/Jayapura', 'label' => 'WIT (Asia/Jayapura)'],
    ];

    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('q'));

        $branches = Branch::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($q) use ($search): void {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->get()
            ->map(fn (Branch $branch): array => [
                'id' => $branch->id,
                'code' => $branch->code,
                'name' => $branch->name,
                'address' => $branch->address,
                'latitude' => $branch->latitude,
                'longitude' => $branch->longitude,
                'geofence_radius_m' => $branch->geofence_radius_m,
                'timezone' => $branch->timezone,
                'cost_center' => $branch->cost_center,
            ]);

        return Inertia::render('branches/index', [
            'branches' => $branches,
            'filters' => ['q' => $search],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('branches/form', [
            'branch' => null,
            'timezones' => $this->timezones,
        ]);
    }

    public function edit(Branch $branch): Response
    {
        return Inertia::render('branches/form', [
            'branch' => [
                'id' => $branch->id,
                'code' => $branch->code,
                'name' => $branch->name,
                'address' => $branch->address,
                'latitude' => $branch->latitude,
                'longitude' => $branch->longitude,
                'geofence_radius_m' => $branch->geofence_radius_m,
                'timezone' => $branch->timezone,
                'cost_center' => $branch->cost_center,
            ],
            'timezones' => $this->timezones,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Branch::create($this->validateData($request));

        return to_route('branches.index')->with('success', 'Cabang dibuat.');
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        $branch->update($this->validateData($request, $branch->id));

        return to_route('branches.index')->with('success', 'Cabang diperbarui.');
    }

    public function destroy(Branch $branch): RedirectResponse
    {
        $branch->delete();

        return back()->with('success', 'Cabang dihapus.');
    }

    /**
     * @return array{code: string, name: string, address: string|null, latitude: float|null, longitude: float|null, geofence_radius_m: int, timezone: string, cost_center: string|null}
     */
    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        /** @var array{code: string, name: string, address: string|null, latitude: float|null, longitude: float|null, geofence_radius_m: int, timezone: string, cost_center: string|null} $validated */
        $validated = $request->validate([
            'code' => [
                'required', 'string', 'max:255',
                Rule::unique('branches', 'code')
                    ->where('tenant_id', $request->user()->tenant_id)
                    ->whereNull('deleted_at')
                    ->ignore($ignoreId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'geofence_radius_m' => ['required', 'integer', 'min:10', 'max:10000'],
            'timezone' => ['required', Rule::in(array_column($this->timezones, 'value'))],
            'cost_center' => ['nullable', 'string', 'max:255'],
        ], [
            'code.required' => 'Kode cabang wajib diisi.',
            'code.unique' => 'Kode cabang sudah digunakan.',
            'name.required' => 'Nama cabang wajib diisi.',
            'geofence_radius_m.required' => 'Radius geofence wajib diisi.',
        ]);

        return $validated;
    }
}
