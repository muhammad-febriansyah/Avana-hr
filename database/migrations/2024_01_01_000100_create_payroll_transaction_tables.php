<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('payroll_group_id')->constrained('payroll_groups');
            $table->enum('frequency', ['monthly', 'weekly', 'biweekly'])->default('monthly');
            $table->enum('run_type', ['regular', 'thr', 'bonus', 'final_settlement'])->default('regular');
            $table->date('period_start');
            $table->date('period_end');
            $table->date('cutoff_date')->nullable();
            $table->date('payment_date')->nullable();
            $table->enum('status', ['draft', 'calculated', 'pending_approval', 'approved', 'locked', 'paid', 'cancelled'])->default('draft');
            $table->timestamp('calculated_at')->nullable();
            $table->foreignId('calculated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'payroll_group_id', 'period_start'], 'payroll_runs_tenant_group_period_index');
            $table->unique(['payroll_group_id', 'run_type', 'period_start', 'period_end'], 'payroll_runs_group_type_period_unique');
        });

        Schema::create('payroll_run_employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees');
            $table->string('employee_code_snapshot')->nullable();
            $table->string('employee_name_snapshot')->nullable();
            $table->string('position_snapshot')->nullable();
            $table->string('grade_snapshot')->nullable();
            $table->string('branch_snapshot')->nullable();
            $table->string('bank_name_snapshot')->nullable();
            $table->text('bank_account_snapshot')->nullable();
            $table->text('npwp_snapshot')->nullable();
            $table->string('ptkp_status_snapshot')->nullable();
            $table->char('ter_category_snapshot', 1)->nullable();
            $table->bigInteger('basic_salary_snapshot')->default(0);
            $table->unsignedTinyInteger('attendance_days')->default(0);
            $table->unsignedTinyInteger('absent_days')->default(0);
            $table->unsignedSmallInteger('late_minutes')->default(0);
            $table->unsignedSmallInteger('overtime_minutes')->default(0);
            $table->decimal('prorate_factor', 7, 6)->default(1);
            $table->bigInteger('gross')->default(0);
            $table->bigInteger('total_deduction')->default(0);
            $table->bigInteger('pph21')->default(0);
            $table->bigInteger('bpjs_employee')->default(0);
            $table->bigInteger('bpjs_employer')->default(0);
            $table->bigInteger('net')->default(0);
            $table->char('payslip_token', 36)->nullable();
            $table->enum('bank_export_status', ['pending', 'exported', 'exception'])->default('pending');
            $table->timestamps();

            $table->unique(['payroll_run_id', 'employee_id'], 'pre_run_employee_unique');
            $table->unique('payslip_token');
            $table->index(['tenant_id', 'employee_id']);
            $table->index(['payroll_run_id', 'bank_export_status'], 'pre_run_bank_status_index');
        });

        Schema::create('payslip_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_employee_id')->constrained('payroll_run_employees')->cascadeOnDelete();
            $table->string('component_code_snapshot')->nullable();
            $table->string('component_name_snapshot')->nullable();
            $table->enum('type', ['earning', 'deduction', 'adjustment', 'tax', 'bpjs']);
            $table->bigInteger('amount')->default(0);
            $table->string('calculation_note', 500)->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('payroll_run_employee_id');
        });

        Schema::create('payroll_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('effective_date')->nullable();
            $table->foreignId('target_run_id')->nullable()->constrained('payroll_runs')->nullOnDelete();
            $table->bigInteger('amount')->default(0);
            $table->enum('type', ['addition', 'deduction'])->default('addition');
            $table->text('change_points')->nullable();
            $table->text('reason')->nullable();
            $table->string('attachment_path')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'applied'])->default('pending');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('applied_in_run_id')->nullable()->constrained('payroll_runs')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['employee_id', 'status']);
        });

        Schema::create('employee_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->bigInteger('principal');
            $table->unsignedTinyInteger('tenor_months');
            $table->bigInteger('installment_amount');
            $table->bigInteger('outstanding');
            $table->date('start_period')->nullable();
            $table->enum('status', ['pending', 'approved', 'active', 'paid_off', 'cancelled'])->default('pending');
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['employee_id', 'status']);
        });

        Schema::create('loan_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('employee_loans')->cascadeOnDelete();
            $table->unsignedTinyInteger('seq');
            $table->date('due_period');
            $table->bigInteger('amount');
            $table->enum('status', ['scheduled', 'deducted', 'skipped'])->default('scheduled');
            $table->foreignId('payroll_run_id')->nullable()->constrained('payroll_runs')->nullOnDelete();
            $table->timestamps();

            $table->unique(['loan_id', 'seq']);
            $table->index(['due_period', 'status']);
        });

        Schema::create('bank_export_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
            $table->enum('bank_format', ['bca', 'mandiri', 'bri', 'bni']);
            $table->string('file_path')->nullable();
            $table->bigInteger('total_amount')->default(0);
            $table->integer('total_records')->default(0);
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index('payroll_run_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_export_batches');
        Schema::dropIfExists('loan_installments');
        Schema::dropIfExists('employee_loans');
        Schema::dropIfExists('payroll_adjustments');
        Schema::dropIfExists('payslip_lines');
        Schema::dropIfExists('payroll_run_employees');
        Schema::dropIfExists('payroll_runs');
    }
};
