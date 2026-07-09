<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blind-index hash for NPWP plus per-tenant uniqueness for NPWP and email,
 * so duplicate NPWP/email can be rejected per field (QA-0002). NIK already
 * has nik_ktp_hash + a unique index from the base employees migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->char('npwp_hash', 64)->nullable()->after('npwp');
            $table->unique(['tenant_id', 'npwp_hash']);
            $table->unique(['tenant_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'email']);
            $table->dropUnique(['tenant_id', 'npwp_hash']);
            $table->dropColumn('npwp_hash');
        });
    }
};
