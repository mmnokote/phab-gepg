<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_rules', function (Blueprint $table) {
            $table->string('app_code', 50)->default('DEFAULT')->after('code');
        });

        Schema::table('fee_rules', function (Blueprint $table) {
            try {
                $table->dropUnique('fee_rules_code_unique');
            } catch (\Throwable) {
                // Ignore when constraint is already absent.
            }

            $table->unique(['app_code', 'code'], 'fee_rules_app_code_code_unique');
            $table->index(['app_code', 'active'], 'fee_rules_app_code_active_idx');
        });

        Schema::table('gepg_bill_configs', function (Blueprint $table) {
            $table->string('app_code', 50)->default('DEFAULT')->after('code');
            $table->string('profile_code', 50)->default('DEFAULT')->after('app_code');
        });

        Schema::table('gepg_bill_configs', function (Blueprint $table) {
            try {
                $table->dropUnique('gepg_bill_configs_code_unique');
            } catch (\Throwable) {
                // Ignore when constraint is already absent.
            }

            $table->unique(['app_code', 'profile_code'], 'gepg_bill_configs_app_profile_unique');
            $table->index(['app_code', 'active'], 'gepg_bill_configs_app_active_idx');
        });
    }

    public function down(): void
    {
        Schema::table('gepg_bill_configs', function (Blueprint $table) {
            $table->dropUnique('gepg_bill_configs_app_profile_unique');
            $table->dropIndex('gepg_bill_configs_app_active_idx');
            $table->unique('code', 'gepg_bill_configs_code_unique');
            $table->dropColumn(['profile_code', 'app_code']);
        });

        Schema::table('fee_rules', function (Blueprint $table) {
            $table->dropUnique('fee_rules_app_code_code_unique');
            $table->dropIndex('fee_rules_app_code_active_idx');
            $table->unique('code', 'fee_rules_code_unique');
            $table->dropColumn('app_code');
        });
    }
};
