<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_ptkp_rates', function (Blueprint $table) {
            $table->id();
            $table->enum('ptkp_status', ['TK0', 'TK1', 'TK2', 'TK3', 'K0', 'K1', 'K2', 'K3']);
            $table->unsignedSmallInteger('year');
            $table->bigInteger('annual_amount');
            $table->timestamps();

            $table->unique(['ptkp_status', 'year']);
        });

        Schema::create('tax_ter_rates', function (Blueprint $table) {
            $table->id();
            $table->enum('category', ['A', 'B', 'C']);
            $table->bigInteger('income_from');
            $table->bigInteger('income_to');
            $table->decimal('rate', 5, 2);
            $table->unsignedSmallInteger('year');
            $table->timestamps();

            $table->index(['category', 'year', 'income_from']);
        });

        Schema::create('bpjs_rates', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['kesehatan', 'jht', 'jp', 'jkk', 'jkm']);
            $table->decimal('employee_pct', 5, 2)->default(0);
            $table->decimal('employer_pct', 5, 2)->default(0);
            $table->bigInteger('salary_cap')->nullable();
            $table->date('effective_date');
            $table->timestamps();

            $table->index(['type', 'effective_date']);
        });

        Schema::create('regional_minimum_wages', function (Blueprint $table) {
            $table->id();
            $table->string('province_code');
            $table->string('city_code')->nullable();
            $table->bigInteger('amount');
            $table->unsignedSmallInteger('effective_year');
            $table->timestamps();

            $table->index(['province_code', 'effective_year']);
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->date('date');
            $table->string('name');
            $table->timestamps();

            $table->index(['tenant_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('regional_minimum_wages');
        Schema::dropIfExists('bpjs_rates');
        Schema::dropIfExists('tax_ter_rates');
        Schema::dropIfExists('tax_ptkp_rates');
    }
};
