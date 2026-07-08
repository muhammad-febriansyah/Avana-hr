<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->after('tenant_id')->constrained('employees')->nullOnDelete();
            $table->string('avatar_path')->nullable()->after('email');
            $table->text('mfa_secret')->nullable()->after('password');
            $table->boolean('is_active')->default(true)->after('mfa_secret');
            $table->timestamp('last_login_at')->nullable()->after('is_active');

            // Multi-tenant: email is unique per tenant, not globally.
            $table->dropUnique('users_email_unique');
            $table->unique(['tenant_id', 'email']);
            $table->unique('employee_id');
            $table->index(['tenant_id', 'is_active']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->foreign('payroll_group_id')->references('id')->on('payroll_groups')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['payroll_group_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropForeign(['employee_id']);
            $table->dropUnique(['tenant_id', 'email']);
            $table->dropUnique(['employee_id']);
            $table->dropIndex(['tenant_id', 'is_active']);
            $table->dropColumn(['tenant_id', 'employee_id', 'avatar_path', 'mfa_secret', 'is_active', 'last_login_at']);
            $table->unique('email');
        });
    }
};
