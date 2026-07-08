<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('driver', ['anthropic', 'openai', 'gemini', 'openai_compatible']);
            $table->string('base_url')->nullable();
            $table->text('api_key')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('ai_feature_configs', function (Blueprint $table) {
            $table->id();
            $table->string('feature_code')->unique();
            $table->foreignId('ai_provider_id')->nullable()->constrained('ai_providers')->nullOnDelete();
            $table->string('model')->nullable();
            $table->text('system_prompt')->nullable();
            $table->decimal('temperature', 3, 2)->default(0.3);
            $table->integer('max_tokens')->default(1000);
            $table->bigInteger('monthly_token_budget_per_tenant')->default(0);
            $table->foreignId('fallback_provider_id')->nullable()->constrained('ai_providers')->nullOnDelete();
            $table->string('fallback_model')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tenant_ai_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();
            $table->boolean('is_enabled')->default(false);
            $table->bigInteger('monthly_token_budget_override')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('feature_code');
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->integer('input_tokens')->default(0);
            $table->integer('output_tokens')->default(0);
            $table->integer('duration_ms')->default(0);
            $table->enum('status', ['success', 'error', 'budget_exceeded'])->default('success');
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'feature_code', 'created_at'], 'ai_usage_tenant_feature_created_index');
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
        Schema::dropIfExists('tenant_ai_settings');
        Schema::dropIfExists('ai_feature_configs');
        Schema::dropIfExists('ai_providers');
    }
};
