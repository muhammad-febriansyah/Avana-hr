<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class EmployeeAccountController extends Controller
{
    /**
     * Provision an ESS login account for an employee (PRD M02.4).
     */
    public function store(Request $request, Employee $employee): RedirectResponse
    {
        if ($employee->email === null) {
            return back()->with('error', 'Karyawan belum memiliki email untuk akun ESS.');
        }

        if ($employee->user()->exists()) {
            return back()->with('error', 'Akun ESS untuk karyawan ini sudah ada.');
        }

        if (User::query()->where('email', $employee->email)->exists()) {
            return back()->with('error', 'Email sudah digunakan oleh akun lain.');
        }

        $user = User::create([
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->id,
            'name' => $employee->full_name,
            'email' => $employee->email,
            'password' => Hash::make(Str::password(16)),
            'is_active' => true,
        ]);

        $user->assignRole(Role::Employee->value);

        return to_route('employees.show', $employee->id)
            ->with('success', 'Akun ESS dibuat. Karyawan dapat mengatur kata sandi lewat "Lupa Password".');
    }
}
