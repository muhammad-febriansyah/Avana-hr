<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use App\Models\ShiftPattern;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ShiftPatternController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('shift-patterns/index', [
            'patterns' => ShiftPattern::with('items')->orderBy('name')->get()->map(fn (ShiftPattern $pattern): array => [
                'id' => $pattern->id,
                'name' => $pattern->name,
                'cycle_days' => $pattern->cycle_days,
                'days' => $pattern->items->map(fn ($item): ?int => $item->shift_id)->all(),
            ]),
            'shifts' => Shift::orderBy('start_time')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $pattern = ShiftPattern::create(['name' => $data['name'], 'cycle_days' => $data['cycle_days']]);
        $this->syncItems($pattern, $data['days']);

        return back()->with('success', 'Pola shift dibuat.');
    }

    public function update(Request $request, ShiftPattern $pattern): RedirectResponse
    {
        $data = $this->validateData($request);
        $pattern->update(['name' => $data['name'], 'cycle_days' => $data['cycle_days']]);
        $this->syncItems($pattern, $data['days']);

        return back()->with('success', 'Pola shift diperbarui.');
    }

    public function destroy(ShiftPattern $pattern): RedirectResponse
    {
        $pattern->delete();

        return back()->with('success', 'Pola shift dihapus.');
    }

    /**
     * @return array{name: string, cycle_days: int, days: array<int, int|null>}
     */
    private function validateData(Request $request): array
    {
        /** @var array{name: string, cycle_days: int, days: array<int, int|null>} $validated */
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'cycle_days' => ['required', 'integer', 'min:1', 'max:31'],
            'days' => ['required', 'array', 'min:1'],
            'days.*' => ['nullable', Rule::exists('shifts', 'id')->whereNull('deleted_at')],
        ], [
            'name.required' => 'Nama pola wajib diisi.',
        ]);

        if (count($validated['days']) !== $validated['cycle_days']) {
            throw ValidationException::withMessages([
                'days' => 'Jumlah hari harus sama dengan panjang siklus.',
            ]);
        }

        return $validated;
    }

    /**
     * @param  array<int, int|null>  $days
     */
    private function syncItems(ShiftPattern $pattern, array $days): void
    {
        $pattern->items()->delete();

        foreach (array_values($days) as $index => $shiftId) {
            $pattern->items()->create([
                'day_seq' => $index + 1,
                'shift_id' => $shiftId,
            ]);
        }
    }
}
