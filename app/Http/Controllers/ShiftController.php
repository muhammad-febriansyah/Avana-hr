<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShiftController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('shifts/index', [
            'shifts' => Shift::orderBy('start_time')->get()->map(fn (Shift $shift): array => [
                'id' => $shift->id,
                'name' => $shift->name,
                'start_time' => substr($shift->start_time, 0, 5),
                'end_time' => substr($shift->end_time, 0, 5),
                'is_overnight' => $shift->is_overnight,
                'late_tolerance_min' => $shift->late_tolerance_min,
                'break_minutes' => $shift->break_minutes,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Shift::create($this->validateData($request));

        return back()->with('success', 'Shift dibuat.');
    }

    public function update(Request $request, Shift $shift): RedirectResponse
    {
        $shift->update($this->validateData($request));

        return back()->with('success', 'Shift diperbarui.');
    }

    public function destroy(Shift $shift): RedirectResponse
    {
        $shift->delete();

        return back()->with('success', 'Shift dihapus.');
    }

    /**
     * @return array{name: string, start_time: string, end_time: string, is_overnight: bool, late_tolerance_min: int, break_minutes: int}
     */
    private function validateData(Request $request): array
    {
        /** @var array{name: string, start_time: string, end_time: string, is_overnight: bool, late_tolerance_min: int, break_minutes: int} $validated */
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'is_overnight' => ['boolean'],
            'late_tolerance_min' => ['required', 'integer', 'min:0', 'max:240'],
            'break_minutes' => ['required', 'integer', 'min:0', 'max:480'],
        ], [
            'name.required' => 'Nama shift wajib diisi.',
            'start_time.required' => 'Jam mulai wajib diisi.',
            'end_time.required' => 'Jam selesai wajib diisi.',
        ]);

        return $validated;
    }
}
