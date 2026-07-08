<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_overnight')->default(false);
            $table->unsignedSmallInteger('late_tolerance_min')->default(0);
            $table->unsignedSmallInteger('break_minutes')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
        });

        Schema::create('shift_patterns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedTinyInteger('cycle_days');
            $table->timestamps();
        });

        Schema::create('shift_pattern_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pattern_id')->constrained('shift_patterns')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_seq');
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->timestamps();

            $table->unique(['pattern_id', 'day_seq']);
        });

        Schema::create('employee_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->boolean('is_day_off')->default(false);
            $table->enum('source', ['generated', 'manual'])->default('generated');
            $table->timestamps();

            $table->unique(['employee_id', 'date']);
            $table->index(['tenant_id', 'date']);
        });

        Schema::create('attendance_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->char('event_uuid', 36);
            $table->enum('type', ['in', 'out']);
            $table->timestamp('occurred_at');
            $table->timestamp('device_captured_at')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->enum('channel', ['mobile_face', 'web', 'kiosk', 'import']);
            $table->decimal('similarity_score', 5, 4)->nullable();
            $table->boolean('liveness_passed')->nullable();
            $table->boolean('is_outside_geofence')->default(false);
            $table->boolean('is_suspicious')->default(false);
            $table->string('device_id')->nullable();
            $table->timestamps();

            $table->unique('event_uuid');
            $table->index(['tenant_id', 'employee_id', 'occurred_at'], 'att_events_tenant_emp_occurred_index');
            $table->index(['tenant_id', 'occurred_at']);
            $table->index(['tenant_id', 'is_suspicious', 'occurred_at'], 'att_events_tenant_suspicious_index');
        });

        Schema::create('attendance_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('schedule_shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->timestamp('clock_in')->nullable();
            $table->timestamp('clock_out')->nullable();
            $table->enum('status', ['present', 'late', 'early_leave', 'absent', 'leave', 'holiday', 'day_off', 'duty', 'wfh']);
            $table->unsignedSmallInteger('late_minutes')->default(0);
            $table->unsignedSmallInteger('work_minutes')->default(0);
            $table->unsignedSmallInteger('overtime_minutes')->default(0);
            $table->boolean('is_locked')->default(false);
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'date']);
            $table->index(['tenant_id', 'date', 'status'], 'att_summaries_tenant_date_status_index');
            $table->index(['tenant_id', 'date', 'is_locked'], 'att_summaries_tenant_date_locked_index');
        });

        Schema::create('attendance_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('date');
            $table->enum('field', ['clock_in', 'clock_out', 'status']);
            $table->string('old_value')->nullable();
            $table->string('proposed_value')->nullable();
            $table->text('reason')->nullable();
            $table->string('attachment_path')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['employee_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_corrections');
        Schema::dropIfExists('attendance_summaries');
        Schema::dropIfExists('attendance_events');
        Schema::dropIfExists('employee_schedules');
        Schema::dropIfExists('shift_pattern_items');
        Schema::dropIfExists('shift_patterns');
        Schema::dropIfExists('shifts');
    }
};
