<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates every ERD domain table', function () {
    $tables = [
        // Platform & foundation
        'plans', 'plan_features', 'tenants', 'tenant_settings',
        'approval_flows', 'approval_steps', 'approvals', 'approval_actions', 'approval_delegations',
        'audit_logs', 'security_logs',
        'tax_ptkp_rates', 'tax_ter_rates', 'bpjs_rates', 'regional_minimum_wages', 'holidays',
        // Organisasi & karyawan
        'org_units', 'grades', 'positions', 'branches',
        'employees', 'employee_branch_assignments', 'employee_contracts', 'employee_movements',
        'employee_terminations', 'employee_change_requests', 'custom_field_definitions', 'custom_field_values',
        // Absensi, cuti, lembur
        'shifts', 'shift_patterns', 'shift_pattern_items', 'employee_schedules',
        'attendance_events', 'attendance_summaries', 'attendance_corrections',
        'leave_types', 'leave_balances', 'leave_requests', 'overtime_requests',
        // Payroll
        'component_formulas', 'working_day_rules', 'salary_components', 'formula_items',
        'payroll_groups', 'payroll_group_components', 'component_value_mappings',
        'employee_component_overrides', 'employee_basic_salaries',
        'payroll_runs', 'payroll_run_employees', 'payslip_lines', 'payroll_adjustments',
        'employee_loans', 'loan_installments', 'bank_export_batches',
        // Notifikasi & misc
        'notifications', 'device_tokens', 'announcements',
        // Addendum: menu, AI, CRM, calendar
        'menus', 'tenant_menu_overrides', 'tenant_menu_settings', 'tenant_menu_role_visibility',
        'ai_providers', 'ai_feature_configs', 'tenant_ai_settings', 'ai_usage_logs',
        'crm_pipelines', 'crm_stages', 'crm_deals', 'crm_deal_members', 'crm_activities', 'crm_tasks',
        'company_events',
    ];

    foreach ($tables as $table) {
        expect(Schema::hasTable($table))->toBeTrue("Tabel '{$table}' tidak ada");
    }
});

it('adds avatar to users and logo to tenants', function () {
    expect(Schema::hasColumn('users', 'avatar_path'))->toBeTrue();
    expect(Schema::hasColumn('users', 'tenant_id'))->toBeTrue();
    expect(Schema::hasColumn('users', 'employee_id'))->toBeTrue();
    expect(Schema::hasColumn('tenants', 'logo_path'))->toBeTrue();
});

it('exposes avatar_url accessor on user', function () {
    $user = User::factory()->create(['avatar_path' => 'avatars/foo.png']);

    expect($user->avatar_url)->toContain('avatars/foo.png');
    expect(User::factory()->create(['avatar_path' => null])->avatar_url)->toBeNull();
});

it('exposes logo_url accessor on tenant', function () {
    $tenant = Tenant::factory()->create(['logo_path' => 'logos/acme.png']);

    expect($tenant->logo_url)->toContain('logos/acme.png');
    expect(Tenant::factory()->create(['logo_path' => null])->logo_url)->toBeNull();
});

it('scopes email uniqueness per tenant, not globally', function () {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();

    User::factory()->create(['tenant_id' => $a->id, 'email' => 'sama@avana.test']);

    // Same email under a different tenant is allowed.
    $second = User::factory()->create(['tenant_id' => $b->id, 'email' => 'sama@avana.test']);

    expect($second->exists)->toBeTrue();
});
