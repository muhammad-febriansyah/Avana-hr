<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('component_formulas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('contract_display', ['process', 'setting'])->default('process');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('working_day_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->enum('method', ['fixed', 'calendar', 'workdays'])->default('fixed');
            $table->unsignedTinyInteger('divisor_days')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('salary_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->enum('type', ['earning', 'deduction']);
            $table->date('effective_date')->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->boolean('is_taxable')->default(false);
            $table->enum('process_type', ['regular', 'irregular'])->default('regular');
            $table->enum('frequency', ['monthly', 'weekly', 'biweekly'])->default('monthly');
            $table->boolean('show_on_payslip')->default(true);
            $table->boolean('show_on_contract')->default(false);
            $table->boolean('pay_after_inactive')->default(false);
            $table->enum('calc_basis', ['formula', 'table', 'fixed'])->default('fixed');
            $table->foreignId('formula_id')->nullable()->constrained('component_formulas')->nullOnDelete();
            $table->bigInteger('fixed_amount')->nullable();
            $table->bigInteger('min_amount')->nullable();
            $table->bigInteger('max_amount')->nullable();
            $table->boolean('prorate_enabled')->default(false);
            $table->boolean('overtime_related')->default(false);
            $table->boolean('bpjs_basis')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'type', 'is_active'], 'salary_components_tenant_type_active_index');
        });

        Schema::create('formula_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('formula_id')->constrained('component_formulas')->cascadeOnDelete();
            $table->unsignedTinyInteger('seq');
            $table->enum('source_type', ['earning', 'deduction', 'umr', 'constant']);
            $table->foreignId('source_component_id')->nullable()->constrained('salary_components')->nullOnDelete();
            $table->decimal('multiplier', 10, 4)->default(1);
            $table->bigInteger('add_operand')->default(0);
            $table->boolean('prorate')->default(false);
            $table->timestamps();

            $table->unique(['formula_id', 'seq']);
        });

        Schema::create('payroll_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('frequency', ['monthly', 'weekly', 'biweekly'])->default('monthly');
            $table->unsignedTinyInteger('period_start_day')->default(1);
            $table->unsignedTinyInteger('cutoff_day')->default(25);
            $table->foreignId('working_day_rule_id')->nullable()->constrained('working_day_rules')->nullOnDelete();
            $table->enum('attendance_source', ['current', 'previous'])->default('current');
            $table->enum('overtime_source', ['current', 'previous'])->default('current');
            $table->enum('prorate_method', ['calendar', 'workdays'])->default('calendar');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('payroll_group_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_group_id')->constrained('payroll_groups')->cascadeOnDelete();
            $table->foreignId('salary_component_id')->constrained('salary_components')->cascadeOnDelete();
            $table->boolean('is_prorated')->default(false);
            $table->boolean('is_overtime_base')->default(false);
            $table->timestamps();

            $table->unique(['payroll_group_id', 'salary_component_id'], 'pgc_group_component_unique');
        });

        Schema::create('component_value_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('salary_component_id')->constrained('salary_components')->cascadeOnDelete();
            $table->string('employment_status')->nullable();
            $table->foreignId('position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->foreignId('grade_id')->nullable()->constrained('grades')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('area_code')->nullable();
            $table->bigInteger('value');
            $table->smallInteger('priority')->default(0);
            $table->date('effective_date')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'salary_component_id', 'effective_date'], 'cvm_tenant_comp_eff_index');
            $table->index('grade_id');
            $table->index('position_id');
        });

        Schema::create('employee_component_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('salary_component_id')->constrained('salary_components')->cascadeOnDelete();
            $table->bigInteger('value');
            $table->string('sk_no')->nullable();
            $table->string('attachment_path')->nullable();
            $table->date('effective_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();

            $table->unique(['employee_id', 'salary_component_id', 'effective_date'], 'eco_emp_comp_eff_unique');
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('employee_basic_salaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('effective_date');
            $table->string('sk_no')->nullable();
            $table->boolean('is_umr')->default(false);
            $table->bigInteger('amount');
            $table->text('note')->nullable();
            $table->string('attachment_path')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'effective_date'], 'basic_salaries_emp_eff_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_basic_salaries');
        Schema::dropIfExists('employee_component_overrides');
        Schema::dropIfExists('component_value_mappings');
        Schema::dropIfExists('payroll_group_components');
        Schema::dropIfExists('payroll_groups');
        Schema::dropIfExists('formula_items');
        Schema::dropIfExists('salary_components');
        Schema::dropIfExists('working_day_rules');
        Schema::dropIfExists('component_formulas');
    }
};
