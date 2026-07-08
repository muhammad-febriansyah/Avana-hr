<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_flows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('approvable_type');
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'approvable_type', 'name'], 'approval_flows_tenant_type_name_unique');
            $table->index(['tenant_id', 'approvable_type', 'is_active'], 'approval_flows_tenant_type_active_index');
        });

        Schema::create('approval_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flow_id')->constrained('approval_flows')->cascadeOnDelete();
            $table->unsignedTinyInteger('seq');
            $table->enum('approver_type', ['direct_manager', 'user', 'role', 'position']);
            $table->unsignedBigInteger('approver_id')->nullable();
            $table->bigInteger('min_amount')->nullable();
            $table->unsignedSmallInteger('sla_hours')->nullable();
            $table->timestamps();

            $table->unique(['flow_id', 'seq']);
        });

        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('approvable_type');
            $table->unsignedBigInteger('approvable_id');
            $table->foreignId('flow_id')->nullable()->constrained('approval_flows')->nullOnDelete();
            $table->unsignedTinyInteger('current_step')->default(1);
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->foreignId('requested_by')->constrained('users');
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['approvable_type', 'approvable_id']);
            $table->index(['tenant_id', 'requested_by']);
        });

        Schema::create('approval_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_id')->constrained('approvals')->cascadeOnDelete();
            $table->unsignedTinyInteger('step_seq');
            $table->foreignId('approver_user_id')->constrained('users');
            $table->foreignId('delegated_from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('note')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->timestamps();

            $table->index(['approval_id', 'step_seq']);
            $table->index(['approver_user_id', 'status']);
        });

        Schema::create('approval_delegations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('from_user_id')->constrained('users');
            $table->foreignId('to_user_id')->constrained('users');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'from_user_id', 'is_active'], 'approval_deleg_tenant_from_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_delegations');
        Schema::dropIfExists('approval_actions');
        Schema::dropIfExists('approvals');
        Schema::dropIfExists('approval_steps');
        Schema::dropIfExists('approval_flows');
    }
};
