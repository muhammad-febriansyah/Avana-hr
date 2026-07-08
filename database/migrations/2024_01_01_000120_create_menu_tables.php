<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('parent_id')->nullable()->constrained('menus')->nullOnDelete();
            $table->string('label_default');
            $table->string('icon')->nullable();
            $table->string('route_name')->nullable();
            $table->string('permission_code')->nullable();
            $table->string('feature_code')->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->boolean('is_core')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['parent_id', 'sort_order']);
        });

        Schema::create('tenant_menu_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('menu_id')->constrained('menus')->cascadeOnDelete();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'menu_id'], 'tenant_menu_overrides_tenant_menu_unique');
        });

        Schema::create('tenant_menu_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('menu_id')->constrained('menus')->cascadeOnDelete();
            $table->boolean('is_visible')->default(true);
            $table->string('label_alias')->nullable();
            $table->smallInteger('sort_order')->nullable();
            $table->foreignId('parent_override_id')->nullable()->constrained('menus')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'menu_id'], 'tenant_menu_settings_tenant_menu_unique');
        });

        Schema::create('tenant_menu_role_visibility', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_menu_setting_id')->constrained('tenant_menu_settings')->cascadeOnDelete();
            // role_id references spatie roles table (created by spatie migration); no FK here.
            $table->unsignedBigInteger('role_id');
            $table->timestamps();

            $table->unique(['tenant_menu_setting_id', 'role_id'], 'tmrv_setting_role_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_menu_role_visibility');
        Schema::dropIfExists('tenant_menu_settings');
        Schema::dropIfExists('tenant_menu_overrides');
        Schema::dropIfExists('menus');
    }
};
