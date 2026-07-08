<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('employee_code');
            $table->string('full_name');
            $table->text('nik_ktp')->nullable();
            $table->char('nik_ktp_hash', 64)->nullable();
            $table->text('npwp')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->date('birth_date')->nullable();
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->enum('marital_status', ['single', 'married', 'divorced', 'widowed'])->nullable();
            $table->enum('ptkp_status', ['TK0', 'TK1', 'TK2', 'TK3', 'K0', 'K1', 'K2', 'K3'])->nullable();
            $table->foreignId('position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->foreignId('grade_id')->nullable()->constrained('grades')->nullOnDelete();
            $table->foreignId('org_unit_id')->nullable()->constrained('org_units')->nullOnDelete();
            $table->enum('employment_status', ['pkwt', 'pkwtt', 'magang', 'kemitraan'])->nullable();
            $table->date('join_date')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->date('inactive_date')->nullable();
            $table->foreignId('direct_manager_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('bank_name')->nullable();
            $table->text('bank_account')->nullable();
            $table->string('bank_account_name')->nullable();
            // FK constraint added in cross-domain migration (payroll_groups created later).
            $table->unsignedBigInteger('payroll_group_id')->nullable();
            $table->string('bpjs_kes_no')->nullable();
            $table->string('bpjs_tk_no')->nullable();
            $table->binary('face_embedding')->nullable();
            $table->timestamp('face_enrolled_at')->nullable();
            $table->string('photo_path')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'employee_code']);
            $table->unique(['tenant_id', 'nik_ktp_hash']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'org_unit_id', 'status'], 'employees_tenant_unit_status_index');
            $table->index(['tenant_id', 'position_id']);
            $table->index(['tenant_id', 'payroll_group_id', 'status'], 'employees_tenant_group_status_index');
            $table->index('direct_manager_employee_id');
        });

        Schema::create('employee_branch_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches');
            $table->boolean('is_primary')->default(false);
            $table->date('effective_date')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'branch_id']);
            $table->index(['tenant_id', 'branch_id']);
        });

        Schema::create('employee_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('contract_no');
            $table->enum('type', ['pkwt', 'pkwtt', 'magang', 'kemitraan']);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('file_path')->nullable();
            $table->enum('status', ['active', 'expired', 'terminated'])->default('active');
            $table->timestamps();

            $table->index(['tenant_id', 'end_date', 'status'], 'employee_contracts_tenant_end_status_index');
        });

        Schema::create('employee_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->enum('type', ['mutation', 'promotion', 'demotion']);
            $table->unsignedBigInteger('from_position_id')->nullable();
            $table->unsignedBigInteger('to_position_id')->nullable();
            $table->unsignedBigInteger('from_org_unit_id')->nullable();
            $table->unsignedBigInteger('to_org_unit_id')->nullable();
            $table->unsignedBigInteger('from_grade_id')->nullable();
            $table->unsignedBigInteger('to_grade_id')->nullable();
            $table->unsignedBigInteger('from_branch_id')->nullable();
            $table->unsignedBigInteger('to_branch_id')->nullable();
            $table->bigInteger('from_salary_snapshot')->nullable();
            $table->bigInteger('to_salary')->nullable();
            $table->date('effective_date');
            $table->enum('status', ['pending', 'approved', 'rejected', 'applied'])->default('pending');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'employee_id', 'effective_date'], 'emp_movements_tenant_emp_eff_index');
            $table->index(['tenant_id', 'status', 'effective_date'], 'emp_movements_tenant_status_eff_index');
        });

        Schema::create('employee_terminations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('employee_id')->unique()->constrained('employees')->cascadeOnDelete();
            $table->enum('type', ['resign', 'phk', 'pensiun', 'meninggal']);
            $table->date('effective_date');
            $table->text('reason')->nullable();
            $table->timestamp('clearance_completed_at')->nullable();
            $table->enum('status', ['pending', 'cleared', 'completed'])->default('pending');
            $table->timestamps();
        });

        Schema::create('employee_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users');
            $table->json('changes');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('custom_field_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('entity')->default('employee');
            $table->string('label');
            $table->string('key');
            $table->enum('field_type', ['text', 'number', 'date', 'select']);
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'entity', 'key']);
        });

        Schema::create('custom_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('definition_id')->constrained('custom_field_definitions')->cascadeOnDelete();
            $table->unsignedBigInteger('entity_id');
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['definition_id', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_field_values');
        Schema::dropIfExists('custom_field_definitions');
        Schema::dropIfExists('employee_change_requests');
        Schema::dropIfExists('employee_terminations');
        Schema::dropIfExists('employee_movements');
        Schema::dropIfExists('employee_contracts');
        Schema::dropIfExists('employee_branch_assignments');
        Schema::dropIfExists('employees');
    }
};
