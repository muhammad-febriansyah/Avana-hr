<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HolidayController extends Controller
{
    public function index(Request $request): Response
    {
        $tenantId = $request->user()->tenant_id;

        return Inertia::render('holidays/index', [
            'holidays' => Holiday::visibleTo($tenantId)
                ->orderBy('date')
                ->get()
                ->map(fn (Holiday $holiday): array => [
                    'id' => $holiday->id,
                    'date' => $holiday->date->toDateString(),
                    'name' => $holiday->name,
                    'is_national' => $holiday->tenant_id === null,
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'name' => ['required', 'string', 'max:255'],
        ], [
            'date.required' => 'Tanggal wajib diisi.',
            'name.required' => 'Nama hari libur wajib diisi.',
        ]);

        Holiday::create([
            ...$data,
            'tenant_id' => $request->user()->tenant_id,
        ]);

        return back()->with('success', 'Hari libur ditambahkan.');
    }

    public function destroy(Request $request, Holiday $holiday): RedirectResponse
    {
        // National (null-tenant) holidays cannot be removed by a tenant.
        abort_unless($holiday->tenant_id === $request->user()->tenant_id, 403);

        $holiday->delete();

        return back()->with('success', 'Hari libur dihapus.');
    }
}
