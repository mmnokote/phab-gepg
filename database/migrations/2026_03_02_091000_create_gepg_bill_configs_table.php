<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gepg_bill_configs', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->boolean('active')->default(true);
            $table->string('sp_grp_code', 30);
            $table->string('sys_code', 30);
            $table->string('sp_code', 30);
            $table->unsignedSmallInteger('bill_typ')->default(1);
            $table->unsignedSmallInteger('pay_typ')->default(2);
            $table->string('coll_cent_code', 50)->nullable();
            $table->unsignedSmallInteger('cust_id_typ')->default(1);
            $table->string('bill_gen_by', 100)->default('System');
            $table->string('bill_appr_by', 100)->default('System');
            $table->unsignedSmallInteger('bill_pay_opt')->default(3);
            $table->unsignedSmallInteger('pay_plan')->default(1);
            $table->unsignedSmallInteger('pay_lim_typ')->default(1);
            $table->decimal('pay_lim_amt', 14, 2)->default(0);
            $table->decimal('min_pay_amt', 14, 2)->default(100);
            $table->string('sub_sp_code', 30)->nullable();
            $table->char('use_item_ref_on_pay', 1)->default('N');
            $table->string('coll_sp', 30)->nullable();
            $table->string('coll_psp', 100)->nullable();
            $table->unsignedInteger('bill_ttl_days')->default(30);
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gepg_bill_configs');
    }
};
